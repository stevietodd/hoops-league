<?php

declare(strict_types=1);

final class PlayoffController
{
    public static function index(): void
    {
        $season = Database::activeSeason();
        $tournament = $season ? Playoffs::findTournamentForSeason((int) $season['id']) : null;
        $rounds = [];
        $gamesById = [];
        $champion = null;

        if ($tournament) {
            $byRound = Playoffs::bracketByRound((int) $tournament['id']);
            foreach ($byRound as $roundNum => $slots) {
                $matchups = Playoffs::matchupsForRound($slots);
                foreach ($matchups as &$m) {
                    if (!empty($m['game_id'])) {
                        $gid = (int) $m['game_id'];
                        if (!isset($gamesById[$gid])) {
                            $gamesById[$gid] = ScheduleController::findGame($gid);
                        }
                        $m['game'] = $gamesById[$gid];
                    } else {
                        $m['game'] = null;
                    }
                }
                unset($m);
                usort($matchups, static function (array $a, array $b): int {
                    $tipA = $a['game']['tipoff'] ?? null;
                    $tipB = $b['game']['tipoff'] ?? null;
                    if ($tipA === null && $tipB === null) {
                        return 0;
                    }
                    if ($tipA === null) {
                        return 1;
                    }
                    if ($tipB === null) {
                        return -1;
                    }
                    return strcmp((string) $tipA, (string) $tipB);
                });
                $rounds[] = [
                    'round' => $roundNum,
                    'label' => Playoffs::roundLabel($roundNum, (int) $tournament['bracket_size']),
                    'matchups' => $matchups,
                ];
            }

            if (!empty($season['champion_team_id'])) {
                $champion = find_team((int) $season['champion_team_id']);
            }
        }

        render('playoffs', [
            'title' => 'Playoffs',
            'season' => $season,
            'tournament' => $tournament,
            'rounds' => $rounds,
            'champion' => $champion,
        ]);
    }
}
