<?php

declare(strict_types=1);

final class TeamController
{
    public static function index(): void
    {
        $teams = Database::pdo()->query(
            'SELECT t.*,
                    (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id) AS player_count
             FROM teams t
             ORDER BY CAST(t.abbrev AS INTEGER), t.name'
        )->fetchAll();
        render('teams', ['title' => 'Teams', 'teams' => $teams]);
    }

    public static function show(string $id): void
    {
        $team = self::findTeam((int) $id);
        if (!$team) {
            http_response_code(404);
            render('errors/404', ['title' => 'Not found']);
            return;
        }
        $players = Database::pdo()->prepare(
            'SELECT * FROM players WHERE team_id = ? ORDER BY is_captain DESC, display_name'
        );
        $players->execute([(int) $id]);
        render('team_detail', [
            'title' => $team['name'],
            'team' => $team,
            'players' => $players->fetchAll(),
            'canManage' => Auth::canManageRoster((int) $id),
            'isCommissioner' => Auth::isCommissioner(),
        ]);
    }

    public static function addPlayer(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $teamId = (int) $id;
        if (!Auth::canManageRoster($teamId)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        $name = trim((string) ($_POST['display_name'] ?? ''));
        if ($name === '') {
            flash('error', 'Player name is required.');
            redirect('/teams/' . $teamId);
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO players (team_id, display_name, is_captain) VALUES (?, ?, 0)'
        );
        $stmt->execute([$teamId, $name]);
        flash('success', 'Added ' . $name . ' to the roster.');
        redirect('/teams/' . $teamId);
    }

    public static function removePlayer(string $teamId, string $playerId): void
    {
        Auth::requireLogin();
        verify_csrf();
        $tid = (int) $teamId;
        if (!Auth::canManageRoster($tid)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM players WHERE id = ? AND team_id = ?');
        $stmt->execute([(int) $playerId, $tid]);
        $player = $stmt->fetch();
        if ($player) {
            Database::pdo()->prepare('DELETE FROM players WHERE id = ?')->execute([(int) $playerId]);
            flash('success', 'Removed ' . $player['display_name'] . ' from the roster.');
        }
        redirect('/teams/' . $tid);
    }

    public static function assignCaptain(string $id): void
    {
        Auth::requireCommissioner();
        verify_csrf();
        $teamId = (int) $id;
        $playerId = (int) ($_POST['player_id'] ?? 0);
        $stmt = Database::pdo()->prepare('SELECT * FROM players WHERE id = ? AND team_id = ?');
        $stmt->execute([$playerId, $teamId]);
        $player = $stmt->fetch();
        if (!$player) {
            flash('error', 'Could not assign captain.');
            redirect('/teams/' . $teamId);
        }
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE players SET is_captain = 0 WHERE team_id = ?')->execute([$teamId]);
        $pdo->prepare('UPDATE players SET is_captain = 1 WHERE id = ?')->execute([$playerId]);
        flash('success', $player['display_name'] . ' is now captain.');
        redirect('/teams/' . $teamId);
    }

    public static function findTeam(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM teams WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
