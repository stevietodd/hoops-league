<?php

declare(strict_types=1);

/**
 * Start a new season from a roster CSV, keeping all previous seasons intact.
 *
 * Usage:
 *   php bin/import_roster_season.php --season="Fall 2026" --roster=path/to/roster.csv [--commit]
 *
 * Without --commit the import runs inside a transaction and is rolled back (dry run).
 *
 * roster.csv columns:
 *   team_number, team_display_name, first_name, last_name, display_name,
 *   ranking, is_captain, existing_player_id
 *   - existing_player_id links a returning player; blank creates a new player.
 *   - ranking is stored in players.current_ranking for every listed player.
 *
 * The previously active season is archived (champion and rosters untouched).
 * Players not listed keep their rows and stay on past rosters only.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

function usage(): never
{
    fwrite(STDERR, "Usage: php bin/import_roster_season.php --season=\"Name\" --roster=path/to/roster.csv [--commit]\n");
    exit(1);
}

$opts = ['season' => null, 'roster' => null, 'commit' => false];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--commit') {
        $opts['commit'] = true;
        continue;
    }
    if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
        usage();
    }
    [$k, $v] = explode('=', substr($arg, 2), 2);
    if (!array_key_exists($k, $opts) || $k === 'commit') {
        usage();
    }
    $opts[$k] = $v;
}
if (!$opts['season'] || !$opts['roster'] || !is_file($opts['roster'])) {
    usage();
}

$fh = fopen($opts['roster'], 'r');
$header = array_map(static fn ($h) => strtolower(trim((string) $h)), fgetcsv($fh) ?: []);
$rows = [];
while (($data = fgetcsv($fh)) !== false) {
    if (count($data) === 1 && trim((string) $data[0]) === '') {
        continue;
    }
    $rows[] = array_combine($header, array_map('trim', array_pad($data, count($header), '')));
}
fclose($fh);

foreach (['team_number', 'team_display_name', 'first_name', 'last_name', 'display_name', 'ranking', 'is_captain', 'existing_player_id'] as $col) {
    if (!in_array($col, $header, true)) {
        fwrite(STDERR, "Roster CSV missing column: {$col}\n");
        exit(1);
    }
}

const TEAM_COLORS = [
    1 => '#1d4ed8', 2 => '#b45309', 3 => '#166534', 4 => '#7c3aed', 5 => '#be123c',
    6 => '#0f766e', 7 => '#c2410c', 8 => '#334155', 9 => '#a16207',
];

$pdo = Database::pdo();
$pdo->beginTransaction();

try {
    $exists = $pdo->prepare('SELECT COUNT(*) FROM seasons WHERE name = ?');
    $exists->execute([$opts['season']]);
    if ((int) $exists->fetchColumn() > 0) {
        throw new RuntimeException("Season already exists: {$opts['season']}");
    }

    $previous = Database::activeSeason();
    $pdo->exec("UPDATE seasons SET is_active = 0, status = 'archived' WHERE is_active = 1");
    $pdo->prepare("INSERT INTO seasons (name, is_active, status) VALUES (?, 1, 'active')")
        ->execute([$opts['season']]);
    $seasonId = (int) $pdo->lastInsertId();

    $findPlayer = $pdo->prepare('SELECT * FROM players WHERE id = ?');
    $updPlayer = $pdo->prepare('UPDATE players SET current_ranking = ?, last_initial = ? WHERE id = ?');
    $insPlayer = $pdo->prepare(
        'INSERT INTO players (first_name, last_initial, display_name, current_ranking) VALUES (?, ?, ?, ?)'
    );

    /** @var array<int, array{name: string, captain: ?int, players: list<int>}> $teams */
    $teams = [];
    $returning = 0;
    $created = 0;
    $seenPlayers = [];

    foreach ($rows as $i => $row) {
        $line = $i + 2;
        $num = (int) $row['team_number'];
        if ($num < 1 || $row['team_display_name'] === '' || $row['display_name'] === '' || $row['ranking'] === '') {
            throw new RuntimeException("roster row {$line}: team_number, team_display_name, display_name and ranking are required.");
        }
        $initial = strtoupper(substr($row['last_name'], 0, 1));

        if ($row['existing_player_id'] !== '') {
            $playerId = (int) $row['existing_player_id'];
            $findPlayer->execute([$playerId]);
            $player = $findPlayer->fetch();
            if (!$player) {
                throw new RuntimeException("roster row {$line}: player #{$playerId} not found.");
            }
            if (strcasecmp($player['display_name'], $row['display_name']) !== 0) {
                throw new RuntimeException("roster row {$line}: player #{$playerId} is {$player['display_name']}, expected {$row['display_name']}.");
            }
            $updPlayer->execute([$row['ranking'], $initial, $playerId]);
            $returning++;
        } else {
            $insPlayer->execute([$row['first_name'], $initial, $row['display_name'], $row['ranking']]);
            $playerId = (int) $pdo->lastInsertId();
            $created++;
        }

        if (isset($seenPlayers[$playerId])) {
            throw new RuntimeException("roster row {$line}: {$row['display_name']} is listed twice.");
        }
        $seenPlayers[$playerId] = true;

        $teams[$num] ??= ['name' => $row['team_display_name'], 'captain' => null, 'players' => []];
        $teams[$num]['players'][] = $playerId;
        if ($row['is_captain'] === '1') {
            if ($teams[$num]['captain'] !== null) {
                throw new RuntimeException("Team {$num} has more than one captain.");
            }
            $teams[$num]['captain'] = $playerId;
        }
    }

    $insTeam = $pdo->prepare(
        'INSERT INTO teams (season_id, captain_id, display_name, color, team_number) VALUES (?, ?, ?, ?, ?)'
    );
    $insRoster = $pdo->prepare('INSERT INTO team_roster (team_id, player_id) VALUES (?, ?)');
    ksort($teams);
    foreach ($teams as $num => $team) {
        if ($team['captain'] === null) {
            throw new RuntimeException("Team {$num} ({$team['name']}) has no captain.");
        }
        $insTeam->execute([$seasonId, $team['captain'], $team['name'], TEAM_COLORS[$num] ?? '', (string) $num]);
        $teamId = (int) $pdo->lastInsertId();
        foreach ($team['players'] as $playerId) {
            $insRoster->execute([$teamId, $playerId]);
        }
    }

    $summary = sprintf(
        "Season: %s (archived: %s)\n  Teams: %d\n  Returning players: %d\n  New players: %d\n",
        $opts['season'],
        $previous['name'] ?? 'none',
        count($teams),
        $returning,
        $created
    );

    if ($opts['commit']) {
        $pdo->commit();
        echo "Imported.\n" . $summary;
    } else {
        $pdo->rollBack();
        echo "Dry run (rolled back; pass --commit to apply).\n" . $summary;
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . "\n");
    exit(1);
}
