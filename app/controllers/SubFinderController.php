<?php

declare(strict_types=1);

final class SubFinderController
{
    public static function index(): void
    {
        $teamId = isset($_GET['team']) && $_GET['team'] !== '' ? (int) $_GET['team'] : null;
        $playerId = isset($_GET['player']) && $_GET['player'] !== '' ? (int) $_GET['player'] : null;
        $gameId = isset($_GET['game']) && $_GET['game'] !== '' ? (int) $_GET['game'] : null;

        $teams = Database::pdo()->query(teams_with_captain_query())->fetchAll();
        $team = $teamId ? find_team($teamId) : null;
        $player = null;
        $players = [];
        $games = [];
        $game = null;
        $suggestions = [];

        if ($team) {
            $players = self::roster((int) $team['id']);
            if ($playerId) {
                foreach ($players as $p) {
                    if ((int) $p['id'] === $playerId) {
                        $player = $p;
                        break;
                    }
                }
            }
            if (!$player) {
                $playerId = null;
                $gameId = null;
            }
        } else {
            $teamId = null;
            $playerId = null;
            $gameId = null;
        }

        if ($team && $player) {
            $games = self::teamGames((int) $team['id']);
            if ($gameId) {
                foreach ($games as $g) {
                    if ((int) $g['id'] === $gameId) {
                        $game = $g;
                        break;
                    }
                }
            }
            if (!$game) {
                $gameId = null;
            }
        }

        if ($team && $player && $game) {
            $suggestions = self::findSubs($team, $player, $game);
        }

        $step = 'team';
        if ($team && !$player) {
            $step = 'player';
        } elseif ($team && $player && !$game) {
            $step = 'game';
        } elseif ($team && $player && $game) {
            $step = 'results';
        }

        render('subs', [
            'title' => 'Sub Finder',
            'step' => $step,
            'teams' => $teams,
            'team' => $team,
            'players' => $players,
            'player' => $player,
            'games' => $games,
            'game' => $game,
            'suggestions' => $suggestions,
            'showRankings' => Auth::canSeeRankings(),
            'selectedTeamId' => $teamId,
            'selectedPlayerId' => $playerId,
            'selectedGameId' => $gameId,
        ]);
    }

    /** @return list<array> */
    private static function roster(int $teamId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*
             FROM team_roster tr
             JOIN players p ON p.id = tr.player_id
             WHERE tr.team_id = ?
             ORDER BY p.display_name'
        );
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }

    /** @return list<array> */
    private static function teamGames(int $teamId): array
    {
        $season = Database::activeSeason();
        if (!$season) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            ScheduleController::gameSelectSql() . '
             WHERE g.season_id = ?
               AND (g.home_team_id = ? OR g.away_team_id = ?)
             ORDER BY g.tipoff'
        );
        $stmt->execute([(int) $season['id'], $teamId, $teamId]);
        return array_map([ScheduleController::class, 'hydrateGamePublic'], $stmt->fetchAll());
    }

    /**
     * @param array $team
     * @param array $missing
     * @param array $game
     * @return list<array>
     */
    private static function findSubs(array $team, array $missing, array $game): array
    {
        $missingRank = self::rankingValue($missing['current_ranking'] ?? '');
        if ($missingRank === null) {
            return [];
        }

        $pdo = Database::pdo();
        $busy = $pdo->prepare(
            'SELECT home_team_id AS team_id FROM games WHERE tipoff = ?
             UNION
             SELECT away_team_id AS team_id FROM games WHERE tipoff = ?'
        );
        $busy->execute([$game['tipoff'], $game['tipoff']]);
        $busyTeamIds = array_map('intval', $busy->fetchAll(PDO::FETCH_COLUMN));
        $busyTeamIds[] = (int) $team['id'];
        $busyTeamIds = array_values(array_unique($busyTeamIds));

        $placeholders = implode(',', array_fill(0, count($busyTeamIds), '?'));
        $sql = "SELECT p.*,
                       t.id AS team_id,
                       t.display_name AS team_display_name,
                       t.team_number AS team_number
                FROM players p
                JOIN team_roster tr ON tr.player_id = p.id
                JOIN teams t ON t.id = tr.team_id
                WHERE tr.team_id NOT IN ({$placeholders})
                  AND p.current_ranking != ''
                  AND CAST(p.current_ranking AS REAL) <= ?
                ORDER BY CAST(p.current_ranking AS REAL) DESC, p.display_name";
        $params = $busyTeamIds;
        $params[] = $missingRank;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function rankingValue(string $ranking): ?float
    {
        $ranking = trim($ranking);
        if ($ranking === '' || !is_numeric($ranking)) {
            return null;
        }
        return (float) $ranking;
    }
}
