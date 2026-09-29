<?php

declare(strict_types=1);

final class ManageController
{
    public static function hub(): void
    {
        Auth::requireCommissioner();
        $season = Database::activeSeason();
        $teams = Database::pdo()->query(teams_with_captain_query())->fetchAll();

        $upcoming = [];
        if ($season) {
            $stmt = Database::pdo()->prepare(
                'SELECT g.*,
                        ht.display_name AS home_display_name,
                        ht.team_number AS home_team_number,
                        at.display_name AS away_display_name,
                        at.team_number AS away_team_number,
                        hp.display_name AS home_captain_display_name,
                        hp.current_ranking AS home_captain_ranking,
                        ap.display_name AS away_captain_display_name,
                        ap.current_ranking AS away_captain_ranking
                 FROM games g
                 JOIN teams ht ON ht.id = g.home_team_id
                 JOIN players hp ON hp.id = ht.captain_id
                 JOIN teams at ON at.id = g.away_team_id
                 JOIN players ap ON ap.id = at.captain_id
                 WHERE g.season_id = ? AND g.status = \'scheduled\' AND g.tipoff >= datetime(\'now\')
                 ORDER BY g.tipoff LIMIT 10'
            );
            $stmt->execute([(int) $season['id']]);
            $upcoming = array_map(static function (array $row): array {
                $home = [
                    'display_name' => $row['home_display_name'],
                    'team_number' => $row['home_team_number'],
                    'captain_display_name' => $row['home_captain_display_name'],
                    'captain_ranking' => $row['home_captain_ranking'],
                ];
                $away = [
                    'display_name' => $row['away_display_name'],
                    'team_number' => $row['away_team_number'],
                    'captain_display_name' => $row['away_captain_display_name'],
                    'captain_ranking' => $row['away_captain_ranking'],
                ];
                $row['home_abbrev'] = team_short($home);
                $row['away_abbrev'] = team_short($away);
                $row['home_name'] = team_label($home);
                $row['away_name'] = team_label($away);
                $homeSeed = null;
                $awaySeed = null;
                if (($row['phase'] ?? 'regular') === 'playoff') {
                    $seeds = Playoffs::seedsByTeamForGame((int) $row['id']);
                    $homeSeed = $seeds[(int) $row['home_team_id']] ?? null;
                    $awaySeed = $seeds[(int) $row['away_team_id']] ?? null;
                }
                return apply_matchup_display_order($row, $homeSeed, $awaySeed);
            }, $stmt->fetchAll());
        }

        render('manage', [
            'title' => 'Manage',
            'season' => $season,
            'teams' => $teams,
            'upcoming' => $upcoming,
            'playoffTournament' => $season
                ? Playoffs::findTournamentForSeason((int) $season['id'])
                : null,
            'standingsCount' => $season
                ? count(Standings::compute((int) $season['id']))
                : 0,
        ]);
    }

    public static function playoffCreateForm(): void
    {
        Auth::requireCommissioner();
        $season = Database::activeSeason();
        if (!$season) {
            flash('error', 'No active season.');
            redirect('/manage');
        }
        if (Playoffs::findTournamentForSeason((int) $season['id'])) {
            redirect('/manage/playoffs');
        }
        $standings = Standings::compute((int) $season['id']);
        $defaultSize = count($standings) >= 8 ? 8 : 4;
        render('manage_playoffs', [
            'title' => 'Start playoffs',
            'season' => $season,
            'standings' => $standings,
            'tournament' => null,
            'matchups' => [],
            'seeds' => [],
            'teams' => [],
            'canEditSeeds' => false,
            'openingMatchups' => self::openingMatchupPreview($standings, $defaultSize),
            'openingMatchupsBySize' => [
                4 => self::openingMatchupPreview($standings, 4),
                8 => self::openingMatchupPreview($standings, 8),
            ],
        ]);
    }

    public static function playoffManage(): void
    {
        Auth::requireCommissioner();
        $season = Database::activeSeason();
        if (!$season) {
            flash('error', 'No active season.');
            redirect('/manage');
        }
        $tournament = Playoffs::findTournamentForSeason((int) $season['id']);
        if (!$tournament) {
            redirect('/manage/playoffs/new');
        }
        $matchups = Playoffs::allMatchups((int) $tournament['id']);
        $canEditSeeds = true;
        foreach ($matchups as $m) {
            $g = $m['game'] ?? null;
            if ((int) ($m['round'] ?? 0) === 1 && $g && ($g['status'] ?? '') === 'final') {
                $canEditSeeds = false;
                break;
            }
        }
        $seeds = Playoffs::round1BySeed((int) $tournament['id']);
        render('manage_playoffs', [
            'title' => 'Manage playoffs',
            'season' => $season,
            'standings' => Standings::compute((int) $season['id']),
            'tournament' => $tournament,
            'matchups' => $matchups,
            'seeds' => $seeds,
            'teams' => Database::pdo()->query(teams_with_captain_query())->fetchAll(),
            'canEditSeeds' => $canEditSeeds,
            'openingMatchups' => [],
        ]);
    }

    public static function playoffCreate(): void
    {
        Auth::requireCommissioner();
        verify_csrf();
        $season = Database::activeSeason();
        if (!$season) {
            flash('error', 'No active season.');
            redirect('/manage');
        }
        $bracketSize = (int) ($_POST['bracket_size'] ?? 8);
        if (!in_array($bracketSize, [4, 8], true)) {
            flash('error', 'Choose a valid bracket size.');
            redirect('/manage/playoffs/new');
        }
        $tipoffs = self::parseTipoffList($_POST['tipoffs'] ?? [], $bracketSize / 2);
        if ($tipoffs === null) {
            flash('error', 'Set a tipoff for each first-round game.');
            redirect('/manage/playoffs/new');
        }
        try {
            Playoffs::createFromStandings(
                (int) $season['id'],
                $bracketSize,
                Standings::compute((int) $season['id']),
                $tipoffs,
                ''
            );
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/manage/playoffs/new');
        }
        flash('success', 'Playoff bracket created. Adjust seeds or tipoffs below if needed.');
        redirect('/manage/playoffs');
    }

