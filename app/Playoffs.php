<?php

declare(strict_types=1);

/** Single-elimination playoff helpers. */

final class Playoffs
{
    /** Standard seed order into opening-round slot indices (power of two). */
    public static function seedSlotOrder(int $bracketSize): array
    {
        return match ($bracketSize) {
            4 => [1, 4, 2, 3],
            8 => [1, 8, 4, 5, 2, 7, 3, 6],
            default => throw new InvalidArgumentException('Bracket size must be 4 or 8.'),
        };
    }

    public static function roundCount(int $bracketSize): int
    {
        return (int) log($bracketSize, 2);
    }

    public static function roundLabel(int $round, int $bracketSize): string
    {
        $total = self::roundCount($bracketSize);
        $fromEnd = $total - $round + 1;
        return match ($fromEnd) {
            1 => 'Final',
            2 => 'Semifinals',
            3 => 'Quarterfinals',
            default => 'Round ' . $round,
        };
    }

    /** Letters used by matchups in rounds before $round (0-based matchup indexing within a round). */
    public static function letterOffsetBeforeRound(int $round, int $bracketSize): int
    {
        $offset = 0;
        for ($r = 1; $r < $round; $r++) {
            $offset += (int) ($bracketSize / (2 ** $r));
        }
        return $offset;
    }

    public static function matchupLetter(int $round, int $matchupIndex, int $bracketSize): string
    {
        $n = self::letterOffsetBeforeRound($round, $bracketSize) + $matchupIndex;
        if ($n < 0 || $n > 25) {
            return (string) ($n + 1);
        }
        return chr(ord('A') + $n);
    }

    /** @return array{0: string, 1: string}|null Letters of the two prior matchups that feed this one. */
    public static function feederLetters(int $round, int $matchupIndex, int $bracketSize): ?array
    {
        if ($round <= 1) {
            return null;
        }
        return [
            self::matchupLetter($round - 1, $matchupIndex * 2, $bracketSize),
            self::matchupLetter($round - 1, $matchupIndex * 2 + 1, $bracketSize),
        ];
    }

    public static function sideLabel(array $slot, ?string $feederLetter): string
    {
        if (!empty($slot['team_id'])) {
            return team_label([
                'display_name' => $slot['team_display_name'] ?? null,
                'team_number' => $slot['team_number'] ?? null,
            ]);
        }
        if ($feederLetter !== null && $feederLetter !== '') {
            return 'Winner ' . $feederLetter;
        }
        return 'TBD';
    }

