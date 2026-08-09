<?php

declare(strict_types=1);

/**
 * Seed demo league data. Run: php bin/seed.php
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::pdo();

$pdo->exec('DELETE FROM results');
$pdo->exec('DELETE FROM games');
$pdo->exec('DELETE FROM team_roster');
$pdo->exec('DELETE FROM teams');
$pdo->exec('DELETE FROM players');
$pdo->exec('DELETE FROM seasons');
$pdo->exec('DELETE FROM users');

$password = password_hash('hoops1234', PASSWORD_DEFAULT);

$insUser = $pdo->prepare(
    'INSERT INTO users (email, password_hash, first_name, last_name, is_admin, is_commissioner)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$insUser->execute(['admin@hoops.local', $password, 'Avery', 'Admin', 1, 1]);
$adminId = (int) $pdo->lastInsertId();
$insUser->execute(['commissioner@hoops.local', $password, 'Casey', 'Commissioner', 0, 1]);

$pdo->prepare(
    'INSERT INTO seasons (name, start_date, end_date, is_active) VALUES (?, ?, ?, 1)'
)->execute(['Summer 2026', '2026-07-01', '2026-09-30']);
$seasonId = (int) $pdo->lastInsertId();

$roster = [
    ['1', '#1d4ed8', 'Alex', 'K', 'Alex K.', 'alex@hoops.local', [
        ['Jordan', 'L', 'Jordan L.'],
        ['Sam', 'R', 'Sam R.'],
        ['Chris', 'P', 'Chris P.'],
    ]],
    ['2', '#b45309', 'Morgan', 'S', 'Morgan S.', 'morgan@hoops.local', [
        ['Riley', 'C', 'Riley C.'],
        ['Taylor', 'B', 'Taylor B.'],
        ['Jamie', 'O', 'Jamie O.'],
    ]],
    ['3', '#166534', 'Drew', 'H', 'Drew H.', 'drew@hoops.local', [
        ['Casey', 'N', 'Casey N.'],
        ['Avery', 'S', 'Avery S.'],
        ['Quinn', 'D', 'Quinn D.'],
    ]],
    ['4', '#7c3aed', 'Parker', 'L', 'Parker L.', 'parker@hoops.local', [
        ['Reese', 'K', 'Reese K.'],
        ['Blake', 'T', 'Blake T.'],
        ['Cameron', 'W', 'Cameron W.'],
    ]],
];

$teamIds = [];
$insPlayer = $pdo->prepare(
    'INSERT INTO players (first_name, last_initial, display_name, current_ranking, user_id)
     VALUES (?, ?, ?, ?, ?)'
);
$insTeam = $pdo->prepare('INSERT INTO teams (captain_id, display_name, color, team_number) VALUES (?, ?, ?, ?)');
$insRoster = $pdo->prepare('INSERT INTO team_roster (team_id, player_id) VALUES (?, ?)');

foreach ($roster as [$ranking, $color, $capFirst, $capInitial, $capDisplay, $email, $players]) {
    $insUser->execute([$email, $password, $capFirst, $capInitial, 0, 0]);
    $userId = (int) $pdo->lastInsertId();

    $insPlayer->execute([$capFirst, $capInitial, $capDisplay, $ranking, $userId]);
    $captainId = (int) $pdo->lastInsertId();

    $insTeam->execute([$captainId, 'Team ' . $capDisplay, $color, $ranking]);
    $teamId = (int) $pdo->lastInsertId();
    $teamIds[$ranking] = $teamId;
    $insRoster->execute([$teamId, $captainId]);

    foreach ($players as [$first, $initial, $display]) {
        $insPlayer->execute([$first, $initial, $display, $ranking, null]);
        $playerId = (int) $pdo->lastInsertId();
        $insRoster->execute([$teamId, $playerId]);
    }
}

$tz = new DateTimeZone('America/New_York');
$base = new DateTimeImmutable('now', $tz);
$schedule = [
    ['2', '1', $base->modify('-7 days')->setTime(19, 0), 'North Gym', [62, 58], true],
    ['3', '4', $base->modify('-7 days')->setTime(20, 0), 'North Gym', [71, 70], true],
    ['1', '3', $base->modify('-3 days')->setTime(19, 0), 'South Court', [55, 60], true],
    ['4', '2', $base->modify('+2 days')->setTime(19, 0), 'North Gym', null, false],
    ['1', '4', $base->modify('+5 days')->setTime(20, 0), 'South Court', null, false],
    ['2', '3', $base->modify('+9 days')->setTime(19, 0), 'North Gym', null, false],
];

$insGame = $pdo->prepare(
    'INSERT INTO games (season_id, home_team_id, away_team_id, tipoff, location, status)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$insResult = $pdo->prepare(
    'INSERT INTO results (game_id, home_score, away_score, submitted_by) VALUES (?, ?, ?, ?)'
);

foreach ($schedule as [$away, $home, $tipoff, $location, $scores, $final]) {
    $insGame->execute([
        $seasonId,
        $teamIds[$home],
        $teamIds[$away],
        $tipoff->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        $location,
        $final ? 'final' : 'scheduled',
    ]);
    $gameId = (int) $pdo->lastInsertId();
    if ($final && $scores) {
        $insResult->execute([$gameId, $scores[1], $scores[0], $adminId]);
    }
}

echo "Demo league ready.\n";
echo "  admin@hoops.local / hoops1234\n";
echo "  commissioner@hoops.local / hoops1234\n";
echo "  alex@hoops.local / hoops1234 (Team Alex K.)\n";
echo "  morgan@hoops.local / hoops1234 (Team Morgan S.)\n";
