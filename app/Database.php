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
        }
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
        foreach (['results', 'games', 'team_roster', 'teams', 'players', 'seasons'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
        // Drop legacy tables if present
        $pdo->exec('DROP TABLE IF EXISTS players_legacy');
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::applySchema();
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
