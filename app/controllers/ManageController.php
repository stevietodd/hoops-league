<?php

declare(strict_types=1);

final class ManageController
{
    public static function hub(): void
    {
        Auth::requireCommissioner();
        $season = Database::activeSeason();
        $teams = Database::pdo()->query('SELECT * FROM teams ORDER BY CAST(abbrev AS INTEGER), name')->fetchAll();
        foreach ($teams as &$team) {
            $p = Database::pdo()->prepare(
                'SELECT display_name FROM players WHERE team_id = ? AND is_captain = 1 LIMIT 1'
            );
            $p->execute([(int) $team['id']]);
            $cap = $p->fetch();
            $team['captain_name'] = $cap['display_name'] ?? null;
        }
        unset($team);

        $upcoming = [];
        if ($season) {
            $stmt = Database::pdo()->prepare(
                'SELECT g.*, ht.abbrev AS home_abbrev, at.abbrev AS away_abbrev
                 FROM games g
                 JOIN teams ht ON ht.id = g.home_team_id
                 JOIN teams at ON at.id = g.away_team_id
                 WHERE g.season_id = ? AND g.status = \'scheduled\' AND g.tipoff >= datetime(\'now\')
                 ORDER BY g.tipoff LIMIT 10'
            );
            $stmt->execute([(int) $season['id']]);
            $upcoming = $stmt->fetchAll();
        }

        render('manage', [
            'title' => 'Manage',
            'season' => $season,
            'teams' => $teams,
            'upcoming' => $upcoming,
        ]);
    }

    public static function gameCreateForm(): void
    {
        Auth::requireCommissioner();
        render('game_form', [
            'title' => 'Add game',
            'game' => null,
            'seasons' => Database::pdo()->query('SELECT * FROM seasons ORDER BY is_active DESC, name')->fetchAll(),
            'teams' => Database::pdo()->query('SELECT * FROM teams ORDER BY CAST(abbrev AS INTEGER), name')->fetchAll(),
            'action' => url('/manage/games/new'),
        ]);
    }

    public static function gameCreate(): void
    {
        Auth::requireCommissioner();
        verify_csrf();
        $data = self::parseGamePost();
        if ($data === null) {
            redirect('/manage/games/new');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO games (season_id, home_team_id, away_team_id, tipoff, location, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['season_id'],
            $data['home_team_id'],
            $data['away_team_id'],
            $data['tipoff'],
            $data['location'],
            $data['status'],
        ]);
        flash('success', 'Game added to the schedule.');
        redirect('/games/' . Database::pdo()->lastInsertId());
    }

    public static function gameEditForm(string $id): void
    {
        Auth::requireCommissioner();
        $game = ScheduleController::findGame((int) $id);
        if (!$game) {
            http_response_code(404);
            render('errors/404', ['title' => 'Not found']);
            return;
        }
        render('game_form', [
            'title' => 'Edit game',
            'game' => $game,
            'seasons' => Database::pdo()->query('SELECT * FROM seasons ORDER BY is_active DESC, name')->fetchAll(),
            'teams' => Database::pdo()->query('SELECT * FROM teams ORDER BY CAST(abbrev AS INTEGER), name')->fetchAll(),
            'action' => url('/manage/games/' . $id . '/edit'),
        ]);
    }

    public static function gameEdit(string $id): void
    {
        Auth::requireCommissioner();
        verify_csrf();
        $data = self::parseGamePost();
        if ($data === null) {
            redirect('/manage/games/' . $id . '/edit');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE games SET season_id = ?, home_team_id = ?, away_team_id = ?, tipoff = ?, location = ?, status = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['season_id'],
            $data['home_team_id'],
            $data['away_team_id'],
            $data['tipoff'],
            $data['location'],
            $data['status'],
            (int) $id,
        ]);
        flash('success', 'Game updated.');
        redirect('/games/' . $id);
    }

    public static function users(): void
    {
        Auth::requireAdmin();
        $users = Database::pdo()->query(
            'SELECT * FROM users ORDER BY is_admin DESC, is_commissioner DESC, email'
        )->fetchAll();
        render('manage_users', ['title' => 'Users', 'users' => $users]);
    }

    public static function toggleCommissioner(string $id): void
    {
        Auth::requireAdmin();
        verify_csrf();
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int) $id]);
        $user = $stmt->fetch();
        if (!$user) {
            flash('error', 'User not found.');
            redirect('/manage/users');
        }
        if (!empty($user['is_admin'])) {
            flash('error', 'Admin roles are managed separately.');
            redirect('/manage/users');
        }
        $next = empty($user['is_commissioner']) ? 1 : 0;
        Database::pdo()->prepare('UPDATE users SET is_commissioner = ? WHERE id = ?')->execute([$next, (int) $id]);
        flash('success', $next ? $user['email'] . ' is now a commissioner.' : $user['email'] . ' is no longer a commissioner.');
        redirect('/manage/users');
    }

    private static function parseGamePost(): ?array
    {
        $seasonId = (int) ($_POST['season_id'] ?? 0);
        $homeId = (int) ($_POST['home_team_id'] ?? 0);
        $awayId = (int) ($_POST['away_team_id'] ?? 0);
        $tipoffLocal = trim((string) ($_POST['tipoff'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $status = ($_POST['status'] ?? 'scheduled') === 'final' ? 'final' : 'scheduled';

        if (!$seasonId || !$homeId || !$awayId || $homeId === $awayId || $tipoffLocal === '') {
            flash('error', 'Please fill out the game form correctly.');
            return null;
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $tipoffLocal, new DateTimeZone(config('timezone')));
        if (!$dt) {
            flash('error', 'Invalid tipoff time.');
            return null;
        }

        return [
            'season_id' => $seasonId,
            'home_team_id' => $homeId,
            'away_team_id' => $awayId,
            'tipoff' => $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'location' => $location,
            'status' => $status,
        ];
    }
}
