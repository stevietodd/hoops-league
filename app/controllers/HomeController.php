<?php

declare(strict_types=1);

final class HomeController
{
    public static function home(): void
    {
        $season = Database::activeSeason();
        $seasonId = $season ? (int) $season['id'] : null;
        $standings = Standings::compute($seasonId);
        $teams = Database::pdo()->query(teams_with_captain_query())->fetchAll();
        $upcoming = ScheduleController::nextGameday($seasonId);
        render('home', [
            'title' => 'Home',
            'season' => $season,
            'standings' => $standings,
            'teams' => $teams,
            'upcomingDate' => $upcoming['date'],
            'upcomingGames' => $upcoming['games'],
            'champion' => !empty($season['champion_team_id'])
                ? find_team((int) $season['champion_team_id'])
                : null,
        ]);
    }

    public static function standings(): void
    {
        $season = Database::activeSeason();
        render('standings', [
            'title' => 'Standings',
            'season' => $season,
            'standings' => Standings::compute($season ? (int) $season['id'] : null),
        ]);
    }
}
