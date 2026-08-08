<?php

declare(strict_types=1);

final class ScheduleController
{
    public static function index(): void
    {
        $season = Database::activeSeason();
        $teamId = isset($_GET['team']) && $_GET['team'] !== '' ? (int) $_GET['team'] : null;
        $games = [];
        if ($season) {
            $sql = 'SELECT g.*, ht.name AS home_name, ht.abbrev AS home_abbrev,
                           at.name AS away_name, at.abbrev AS away_abbrev,
                           r.home_score, r.away_score
                    FROM games g
                    JOIN teams ht ON ht.id = g.home_team_id
                    JOIN teams at ON at.id = g.away_team_id
                    LEFT JOIN results r ON r.game_id = g.id
                    WHERE g.season_id = ?';
            $params = [(int) $season['id']];
            if ($teamId) {
                $sql .= ' AND (g.home_team_id = ? OR g.away_team_id = ?)';
                $params[] = $teamId;
                $params[] = $teamId;
            }
            $sql .= ' ORDER BY g.tipoff';
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($params);
            $games = $stmt->fetchAll();
        }
        $teams = Database::pdo()->query('SELECT * FROM teams ORDER BY CAST(abbrev AS INTEGER), name')->fetchAll();
        render('schedule', [
            'title' => 'Schedule',
            'season' => $season,
            'games' => $games,
            'teams' => $teams,
            'selectedTeam' => $teamId,
        ]);
    }

    public static function show(string $id): void
    {
        $game = self::findGame((int) $id);
        if (!$game) {
            http_response_code(404);
            render('errors/404', ['title' => 'Not found']);
            return;
        }
        render('game_detail', [
            'title' => $game['away_name'] . ' @ ' . $game['home_name'],
            'game' => $game,
            'canReport' => Auth::canReportScore($game),
        ]);
    }

    public static function reportForm(string $id): void
    {
        Auth::requireLogin();
        $game = self::findGame((int) $id);
        if (!$game) {
            http_response_code(404);
            render('errors/404', ['title' => 'Not found']);
            return;
        }
        if (!Auth::canReportScore($game)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        render('report_score', [
            'title' => 'Report score',
            'game' => $game,
        ]);
    }

    public static function report(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $game = self::findGame((int) $id);
        if (!$game || !Auth::canReportScore($game)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        $home = filter_input(INPUT_POST, 'home_score', FILTER_VALIDATE_INT);
        $away = filter_input(INPUT_POST, 'away_score', FILTER_VALIDATE_INT);
        if ($home === false || $away === false || $home < 0 || $away < 0 || $home === $away) {
            flash('error', 'Enter valid scores. Games cannot end in a tie.');
            redirect('/games/' . $id . '/report');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $existing = $pdo->prepare('SELECT id FROM results WHERE game_id = ?');
            $existing->execute([(int) $id]);
            if ($existing->fetch()) {
                $upd = $pdo->prepare(
                    'UPDATE results SET home_score = ?, away_score = ?, submitted_by = ?, submitted_at = datetime(\'now\') WHERE game_id = ?'
                );
                $upd->execute([$home, $away, Auth::user()['id'], (int) $id]);
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO results (game_id, home_score, away_score, submitted_by) VALUES (?, ?, ?, ?)'
                );
                $ins->execute([(int) $id, $home, $away, Auth::user()['id']]);
            }
            $pdo->prepare('UPDATE games SET status = \'final\' WHERE id = ?')->execute([(int) $id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'Could not save score.');
            redirect('/games/' . $id . '/report');
        }

        flash('success', 'Score saved. Game marked final.');
        redirect('/games/' . $id);
    }

    public static function findGame(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT g.*, ht.name AS home_name, ht.abbrev AS home_abbrev,
                    at.name AS away_name, at.abbrev AS away_abbrev,
                    r.home_score, r.away_score, s.name AS season_name
             FROM games g
             JOIN teams ht ON ht.id = g.home_team_id
             JOIN teams at ON at.id = g.away_team_id
             JOIN seasons s ON s.id = g.season_id
             LEFT JOIN results r ON r.game_id = g.id
             WHERE g.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
