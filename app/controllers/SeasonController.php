<?php

declare(strict_types=1);

final class SeasonController
{
    public static function index(): void
    {
        $seasons = Database::pdo()->query(
            'SELECT s.*,
                    t.display_name AS champion_display_name,
                    t.team_number AS champion_team_number
             FROM seasons s
             LEFT JOIN teams t ON t.id = s.champion_team_id
             ORDER BY s.is_active DESC, s.start_date DESC, s.id DESC'
        )->fetchAll();

        $roster = Database::pdo()->prepare(
            'SELECT p.display_name, (t.captain_id = p.id) AS is_captain
             FROM team_roster tr
             JOIN players p ON p.id = tr.player_id
             JOIN teams t ON t.id = tr.team_id
             WHERE tr.team_id = ?
             ORDER BY is_captain DESC, p.display_name'
        );
        foreach ($seasons as &$s) {
            $s['champion_players'] = [];
            if (!empty($s['champion_team_id'])) {
                $roster->execute([(int) $s['champion_team_id']]);
                $s['champion_players'] = $roster->fetchAll();
            }
        }
        unset($s);

        render('seasons', [
            'title' => 'Seasons',
            'seasons' => $seasons,
        ]);
    }
}