    public static function playoffUpdateSeeds(): void
    {
        Auth::requireCommissioner();
        verify_csrf();
        $season = Database::activeSeason();
        $tournament = $season ? Playoffs::findTournamentForSeason((int) $season['id']) : null;
        if (!$tournament) {
            flash('error', 'No playoff tournament.');
            redirect('/manage');
        }
        $bracketSize = (int) $tournament['bracket_size'];
        $seedToTeam = [];
        $raw = $_POST['seed'] ?? [];
        if (!is_array($raw)) {
            flash('error', 'Invalid seeding.');
            redirect('/manage/playoffs');
        }
        for ($seed = 1; $seed <= $bracketSize; $seed++) {
            $seedToTeam[$seed] = (int) ($raw[$seed] ?? 0);
        }
        try {
            Playoffs::applySeeds((int) $tournament['id'], $seedToTeam);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/manage/playoffs');
        }
        flash('success', 'Playoff seeds updated.');
        redirect('/manage/playoffs');
    }

    public static function playoffUpdateGames(): void
    {
        Auth::requireCommissioner();
        verify_csrf();
        $season = Database::activeSeason();
        $tournament = $season ? Playoffs::findTournamentForSeason((int) $season['id']) : null;
        if (!$tournament) {
            flash('error', 'No playoff tournament.');
            redirect('/manage');
        }
        $rows = $_POST['matchups'] ?? [];
        if (!is_array($rows) || !$rows) {
            flash('error', 'No matchups to update.');
            redirect('/manage/playoffs');
        }
        $tz = new DateTimeZone((string) config('timezone'));
        try {
            foreach ($rows as $key => $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (!preg_match('/^(\d+)_(\d+)$/', (string) $key, $m)) {
                    continue;
                }
                $round = (int) $m[1];
                $matchupIndex = (int) $m[2];
                $tipoffLocal = trim((string) ($row['tipoff'] ?? ''));
                $tipoffUtc = null;
                if ($tipoffLocal !== '') {
                    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $tipoffLocal, $tz);
                    if (!$dt) {
                        throw new InvalidArgumentException('Invalid tipoff for Game ' . Playoffs::matchupLetter($round, $matchupIndex, (int) $tournament['bracket_size']) . '.');
                    }
                    $tipoffUtc = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                }
                // First-round games already exist — tipoff required.
                if ($round === 1 && $tipoffUtc === null) {
                    throw new InvalidArgumentException('First-round games need a tipoff.');
                }
                Playoffs::updateMatchupSchedule(
                    (int) $tournament['id'],
                    $round,
                    $matchupIndex,
                    $tipoffUtc,
                    ''
                );
            }
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/manage/playoffs');
        }
        flash('success', 'Playoff schedule updated.');
        redirect('/manage/playoffs');
    }

    /** @return list<array{label: string, high: int, low: int}> */
    private static function openingMatchupPreview(array $standings, int $bracketSize): array
    {
        if (!in_array($bracketSize, [4, 8], true) || count($standings) < $bracketSize) {
            return [];
        }
        $order = Playoffs::seedSlotOrder($bracketSize);
        $matchups = [];
        for ($i = 0; $i + 1 < count($order); $i += 2) {
            $seedA = $order[$i];
            $seedB = $order[$i + 1];
            $teamA = $standings[$seedA - 1]['team'] ?? null;
            $teamB = $standings[$seedB - 1]['team'] ?? null;
            if ($seedA <= $seedB) {
                $label = '#' . $seedA . ' ' . ($teamA ? team_label($teamA) : 'TBD')
                    . ' vs #' . $seedB . ' ' . ($teamB ? team_label($teamB) : 'TBD');
            } else {
                $label = '#' . $seedB . ' ' . ($teamB ? team_label($teamB) : 'TBD')
                    . ' vs #' . $seedA . ' ' . ($teamA ? team_label($teamA) : 'TBD');
            }
            $matchups[] = [
                'label' => $label,
                'high' => min($seedA, $seedB),
                'low' => max($seedA, $seedB),
            ];
        }
        return $matchups;
    }

    /** @return list<string>|null UTC tipoffs */
    private static function parseTipoffList(mixed $raw, int $expected): ?array
    {
        if (!is_array($raw) || count($raw) < $expected) {
            return null;
        }
        $tz = new DateTimeZone((string) config('timezone'));
        $out = [];
        for ($i = 0; $i < $expected; $i++) {
            $local = trim((string) ($raw[$i] ?? ''));
            $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $local, $tz);
            if (!$dt) {
                return null;
            }
            $out[] = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        return $out;
    }

    public static function gameCreateForm(): void
    {
        Auth::requireCommissioner();
        render('game_form', [
            'title' => 'Add game',
            'game' => null,
            'seasons' => Database::pdo()->query('SELECT * FROM seasons ORDER BY is_active DESC, name')->fetchAll(),
            'teams' => Database::pdo()->query(teams_with_captain_query())->fetchAll(),
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
            'INSERT INTO games (season_id, home_team_id, away_team_id, tipoff, location, status, phase)
             VALUES (?, ?, ?, ?, ?, ?, \'regular\')'
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
            'teams' => Database::pdo()->query(teams_with_captain_query())->fetchAll(),
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
            'location' => '',
            'status' => $status,
        ];
    }
}
