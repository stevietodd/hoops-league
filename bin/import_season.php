<?php

declare(strict_types=1);

/**
 * Import a season from CSV files.
 *
 * Usage:
 *   php bin/import_season.php --season="Summer 2026" \
 *     --teams=imports/summer-2026/teams.csv \
 *     --schedule=imports/summer-2026/schedule.csv
 *
 * teams.csv columns:
 *   team_name, first_name, last_name, ranking, isCaptain
 *   optional: display_name, last_initial, player_ranking, team_display_name
 *   - ranking = team number (used for schedule matching, e.g. 1–8)
 *   - player_ranking = per-player rating stored in players.current_ranking
 *     (falls back to ranking when omitted)
 *   - team_display_name overrides the default "Team {team_name}" label
 *
 * schedule.csv columns: date, time, team1, team2
 *   - team1 = home, team2 = away
 *   - team1/team2 may be ranking, team_name, or team display_name
 *
 * Public team labels always come from teams.display_name.
 * Public player names always come from players.display_name.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

function usage(): never
{
    fwrite(STDERR, "Usage: php bin/import_season.php --season=\"Name\" --teams=path/to/teams.csv --schedule=path/to/schedule.csv [--keep-results]\n");
    exit(1);
}

function parse_args(array $argv): array
{
    $out = [
        'season' => null,
        'teams' => null,
        'schedule' => null,
        'keep_results' => false,
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--keep-results') {
            $out['keep_results'] = true;
            continue;
        }
        if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
            fwrite(STDERR, "Unknown argument: {$arg}\n");
            usage();
        }
        [$k, $v] = explode('=', substr($arg, 2), 2);
        if (!array_key_exists($k, $out)) {
            fwrite(STDERR, "Unknown option: --{$k}\n");
            usage();
        }
        $out[$k] = $v;
    }
    if (!$out['season'] || !$out['teams'] || !$out['schedule']) {
        usage();
    }
    return $out;
}

function read_csv(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("File not found: {$path}");
    }
    $fh = fopen($path, 'r');
    if ($fh === false) {
        throw new RuntimeException("Cannot open: {$path}");
    }
    $header = fgetcsv($fh);
    if ($header === false) {
        fclose($fh);
        throw new RuntimeException("Empty CSV: {$path}");
    }
    $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
    $rows = [];
    while (($data = fgetcsv($fh)) !== false) {
        if (count($data) === 1 && trim((string) $data[0]) === '') {
            continue;
        }
        $row = [];
        foreach ($header as $i => $key) {
            $row[$key] = trim((string) ($data[$i] ?? ''));
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

function require_columns(array $rows, array $required, string $label): void
{
    if (!$rows) {
        throw new RuntimeException("{$label} CSV has no data rows.");
    }
    $keys = array_keys($rows[0]);
    foreach ($required as $col) {
        if (!in_array($col, $keys, true)) {
            throw new RuntimeException("{$label} CSV missing column: {$col}");
        }
    }
}

function parse_bool(string $value): bool
{
    $v = strtolower(trim($value));
    return in_array($v, ['1', 'true', 'yes', 'y'], true);
}

function normalize_time(string $time): string
{
    $time = trim($time);
    if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
        [$h, $m] = array_map('intval', explode(':', $time));
        return sprintf('%02d:%02d', $h, $m);
    }
    if (preg_match('/^\d{1,2}$/', $time)) {
        return sprintf('%02d:00', (int) $time);
    }
    throw new RuntimeException("Invalid time: {$time}");
}

function import_last_initial(array $row): string
{
    if (!empty($row['last_initial'])) {
        return strtoupper(substr(trim($row['last_initial']), 0, 1));
    }
    $last = trim($row['last_name'] ?? '');
    return $last !== '' ? strtoupper(substr($last, 0, 1)) : '';
}

function import_display_name(array $row, string $lastInitial): string
{
    if (!empty($row['display_name'])) {
        return trim($row['display_name']);
    }
    $first = trim($row['first_name'] ?? '');
    $last = trim($row['last_name'] ?? '');
    // Prefer first + initial when we have a first name.
    if ($first !== '') {
        return default_player_display($first, $lastInitial);
    }
    // Surname-only legacy rows: keep surname as display until manually tweaked.
    if ($last !== '') {
        return $last;
    }
    $display = default_player_display($first, $lastInitial);
    return $display !== 'Player' ? $display : 'Player';
}

/** @var array<int, string> */
const DEFAULT_TEAM_COLORS = [
    1 => '#1d4ed8',
    2 => '#b45309',
    3 => '#166534',
    4 => '#7c3aed',
    5 => '#be123c',
    6 => '#0f766e',
    7 => '#c2410c',
    8 => '#334155',
];

$args = parse_args($argv);
$teamRows = read_csv($args['teams']);
$scheduleRows = read_csv($args['schedule']);
require_columns($teamRows, ['team_name', 'first_name', 'last_name', 'ranking', 'iscaptain'], 'teams');
require_columns($scheduleRows, ['date', 'time', 'team1', 'team2'], 'schedule');

$pdo = Database::pdo();
$pdo->beginTransaction();

