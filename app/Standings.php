<?php

declare(strict_types=1);

final class Standings
{
    public static function compute(?int $seasonId = null): array
    {
        $pdo = Database::pdo();
        $teams = $pdo->query('SELECT * FROM teams ORDER BY CAST(abbrev AS INTEGER), name')->fetchAll();
        $rows = [];
        foreach ($teams as $team) {
            $rows[(int) $team['id']] = [
                'team' => $team,
                'wins' => 0,
                'losses' => 0,
                'points_for' => 0,
                'points_against' => 0,
            ];
        }

        if ($seasonId === null) {
            $season = Database::activeSeason();
            $seasonId = $season ? (int) $season['id'] : null;
        }
        if ($seasonId === null) {
            return array_values($rows);
        }

        $stmt = $pdo->prepare(
            'SELECT g.home_team_id, g.away_team_id, r.home_score, r.away_score
             FROM games g
             INNER JOIN results r ON r.game_id = g.id
             WHERE g.season_id = ? AND g.status = \'final\''
        );
        $stmt->execute([$seasonId]);
        foreach ($stmt->fetchAll() as $game) {
            $homeId = (int) $game['home_team_id'];
            $awayId = (int) $game['away_team_id'];
            if (!isset($rows[$homeId], $rows[$awayId])) {
                continue;
            }
            $homeScore = (int) $game['home_score'];
            $awayScore = (int) $game['away_score'];
            $rows[$homeId]['points_for'] += $homeScore;
            $rows[$homeId]['points_against'] += $awayScore;
            $rows[$awayId]['points_for'] += $awayScore;
            $rows[$awayId]['points_against'] += $homeScore;
            if ($homeScore > $awayScore) {
                $rows[$homeId]['wins']++;
                $rows[$awayId]['losses']++;
            } else {
                $rows[$awayId]['wins']++;
                $rows[$homeId]['losses']++;
            }
        }

        $list = array_values($rows);
        usort($list, static function (array $a, array $b): int {
            $aPlayed = $a['wins'] + $a['losses'];
            $bPlayed = $b['wins'] + $b['losses'];
            $aPct = $aPlayed ? $a['wins'] / $aPlayed : 0.0;
            $bPct = $bPlayed ? $b['wins'] / $bPlayed : 0.0;
            $aDiff = $a['points_for'] - $a['points_against'];
            $bDiff = $b['points_for'] - $b['points_against'];
            return [$bPct, $bDiff, $b['points_for'], $a['team']['name']]
                <=> [$aPct, $aDiff, $a['points_for'], $b['team']['name']];
        });
        return $list;
    }
}
