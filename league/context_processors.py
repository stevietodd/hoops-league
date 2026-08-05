from league.permissions import get_player_profile, is_admin, is_captain, is_commissioner
from league.models import Season


def league_nav(request):
    user = request.user
    return {
        "active_season": Season.get_active(),
        "nav_is_admin": is_admin(user),
        "nav_is_commissioner": is_commissioner(user),
        "nav_is_captain": is_captain(user),
        "nav_player": get_player_profile(user),
    }
