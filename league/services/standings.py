from dataclasses import dataclass

from league.models import Game, Season, Team


@dataclass
class StandingRow:
    team: Team
    wins: int = 0
    losses: int = 0
    points_for: int = 0
    points_against: int = 0

    @property
    def games_played(self):
        return self.wins + self.losses

    @property
    def win_pct(self):
        if self.games_played == 0:
            return 0.0
        return self.wins / self.games_played

    @property
    def point_diff(self):
        return self.points_for - self.points_against

    @property
    def record(self):
        return f"{self.wins}-{self.losses}"


def compute_standings(season=None):
    """Return standings rows sorted by win%, then point differential."""
    season = season or Season.get_active()
    teams = list(Team.objects.all())
    rows = {team.id: StandingRow(team=team) for team in teams}

    if not season:
        return sorted(rows.values(), key=lambda r: r.team.name)

    games = (
        Game.objects.filter(season=season, status=Game.Status.FINAL)
        .select_related("home_team", "away_team", "result")
        .filter(result__isnull=False)
    )

    for game in games:
        result = game.result
        home = rows.get(game.home_team_id)
        away = rows.get(game.away_team_id)
        if not home or not away:
            continue

        home.points_for += result.home_score
        home.points_against += result.away_score
        away.points_for += result.away_score
        away.points_against += result.home_score

        if result.home_score > result.away_score:
            home.wins += 1
            away.losses += 1
        else:
            away.wins += 1
            home.losses += 1

    return sorted(
        rows.values(),
        key=lambda r: (-r.win_pct, -r.point_diff, -r.points_for, r.team.name),
    )


def upcoming_games(season=None, limit=5):
    from django.utils import timezone

    season = season or Season.get_active()
    if not season:
        return Game.objects.none()
    return (
        Game.objects.filter(season=season, status=Game.Status.SCHEDULED, tipoff__gte=timezone.now())
        .select_related("home_team", "away_team")
        .order_by("tipoff")[:limit]
    )


def recent_results(season=None, limit=5):
    season = season or Season.get_active()
    if not season:
        return Game.objects.none()
    return (
        Game.objects.filter(season=season, status=Game.Status.FINAL)
        .select_related("home_team", "away_team", "result")
        .order_by("-tipoff")[:limit]
    )
