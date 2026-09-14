<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function init(string $dbPath): void
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $isNew = !is_file($dbPath);
        self::$pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        self::$pdo->exec('PRAGMA foreign_keys = ON');

        if ($isNew) {
            self::applySchema();
            return;
        }

        if (!self::hasModernPlayerSchema()) {
            self::rebuildLeagueSchema();
            return;
        }

        self::migratePlayoffs();
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            throw new RuntimeException('Database not initialized.');
        }
        return self::$pdo;
    }

    public static function activeSeason(): ?array
    {
        $stmt = self::pdo()->query('SELECT * FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function applySchema(): void
    {
        $schema = file_get_contents(ROOT_PATH . '/sql/schema.sql');
        self::pdo()->exec($schema);
    }

    public static function rebuildLeagueSchema(): void
    {
        $pdo = self::pdo();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        foreach ([
            'results',
            'playoff_slots',
            'playoff_tournaments',
            'games',
            'team_roster',
            'teams',
            'players',
            'seasons',
        ] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
        $pdo->exec('DROP TABLE IF EXISTS players_legacy');
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::applySchema();
    }

    public static function migratePlayoffs(): void
    {
        $pdo = self::pdo();
        $seasonCols = array_column($pdo->query('PRAGMA table_info(seasons)')->fetchAll(), 'name');
        if (!in_array('status', $seasonCols, true)) {
            $pdo->exec("ALTER TABLE seasons ADD COLUMN status TEXT NOT NULL DEFAULT 'active'");
        }
        if (!in_array('champion_team_id', $seasonCols, true)) {
            $pdo->exec('ALTER TABLE seasons ADD COLUMN champion_team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL');
        }

        $gameCols = array_column($pdo->query('PRAGMA table_info(games)')->fetchAll(), 'name');
        if (!in_array('phase', $gameCols, true)) {
            $pdo->exec("ALTER TABLE games ADD COLUMN phase TEXT NOT NULL DEFAULT 'regular'");
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS playoff_tournaments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                season_id INTEGER NOT NULL UNIQUE REFERENCES seasons(id) ON DELETE CASCADE,
                bracket_size INTEGER NOT NULL CHECK (bracket_size IN (4, 8)),
                status TEXT NOT NULL DEFAULT \'setup\' CHECK (status IN (\'setup\', \'in_progress\', \'complete\'))
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS playoff_slots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tournament_id INTEGER NOT NULL REFERENCES playoff_tournaments(id) ON DELETE CASCADE,
                round INTEGER NOT NULL,
                slot_index INTEGER NOT NULL,
                team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL,
                seed INTEGER,
                game_id INTEGER REFERENCES games(id) ON DELETE SET NULL,
                feeds_slot_id INTEGER REFERENCES playoff_slots(id) ON DELETE SET NULL,
                planned_tipoff TEXT,
                planned_location TEXT NOT NULL DEFAULT \'\',
                UNIQUE (tournament_id, round, slot_index)
            )'
        );
        $slotCols = array_column($pdo->query('PRAGMA table_info(playoff_slots)')->fetchAll(), 'name');
        if (!in_array('planned_tipoff', $slotCols, true)) {
            $pdo->exec('ALTER TABLE playoff_slots ADD COLUMN planned_tipoff TEXT');
        }
        if (!in_array('planned_location', $slotCols, true)) {
            $pdo->exec("ALTER TABLE playoff_slots ADD COLUMN planned_location TEXT NOT NULL DEFAULT ''");
        }
        // Keep planned schedule in sync with any games already created.
        $pdo->exec(
            'UPDATE playoff_slots
             SET planned_tipoff = (SELECT tipoff FROM games WHERE games.id = playoff_slots.game_id),
                 planned_location = COALESCE(
                   (SELECT location FROM games WHERE games.id = playoff_slots.game_id),
                   planned_location
                 )
             WHERE game_id IS NOT NULL
               AND (planned_tipoff IS NULL OR planned_tipoff = \'\')'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_games_phase ON games(season_id, phase)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_playoff_slots_tournament ON playoff_slots(tournament_id, round, slot_index)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_playoff_slots_game ON playoff_slots(game_id)');
    }

    private static function hasModernPlayerSchema(): bool
    {
        try {
            $playerCols = array_column(self::pdo()->query('PRAGMA table_info(players)')->fetchAll(), 'name');
            $teamCols = array_column(self::pdo()->query('PRAGMA table_info(teams)')->fetchAll(), 'name');
        } catch (Throwable) {
            return false;
        }
        return in_array('first_name', $playerCols, true)
            && in_array('last_initial', $playerCols, true)
            && in_array('display_name', $playerCols, true)
            && in_array('current_ranking', $playerCols, true)
            && in_array('captain_id', $teamCols, true)
            && in_array('display_name', $teamCols, true)
            && in_array('team_number', $teamCols, true)
            && !in_array('name', $teamCols, true);
    }
}