    public static function findTournamentForSeason(int $seasonId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM playoff_tournaments WHERE season_id = ?');
        $stmt->execute([$seasonId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findTournament(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM playoff_tournaments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array> */
    public static function slotsForTournament(int $tournamentId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT s.*,
                    t.display_name AS team_display_name,
                    t.team_number AS team_number,
                    t.color AS team_color
             FROM playoff_slots s
             LEFT JOIN teams t ON t.id = s.team_id
             WHERE s.tournament_id = ?
             ORDER BY s.round, s.slot_index'
        );
        $stmt->execute([$tournamentId]);
        return $stmt->fetchAll();
    }

    /**
     * Create tournament + empty bracket, seed round 1 from standings, create first-round games.
     *
     * @param list<array> $standingsRows from Standings::compute()
     * @param string|list<string> $firstTipoffUtc One tipoff for all, or one per first-round matchup
     */
    public static function createFromStandings(
        int $seasonId,
        int $bracketSize,
        array $standingsRows,
        string|array $firstTipoffUtc,
        string $location = ''
    ): array {
        if (!in_array($bracketSize, [4, 8], true)) {
            throw new InvalidArgumentException('Bracket size must be 4 or 8.');
        }
        if (count($standingsRows) < $bracketSize) {
            throw new RuntimeException('Need at least ' . $bracketSize . ' teams in standings.');
        }
        if (self::findTournamentForSeason($seasonId)) {
            throw new RuntimeException('A playoff tournament already exists for this season.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO playoff_tournaments (season_id, bracket_size, status) VALUES (?, ?, \'in_progress\')'
            )->execute([$seasonId, $bracketSize]);
            $tournamentId = (int) $pdo->lastInsertId();

            $roundCount = self::roundCount($bracketSize);
            /** @var array<string, int> $slotIds round:index => id */
            $slotIds = [];
            $ins = $pdo->prepare(
                'INSERT INTO playoff_slots (tournament_id, round, slot_index, team_id, seed, game_id, feeds_slot_id)
                 VALUES (?, ?, ?, NULL, NULL, NULL, NULL)'
            );
            for ($round = 1; $round <= $roundCount; $round++) {
                $slotsInRound = (int) ($bracketSize / (2 ** ($round - 1)));
                for ($i = 0; $i < $slotsInRound; $i++) {
                    $ins->execute([$tournamentId, $round, $i]);
                    $slotIds[$round . ':' . $i] = (int) $pdo->lastInsertId();
                }
            }

            $updFeed = $pdo->prepare('UPDATE playoff_slots SET feeds_slot_id = ? WHERE id = ?');
            for ($round = 1; $round < $roundCount; $round++) {
                $slotsInRound = (int) ($bracketSize / (2 ** ($round - 1)));
                for ($i = 0; $i < $slotsInRound; $i++) {
                    $nextIndex = intdiv($i, 2);
                    $updFeed->execute([
                        $slotIds[($round + 1) . ':' . $nextIndex],
                        $slotIds[$round . ':' . $i],
                    ]);
                }
            }

            $seedOrder = self::seedSlotOrder($bracketSize);
            $updTeam = $pdo->prepare('UPDATE playoff_slots SET team_id = ?, seed = ? WHERE id = ?');
            foreach ($seedOrder as $slotIndex => $seed) {
                $team = $standingsRows[$seed - 1]['team'];
                $updTeam->execute([(int) $team['id'], $seed, $slotIds['1:' . $slotIndex]]);
            }

            self::createGamesForRound($tournamentId, 1, $seasonId, $firstTipoffUtc, $location);

            $pdo->prepare("UPDATE seasons SET status = 'playoffs' WHERE id = ?")->execute([$seasonId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return self::findTournament($tournamentId);
    }

    /**
     * @param string|list<string> $tipoffUtc One tipoff for all matchups, or one per matchup index.
     */
    public static function createGamesForRound(
        int $tournamentId,
        int $round,
        int $seasonId,
        string|array $tipoffUtc,
        string $location = ''
    ): int {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM playoff_slots WHERE tournament_id = ? AND round = ? ORDER BY slot_index'
        );
        $stmt->execute([$tournamentId, $round]);
        $slots = $stmt->fetchAll();
        $created = 0;
        $insGame = $pdo->prepare(
            'INSERT INTO games (season_id, home_team_id, away_team_id, tipoff, location, status, phase)
             VALUES (?, ?, ?, ?, ?, \'scheduled\', \'playoff\')'
        );
        $link = $pdo->prepare('UPDATE playoff_slots SET game_id = ? WHERE id IN (?, ?)');

        for ($i = 0; $i + 1 < count($slots); $i += 2) {
            $a = $slots[$i];
            $b = $slots[$i + 1];
            if (!empty($a['game_id']) || !empty($b['game_id'])) {
                continue;
            }
            if (empty($a['team_id']) || empty($b['team_id'])) {
                continue;
            }
            $seedA = (int) ($a['seed'] ?? PHP_INT_MAX);
            $seedB = (int) ($b['seed'] ?? PHP_INT_MAX);
            // Better seed (lower number) is home; if unknown, first slot is home.
            if ($seedA <= $seedB) {
                $homeId = (int) $a['team_id'];
                $awayId = (int) $b['team_id'];
            } else {
                $homeId = (int) $b['team_id'];
                $awayId = (int) $a['team_id'];
            }
            $matchupIndex = intdiv($i, 2);
            $planned = trim((string) ($a['planned_tipoff'] ?? $b['planned_tipoff'] ?? ''));
            if ($planned !== '') {
                $tip = $planned;
            } else {
                $tip = is_array($tipoffUtc)
                    ? (string) ($tipoffUtc[$matchupIndex] ?? $tipoffUtc[0] ?? '')
                    : $tipoffUtc;
            }
            if ($tip === '') {
                throw new InvalidArgumentException('Missing tipoff for matchup ' . ($matchupIndex + 1) . '.');
            }
            $loc = trim((string) ($a['planned_location'] ?? $b['planned_location'] ?? ''));
            if ($loc === '') {
                $loc = $location;
            }
            $insGame->execute([$seasonId, $homeId, $awayId, $tip, $loc]);
            $gameId = (int) $pdo->lastInsertId();
            $link->execute([$gameId, (int) $a['id'], (int) $b['id']]);
            $created++;
        }
        return $created;
    }

    /** @return list<array> Playoff games for a tournament, ordered by tipoff. */
    public static function gamesForTournament(int $tournamentId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT g.*,
                    ht.display_name AS home_display_name,
                    ht.team_number AS home_team_number,
                    at.display_name AS away_display_name,
                    at.team_number AS away_team_number,
                    (SELECT MIN(s.round) FROM playoff_slots s
                     WHERE s.game_id = g.id AND s.tournament_id = ?) AS round
             FROM games g
             JOIN teams ht ON ht.id = g.home_team_id
             JOIN teams at ON at.id = g.away_team_id
             WHERE g.phase = \'playoff\'
               AND g.id IN (
                 SELECT DISTINCT game_id FROM playoff_slots
                 WHERE tournament_id = ? AND game_id IS NOT NULL
               )
             ORDER BY round, g.tipoff, g.id'
        );
        $stmt->execute([$tournamentId, $tournamentId]);
        return $stmt->fetchAll();
    }

    /** Round-1 slots keyed by seed (1..N). */
    public static function round1BySeed(int $tournamentId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT s.*,
                    t.display_name AS team_display_name,
                    t.team_number AS team_number
             FROM playoff_slots s
             LEFT JOIN teams t ON t.id = s.team_id
             WHERE s.tournament_id = ? AND s.round = 1
             ORDER BY s.seed'
        );
        $stmt->execute([$tournamentId]);
        $bySeed = [];
        foreach ($stmt->fetchAll() as $slot) {
            if ($slot['seed'] !== null) {
                $bySeed[(int) $slot['seed']] = $slot;
            }
        }
        return $bySeed;
    }

    /**
     * Reassign round-1 seeds/teams and sync scheduled first-round games.
     * Blocked once any round-1 game is final.
     *
     * @param array<int, int> $seedToTeamId seed number => team id
     */
    public static function applySeeds(int $tournamentId, array $seedToTeamId): void
    {
        $tournament = self::findTournament($tournamentId);
        if (!$tournament || $tournament['status'] !== 'in_progress') {
            throw new RuntimeException('Playoffs are not editable.');
        }
        $bracketSize = (int) $tournament['bracket_size'];
        if (count($seedToTeamId) !== $bracketSize) {
            throw new InvalidArgumentException('Provide a team for every seed.');
        }
        $teamIds = array_values($seedToTeamId);
        if (count(array_unique($teamIds)) !== $bracketSize) {
            throw new InvalidArgumentException('Each team can only be seeded once.');
        }
        for ($seed = 1; $seed <= $bracketSize; $seed++) {
            if (empty($seedToTeamId[$seed])) {
                throw new InvalidArgumentException('Missing team for seed ' . $seed . '.');
            }
        }

        $pdo = Database::pdo();
        $finalCheck = $pdo->prepare(
            'SELECT COUNT(*) FROM games g
             JOIN playoff_slots s ON s.game_id = g.id
             WHERE s.tournament_id = ? AND s.round = 1 AND g.status = \'final\''
        );
        $finalCheck->execute([$tournamentId]);
        if ((int) $finalCheck->fetchColumn() > 0) {
            throw new RuntimeException('Cannot change seeds after a first-round game is final.');
        }

        $seedOrder = self::seedSlotOrder($bracketSize);
        $pdo->beginTransaction();
        try {
            $updSlot = $pdo->prepare(
                'UPDATE playoff_slots SET team_id = ?, seed = ? WHERE tournament_id = ? AND round = 1 AND slot_index = ?'
            );
            foreach ($seedOrder as $slotIndex => $seed) {
                $updSlot->execute([(int) $seedToTeamId[$seed], $seed, $tournamentId, $slotIndex]);
            }

            $slotsStmt = $pdo->prepare(
                'SELECT * FROM playoff_slots WHERE tournament_id = ? AND round = 1 ORDER BY slot_index'
            );
            $slotsStmt->execute([$tournamentId]);
            $slots = $slotsStmt->fetchAll();
            $updGame = $pdo->prepare(
                'UPDATE games SET home_team_id = ?, away_team_id = ? WHERE id = ? AND status = \'scheduled\''
            );
            for ($i = 0; $i + 1 < count($slots); $i += 2) {
                $a = $slots[$i];
                $b = $slots[$i + 1];
                $gameId = (int) ($a['game_id'] ?? $b['game_id'] ?? 0);
                if (!$gameId) {
                    continue;
                }
                $seedA = (int) ($a['seed'] ?? PHP_INT_MAX);
                $seedB = (int) ($b['seed'] ?? PHP_INT_MAX);
                if ($seedA <= $seedB) {
                    $homeId = (int) $a['team_id'];
                    $awayId = (int) $b['team_id'];
                } else {
                    $homeId = (int) $b['team_id'];
                    $awayId = (int) $a['team_id'];
                }
                $updGame->execute([$homeId, $awayId, $gameId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Update tipoff/location for a playoff game in this tournament. */
    public static function updateGameSchedule(
        int $tournamentId,
        int $gameId,
        string $tipoffUtc,
        string $location
    ): void {
        $check = Database::pdo()->prepare(
            'SELECT g.id FROM games g
             JOIN playoff_slots s ON s.game_id = g.id
             WHERE g.id = ? AND s.tournament_id = ? AND g.phase = \'playoff\'
             LIMIT 1'
        );
        $check->execute([$gameId, $tournamentId]);
        if (!$check->fetch()) {
            throw new RuntimeException('Playoff game not found.');
        }
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE games SET tipoff = ?, location = ? WHERE id = ?'
        )->execute([$tipoffUtc, $location, $gameId]);
        $pdo->prepare(
            'UPDATE playoff_slots SET planned_tipoff = ?, planned_location = ? WHERE game_id = ? AND tournament_id = ?'
        )->execute([$tipoffUtc, $location, $gameId, $tournamentId]);
    }

    /**
     * Set planned tipoff for a bracket matchup (by round + matchup index).
     * Also updates the linked game when one exists.
     */
    public static function updateMatchupSchedule(
        int $tournamentId,
        int $round,
        int $matchupIndex,
        ?string $tipoffUtc,
        string $location
    ): void {
        $tournament = self::findTournament($tournamentId);
        if (!$tournament) {
            throw new RuntimeException('Tournament not found.');
        }
        $slotA = $matchupIndex * 2;
        $slotB = $slotA + 1;
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM playoff_slots WHERE tournament_id = ? AND round = ? AND slot_index IN (?, ?) ORDER BY slot_index'
        );
        $stmt->execute([$tournamentId, $round, $slotA, $slotB]);
        $slots = $stmt->fetchAll();
        if (count($slots) < 2) {
            throw new RuntimeException('Matchup not found.');
        }
        $pdo->prepare(
            'UPDATE playoff_slots SET planned_tipoff = ?, planned_location = ?
             WHERE tournament_id = ? AND round = ? AND slot_index IN (?, ?)'
        )->execute([$tipoffUtc, $location, $tournamentId, $round, $slotA, $slotB]);

        $gameId = (int) ($slots[0]['game_id'] ?? $slots[1]['game_id'] ?? 0);
        if ($gameId && $tipoffUtc !== null && $tipoffUtc !== '') {
            $pdo->prepare(
                'UPDATE games SET tipoff = ?, location = ? WHERE id = ? AND status = \'scheduled\''
            )->execute([$tipoffUtc, $location, $gameId]);
        }
    }

    /**
     * Enrich bracket matchups with letters, feeder placeholders, and effective tipoff.
     *
     * @param list<array> $matchups from matchupsForRound
     * @return list<array>
     */
    public static function enrichMatchups(array $matchups, int $round, int $bracketSize, array $gamesById = []): array
    {
        foreach ($matchups as $i => &$m) {
            $letter = self::matchupLetter($round, $i, $bracketSize);
            $feeders = self::feederLetters($round, $i, $bracketSize);
            $a = $m['slot_a'];
            $b = $m['slot_b'];
            $game = null;
            if (!empty($m['game_id'])) {
                $gid = (int) $m['game_id'];
                $game = $gamesById[$gid] ?? null;
            }
            $plannedTip = trim((string) ($a['planned_tipoff'] ?? $b['planned_tipoff'] ?? ''));
            $plannedLoc = (string) ($a['planned_location'] ?? $b['planned_location'] ?? '');
            $tipoff = $game['tipoff'] ?? ($plannedTip !== '' ? $plannedTip : null);
            $location = $game['location'] ?? $plannedLoc;
            $m['letter'] = $letter;
            $m['feeder_letters'] = $feeders;
            $m['game'] = $game;
            $m['tipoff'] = $tipoff;
            $m['location'] = $location;
            $m['label_a'] = self::sideLabel($a, $feeders[0] ?? null);
            $m['label_b'] = self::sideLabel($b, $feeders[1] ?? null);
            $m['round'] = $round;
            $m['matchup_index'] = $i;
        }
        unset($m);
        return $matchups;
    }

    /** Sort matchups by tipoff when known; undated matchups keep bracket order at the end. */
    public static function sortMatchupsByTipoff(array $matchups): array
    {
        $indexed = array_values($matchups);
        usort($indexed, static function (array $a, array $b): int {
            $tipA = $a['tipoff'] ?? $a['game']['tipoff'] ?? null;
            $tipB = $b['tipoff'] ?? $b['game']['tipoff'] ?? null;
            if ($tipA === null && $tipB === null) {
                return ($a['matchup_index'] ?? 0) <=> ($b['matchup_index'] ?? 0);
            }
            if ($tipA === null) {
                return 1;
            }
            if ($tipB === null) {
                return -1;
            }
            $cmp = strcmp((string) $tipA, (string) $tipB);
            if ($cmp !== 0) {
                return $cmp;
            }
            return ($a['matchup_index'] ?? 0) <=> ($b['matchup_index'] ?? 0);
        });
        return $indexed;
    }

    /** All tournament matchups (including TBD later rounds) for manage UI. */
    public static function allMatchups(int $tournamentId): array
    {
        $tournament = self::findTournament($tournamentId);
        if (!$tournament) {
            return [];
        }
        $bracketSize = (int) $tournament['bracket_size'];
        $byRound = self::bracketByRound($tournamentId);
        $gameIds = [];
        foreach ($byRound as $slots) {
            foreach ($slots as $slot) {
                if (!empty($slot['game_id'])) {
                    $gameIds[(int) $slot['game_id']] = true;
                }
            }
        }
        $gamesById = [];
        if ($gameIds) {
            $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
            $stmt = Database::pdo()->prepare(
                "SELECT * FROM games WHERE id IN ($placeholders)"
            );
            $stmt->execute(array_keys($gameIds));
            foreach ($stmt->fetchAll() as $row) {
                $gamesById[(int) $row['id']] = $row;
            }
        }
        $out = [];
        foreach ($byRound as $roundNum => $slots) {
            $matchups = self::matchupsForRound($slots);
            $enriched = self::enrichMatchups($matchups, (int) $roundNum, $bracketSize, $gamesById);
            foreach ($enriched as $m) {
                $m['round_label'] = self::roundLabel((int) $roundNum, $bracketSize);
                $out[] = $m;
            }
        }
        return $out;
    }

    /** After a playoff game is finalized, advance the winner and maybe create the next game. */
    public static function advanceFromGame(int $gameId): void
    {
        $pdo = Database::pdo();
        $gameStmt = $pdo->prepare(
            'SELECT g.*, r.home_score, r.away_score
             FROM games g
             JOIN results r ON r.game_id = g.id
             WHERE g.id = ? AND g.phase = \'playoff\' AND g.status = \'final\''
        );
        $gameStmt->execute([$gameId]);
        $game = $gameStmt->fetch();
        if (!$game) {
            return;
        }

        $winnerId = ((int) $game['home_score'] > (int) $game['away_score'])
            ? (int) $game['home_team_id']
            : (int) $game['away_team_id'];

        $slotsStmt = $pdo->prepare('SELECT * FROM playoff_slots WHERE game_id = ? ORDER BY slot_index');
        $slotsStmt->execute([$gameId]);
        $slots = $slotsStmt->fetchAll();
        if (count($slots) < 2) {
            return;
        }

        $feedsId = $slots[0]['feeds_slot_id'] !== null ? (int) $slots[0]['feeds_slot_id'] : null;
        $tournamentId = (int) $slots[0]['tournament_id'];
        $tournament = self::findTournament($tournamentId);
        if (!$tournament) {
            return;
        }

        // Carry the better (lower) seed forward when known.
        $winnerSeed = null;
        foreach ($slots as $slot) {
            if ((int) ($slot['team_id'] ?? 0) === $winnerId && $slot['seed'] !== null) {
                $winnerSeed = (int) $slot['seed'];
                break;
            }
        }

        if ($feedsId === null) {
            // Final game — tournament complete.
            $pdo->prepare(
                'UPDATE playoff_tournaments SET status = \'complete\' WHERE id = ?'
            )->execute([$tournamentId]);
            $pdo->prepare(
                'UPDATE seasons SET champion_team_id = ? WHERE id = ?'
            )->execute([$winnerId, (int) $tournament['season_id']]);
            return;
        }

        $pdo->prepare(
            'UPDATE playoff_slots SET team_id = ?, seed = COALESCE(?, seed) WHERE id = ?'
        )->execute([$winnerId, $winnerSeed, $feedsId]);

        $next = $pdo->prepare('SELECT * FROM playoff_slots WHERE id = ?');
        $next->execute([$feedsId]);
        $nextSlot = $next->fetch();
        if (!$nextSlot) {
            return;
        }

        $round = (int) $nextSlot['round'];
        $pairIndex = (int) $nextSlot['slot_index'];
        $mateIndex = $pairIndex % 2 === 0 ? $pairIndex + 1 : $pairIndex - 1;
        $mateStmt = $pdo->prepare(
            'SELECT * FROM playoff_slots WHERE tournament_id = ? AND round = ? AND slot_index = ?'
        );
        $mateStmt->execute([$tournamentId, $round, $mateIndex]);
        $mate = $mateStmt->fetch();
        if (!$mate || empty($mate['team_id']) || empty($nextSlot['team_id'])) {
            return;
        }
        if (!empty($nextSlot['game_id']) || !empty($mate['game_id'])) {
            return;
        }

        $planned = trim((string) ($nextSlot['planned_tipoff'] ?? $mate['planned_tipoff'] ?? ''));
        if ($planned !== '') {
            $tipoff = $planned;
        } else {
            // Default tipoff: 7 days after this game, same clock time.
            $tipoff = (new DateTimeImmutable($game['tipoff'], new DateTimeZone('UTC')))
                ->modify('+7 days')
                ->format('Y-m-d H:i:s');
        }
        $plannedLoc = trim((string) ($nextSlot['planned_location'] ?? $mate['planned_location'] ?? ''));
        self::createGamesForRound(
            $tournamentId,
            $round,
            (int) $tournament['season_id'],
            $tipoff,
            $plannedLoc !== '' ? $plannedLoc : (string) ($game['location'] ?? '')
        );
    }

    /** @return array<int, list<array>> */
    public static function bracketByRound(int $tournamentId): array
    {
        $slots = self::slotsForTournament($tournamentId);
        $byRound = [];
        foreach ($slots as $slot) {
            $byRound[(int) $slot['round']][] = $slot;
        }
        return $byRound;
    }

    /** Pair slots into matchups for display. */
    public static function matchupsForRound(array $roundSlots): array
    {
        $matchups = [];
        for ($i = 0; $i + 1 < count($roundSlots); $i += 2) {
            $matchups[] = [
                'slot_a' => $roundSlots[$i],
                'slot_b' => $roundSlots[$i + 1],
                'game_id' => $roundSlots[$i]['game_id'] ?? $roundSlots[$i + 1]['game_id'] ?? null,
            ];
        }
        return $matchups;
    }
}