try {
    $pdo->exec('DELETE FROM results');
    $pdo->exec('DELETE FROM games');
    $pdo->exec('DELETE FROM team_roster');
    $pdo->exec('DELETE FROM teams');
    $pdo->exec('DELETE FROM players');
    $pdo->exec('DELETE FROM seasons');

    $dates = array_column($scheduleRows, 'date');
    sort($dates);
    $start = $dates[0] ?? null;
    $end = $dates ? $dates[count($dates) - 1] : null;

    $insSeason = $pdo->prepare(
        'INSERT INTO seasons (name, start_date, end_date, is_active) VALUES (?, ?, ?, 1)'
    );
    $insSeason->execute([$args['season'], $start, $end]);
    $seasonId = (int) $pdo->lastInsertId();

    /** @var array<string, list<array>> $groups */
    $groups = [];
    foreach ($teamRows as $i => $row) {
        $name = $row['team_name'];
        $ranking = $row['ranking'];
        if ($name === '' || $ranking === '') {
            throw new RuntimeException('teams.csv row ' . ($i + 2) . ': team_name and ranking are required.');
        }
        $key = $ranking . "\0" . $name;
        $groups[$key][] = $row + ['_line' => $i + 2];
    }

    /** @var array<string, int> $teamsByAlias */
    $teamsByAlias = [];

    $insPlayer = $pdo->prepare(
        'INSERT INTO players (first_name, last_initial, display_name, current_ranking)
         VALUES (?, ?, ?, ?)'
    );
    $insTeam = $pdo->prepare(
        'INSERT INTO teams (captain_id, display_name, color, team_number) VALUES (?, ?, ?, ?)'
    );
    $insRoster = $pdo->prepare('INSERT INTO team_roster (team_id, player_id) VALUES (?, ?)');

    foreach ($groups as $rows) {
        $teamName = $rows[0]['team_name'];
        $ranking = $rows[0]['ranking'];
        $color = DEFAULT_TEAM_COLORS[(int) $ranking] ?? '';

        $captainRow = null;
        foreach ($rows as $row) {
            if (parse_bool($row['iscaptain'])) {
                $captainRow = $row;
            }
        }
        if ($captainRow === null) {
            $captainRow = $rows[0];
        }

        $createPlayer = static function (array $row) use ($insPlayer, $ranking): array {
            $first = trim($row['first_name'] ?? '');
            $initial = import_last_initial($row);
            $display = import_display_name($row, $initial);
            if ($display === 'Player') {
                throw new RuntimeException('teams.csv row ' . $row['_line'] . ': need a displayable name.');
            }
            $playerRanking = trim((string) ($row['player_ranking'] ?? $row['current_ranking'] ?? $ranking));
            $insPlayer->execute([$first, $initial, $display, $playerRanking]);
            return [
                'id' => (int) Database::pdo()->lastInsertId(),
                'display_name' => $display,
            ];
        };

        $captain = $createPlayer($captainRow);
        $teamDisplay = trim((string) ($rows[0]['team_display_name'] ?? ''));
        if ($teamDisplay === '') {
            $teamDisplay = default_team_display($teamName, $captain['display_name']);
        }
        $insTeam->execute([$captain['id'], $teamDisplay, $color, $ranking]);
        $teamId = (int) $pdo->lastInsertId();
        $insRoster->execute([$teamId, $captain['id']]);

        foreach ($rows as $row) {
            if ($row === $captainRow) {
                continue;
            }
            $player = $createPlayer($row);
            $insRoster->execute([$teamId, $player['id']]);
        }

        $aliases = [
            $ranking,
            (string) (int) $ranking,
            $teamName,
            $teamDisplay,
            $captain['display_name'],
            'Team ' . $captain['display_name'],
        ];
        foreach ($aliases as $alias) {
            if ($alias !== '') {
                $teamsByAlias[$alias] = $teamId;
            }
        }
    }

    $resolveTeam = static function (string $token) use ($teamsByAlias): int {
        $token = trim($token);
        if ($token === '') {
            throw new RuntimeException('Empty team token in schedule.');
        }
        if (isset($teamsByAlias[$token])) {
            return $teamsByAlias[$token];
        }
        if (ctype_digit($token) && isset($teamsByAlias[(string) (int) $token])) {
            return $teamsByAlias[(string) (int) $token];
        }
        throw new RuntimeException("Unknown team in schedule: {$token}");
    };

    $tz = new DateTimeZone((string) config('timezone'));
    $insGame = $pdo->prepare(
        'INSERT INTO games (season_id, home_team_id, away_team_id, tipoff, location, status)
         VALUES (?, ?, ?, ?, ?, \'scheduled\')'
    );

    foreach ($scheduleRows as $i => $row) {
        $date = $row['date'];
        $time = normalize_time($row['time']);
        $homeId = $resolveTeam($row['team1']);
        $awayId = $resolveTeam($row['team2']);
        if ($homeId === $awayId) {
            throw new RuntimeException('schedule.csv row ' . ($i + 2) . ': team1 and team2 must differ.');
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $tz);
        if (!$dt) {
            $dt = DateTimeImmutable::createFromFormat('n/j/Y H:i', $date . ' ' . $time, $tz)
                ?: DateTimeImmutable::createFromFormat('m/d/Y H:i', $date . ' ' . $time, $tz);
        }
        if (!$dt) {
            throw new RuntimeException('schedule.csv row ' . ($i + 2) . ": invalid date/time {$date} {$time}");
        }
        $insGame->execute([
            $seasonId,
            $homeId,
            $awayId,
            $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            '',
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$teamCount = (int) $pdo->query('SELECT COUNT(*) FROM teams')->fetchColumn();
$playerCount = (int) $pdo->query('SELECT COUNT(*) FROM players')->fetchColumn();
$gameCount = (int) $pdo->query('SELECT COUNT(*) FROM games')->fetchColumn();

echo "Imported season: {$args['season']}\n";
echo "  Teams:   {$teamCount}\n";
echo "  Players: {$playerCount}\n";
echo "  Games:   {$gameCount}\n";
