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

        render('seasons', [
            'title' => 'Seasons',
            'seasons' => $seasons,
        ]);
    }
}
