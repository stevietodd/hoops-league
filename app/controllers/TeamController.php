<?php

declare(strict_types=1);

final class TeamController
{
    public static function index(): void
    {
        $teams = Database::pdo()->query(teams_with_captain_query())->fetchAll();
        render('teams', ['title' => 'Teams', 'teams' => $teams]);
    }

    public static function show(string $id): void
    {
        $team = find_team((int) $id);
        if (!$team) {
            http_response_code(404);
            render('errors/404', ['title' => 'Not found']);
            return;
        }
        $players = Database::pdo()->prepare(
            'SELECT p.*, (t.captain_id = p.id) AS is_captain
             FROM team_roster tr
             JOIN players p ON p.id = tr.player_id
             JOIN teams t ON t.id = tr.team_id
             WHERE tr.team_id = ?
             ORDER BY is_captain DESC, p.display_name'
        );
        $players->execute([(int) $id]);
        render('team_detail', [
            'title' => team_label($team),
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
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $initial = strtoupper(substr(trim((string) ($_POST['last_initial'] ?? '')), 0, 1));
        $display = trim((string) ($_POST['display_name'] ?? ''));
        $ranking = trim((string) ($_POST['current_ranking'] ?? ''));
        if ($display === '') {
            $display = default_player_display($first, $initial);
        }
        if ($display === '' || $display === 'Player') {
            flash('error', 'Player display name is required.');
            redirect('/teams/' . $teamId);
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO players (first_name, last_initial, display_name, current_ranking)
             VALUES (?, ?, ?, ?)'
        )->execute([$first, $initial, $display, $ranking]);
        $playerId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO team_roster (team_id, player_id) VALUES (?, ?)')->execute([$teamId, $playerId]);
        flash('success', 'Added ' . $display . ' to the roster.');
        redirect('/teams/' . $teamId);
    }

    public static function removePlayer(string $teamId, string $playerId): void
    {
        Auth::requireLogin();
        verify_csrf();
        $tid = (int) $teamId;
        $pid = (int) $playerId;
        if (!Auth::canManageRoster($tid)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        $team = find_team($tid);
        if ($team && (int) $team['captain_id'] === $pid) {
            flash('error', 'Assign a new captain before removing the current captain.');
            redirect('/teams/' . $tid);
        }
        $stmt = Database::pdo()->prepare(
            'SELECT p.* FROM players p
             JOIN team_roster tr ON tr.player_id = p.id
             WHERE p.id = ? AND tr.team_id = ?'
        );
        $stmt->execute([$pid, $tid]);
        $player = $stmt->fetch();
        if ($player) {
            Database::pdo()->prepare('DELETE FROM team_roster WHERE team_id = ? AND player_id = ?')->execute([$tid, $pid]);
            Database::pdo()->prepare('DELETE FROM players WHERE id = ?')->execute([$pid]);
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
        $stmt = Database::pdo()->prepare(
            'SELECT p.* FROM players p
             JOIN team_roster tr ON tr.player_id = p.id
             WHERE p.id = ? AND tr.team_id = ?'
        );
        $stmt->execute([$playerId, $teamId]);
        $player = $stmt->fetch();
        if (!$player) {
            flash('error', 'Could not assign captain.');
            redirect('/teams/' . $teamId);
        }
        Database::pdo()->prepare('UPDATE teams SET captain_id = ? WHERE id = ?')->execute([$playerId, $teamId]);
        flash('success', $player['display_name'] . ' is now captain.');
        redirect('/teams/' . $teamId);
    }

    public static function update(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $teamId = (int) $id;
        if (!Auth::canManageRoster($teamId)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        $display = trim((string) ($_POST['display_name'] ?? ''));
        if ($display === '') {
            flash('error', 'Team display name is required.');
            redirect('/teams/' . $teamId);
        }
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE teams SET display_name = ? WHERE id = ?')->execute([$display, $teamId]);
        $message = 'Updated team name to ' . $display . '.';

        if (Auth::isCommissioner() && isset($_POST['team_number'])) {
            $number = trim((string) $_POST['team_number']);
            $team = find_team($teamId);
            if ($team && $number !== '' && $number !== (string) $team['team_number']) {
                if (!ctype_digit($number)) {
                    flash('error', 'Team number must be a whole number.');
                    redirect('/teams/' . $teamId);
                }
                $number = (string) (int) $number;
                $pdo->beginTransaction();
                $other = $pdo->prepare('SELECT id, display_name FROM teams WHERE season_id = ? AND team_number = ? AND id != ?');
                $other->execute([$team['season_id'], $number, $teamId]);
                $otherTeam = $other->fetch();
                if ($otherTeam) {
                    $pdo->prepare('UPDATE teams SET team_number = ? WHERE id = ?')
                        ->execute([(string) $team['team_number'], (int) $otherTeam['id']]);
                }
                $pdo->prepare('UPDATE teams SET team_number = ? WHERE id = ?')->execute([$number, $teamId]);
                $pdo->commit();
                $message = $display . ' is now #' . $number . '.';
                if ($otherTeam) {
                    $message .= ' ' . $otherTeam['display_name'] . ' moved to #' . $team['team_number'] . '.';
                }
            }
        }

        flash('success', $message);
        redirect('/teams/' . $teamId);
    }

    public static function updatePlayer(string $teamId, string $playerId): void
    {
        Auth::requireLogin();
        verify_csrf();
        $tid = (int) $teamId;
        $pid = (int) $playerId;
        if (!Auth::canManageRoster($tid)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        $display = trim((string) ($_POST['display_name'] ?? ''));
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $initial = strtoupper(substr(trim((string) ($_POST['last_initial'] ?? '')), 0, 1));
        $ranking = trim((string) ($_POST['current_ranking'] ?? ''));
        if ($display === '') {
            flash('error', 'Display name is required.');
            redirect('/teams/' . $tid);
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE players SET first_name = ?, last_initial = ?, display_name = ?, current_ranking = ?
             WHERE id = ? AND id IN (SELECT player_id FROM team_roster WHERE team_id = ?)'
        );
        $stmt->execute([$first, $initial, $display, $ranking, $pid, $tid]);
        flash('success', 'Updated ' . $display . '.');
        redirect('/teams/' . $tid);
    }
}
