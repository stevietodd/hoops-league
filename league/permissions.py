"""Role helpers for commissioner / captain / player checks."""


def is_commissioner(user):
    return bool(user.is_authenticated and getattr(user, "is_commissioner", False))


def get_player_profile(user):
    if not user.is_authenticated:
        return None
    return getattr(user, "player_profile", None)


def is_captain(user):
    profile = get_player_profile(user)
    return bool(profile and profile.is_captain)


def is_captain_of(user, team):
    profile = get_player_profile(user)
    return bool(profile and profile.is_captain and profile.team_id == team.id)


def can_report_score(user, game):
    if not user.is_authenticated:
        return False
    if is_commissioner(user):
        return True
    profile = get_player_profile(user)
    if not profile or not profile.is_captain:
        return False
    return profile.team_id in (game.home_team_id, game.away_team_id)


def can_manage_roster(user, team):
    return is_commissioner(user) or is_captain_of(user, team)
