<?php

declare(strict_types=1);

final class HomeController
{
    public static function home(): void
    {
        $season = Database::activeSeason();
        $standings = Standings::compute($season ? (int) $season['id'] : null);
        $teams = Database::pdo()->query('SELECT * FROM teams ORDER BY CAST(abbrev AS INTEGER), name')->fetchAll();
        render('home', [
            'title' => 'Home',
            'season' => $season,
            'standings' => $standings,
            'teams' => $teams,
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
