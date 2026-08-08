<?php

declare(strict_types=1);

/**
 * Seed demo league data. Run: php bin/seed.php
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::pdo();

$pdo->exec('DELETE FROM results');
$pdo->exec('DELETE FROM games');
$pdo->exec('DELETE FROM players');
$pdo->exec('DELETE FROM teams');
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
    ['Court Kings', 'KNG', '#1d4ed8', 'Alex King', 'alex@hoops.local', ['Jordan Lee', 'Sam Rivera', 'Chris Patton']],
    ['Fast Break', 'FBK', '#b45309', 'Morgan Swift', 'morgan@hoops.local', ['Riley Chen', 'Taylor Brooks', 'Jamie Ortiz']],
    ['Rim Runners', 'RIM', '#166534', 'Drew Hayes', 'drew@hoops.local', ['Casey Nguyen', 'Avery Scott', 'Quinn Diaz']],
    ['Alley-Oops', 'AOP', '#7c3aed', 'Parker Lane', 'parker@hoops.local', ['Reese Kim', 'Blake Torres', 'Cameron Wells']],
];

$teamIds = [];
$insTeam = $pdo->prepare('INSERT INTO teams (name, abbrev, color) VALUES (?, ?, ?)');
$insPlayer = $pdo->prepare(
    'INSERT INTO players (user_id, team_id, display_name, is_captain) VALUES (?, ?, ?, ?)'
);

foreach ($roster as [$name, $abbrev, $color, $captainName, $email, $players]) {
    $insTeam->execute([$name, $abbrev, $color]);
    $teamId = (int) $pdo->lastInsertId();
    $teamIds[$name] = $teamId;

    $parts = explode(' ', $captainName, 2);
    $insUser->execute([$email, $password, $parts[0], $parts[1] ?? '', 0, 0]);
    $userId = (int) $pdo->lastInsertId();
    $insPlayer->execute([$userId, $teamId, $captainName, 1]);

    foreach ($players as $playerName) {
        $insPlayer->execute([null, $teamId, $playerName, 0]);
    }
}

$tz = new DateTimeZone('America/New_York');
$base = new DateTimeImmutable('now', $tz);
$schedule = [
    ['Fast Break', 'Court Kings', $base->modify('-7 days')->setTime(19, 0), 'North Gym', [62, 58], true],
    ['Rim Runners', 'Alley-Oops', $base->modify('-7 days')->setTime(20, 0), 'North Gym', [71, 70], true],
    ['Court Kings', 'Rim Runners', $base->modify('-3 days')->setTime(19, 0), 'South Court', [55, 60], true],
    ['Alley-Oops', 'Fast Break', $base->modify('+2 days')->setTime(19, 0), 'North Gym', null, false],
    ['Court Kings', 'Alley-Oops', $base->modify('+5 days')->setTime(20, 0), 'South Court', null, false],
    ['Fast Break', 'Rim Runners', $base->modify('+9 days')->setTime(19, 0), 'North Gym', null, false],
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
        // scores array is [away, home] matching prior Django seed
        $insResult->execute([$gameId, $scores[1], $scores[0], $adminId]);
    }
}

echo "Demo league ready.\n";
echo "  admin@hoops.local / hoops1234\n";
echo "  commissioner@hoops.local / hoops1234\n";
echo "  alex@hoops.local / hoops1234 (Court Kings captain)\n";
echo "  morgan@hoops.local / hoops1234 (Fast Break captain)\n";
