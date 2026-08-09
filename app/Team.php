<?php

declare(strict_types=1);

/** Team helpers — public labels always use teams.display_name. */

function team_label(array $team): string
{
    $display = trim((string) ($team['display_name'] ?? ''));
    if ($display !== '') {
        return $display;
    }
    $captain = trim((string) ($team['captain_display_name'] ?? ''));
    return $captain !== '' ? 'Team ' . $captain : 'Team';
}

function team_short(array $team): string
{
    $number = trim((string) ($team['team_number'] ?? ''));
    if ($number !== '') {
        return $number;
    }
    $display = (string) ($team['display_name'] ?? $team['captain_display_name'] ?? 'T');
    return strtoupper(substr($display, 0, 3));
}

function default_team_display(string $teamName, string $captainDisplayName): string
{
    $name = trim($teamName);
    if ($name !== '') {
        return str_starts_with(strtolower($name), 'team ') ? $name : 'Team ' . $name;
    }
    $captain = trim($captainDisplayName);
    return $captain !== '' ? 'Team ' . $captain : 'Team';
}

function teams_with_captain_query(string $orderBy = 'CAST(team_number AS INTEGER), team_number, display_name'): string
{
    return "SELECT t.*,
                   p.display_name AS captain_display_name,
                   p.current_ranking AS captain_ranking,
                   p.first_name AS captain_first_name,
                   p.last_initial AS captain_last_initial,
                   (SELECT COUNT(*) FROM team_roster tr WHERE tr.team_id = t.id) AS player_count
            FROM teams t
            JOIN players p ON p.id = t.captain_id
            ORDER BY {$orderBy}";
}

function find_team(int $id): ?array
{
    $stmt = Database::pdo()->prepare(
        'SELECT t.*,
                p.display_name AS captain_display_name,
                p.current_ranking AS captain_ranking,
                p.id AS captain_player_id
         FROM teams t
         JOIN players p ON p.id = t.captain_id
         WHERE t.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function default_player_display(string $firstName, string $lastInitial): string
{
    $first = trim($firstName);
    $initial = strtoupper(trim($lastInitial));
    if ($initial !== '' && !str_ends_with($initial, '.')) {
        $initial .= '.';
    }
    if ($first !== '' && $initial !== '') {
        return $first . ' ' . $initial;
    }
    if ($first !== '') {
        return $first;
    }
    return rtrim($initial, '.') !== '' ? $initial : 'Player';
}
