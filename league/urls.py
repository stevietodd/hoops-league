from django.urls import path

from . import views

urlpatterns = [
    path("", views.home, name="home"),
    path("schedule/", views.schedule, name="schedule"),
    path("standings/", views.standings, name="standings"),
    path("teams/", views.team_list, name="team_list"),
    path("teams/<int:pk>/", views.team_detail, name="team_detail"),
    path("teams/<int:pk>/roster/add/", views.add_roster_player, name="add_roster_player"),
    path(
        "teams/<int:team_pk>/roster/<int:player_pk>/remove/",
        views.remove_roster_player,
        name="remove_roster_player",
    ),
    path("teams/<int:pk>/captain/", views.assign_captain, name="assign_captain"),
    path("games/<int:pk>/", views.game_detail, name="game_detail"),
    path("games/<int:pk>/report/", views.report_score, name="report_score"),
    path("manage/", views.manage_hub, name="manage"),
    path("manage/users/", views.manage_users, name="manage_users"),
    path("manage/users/<int:pk>/toggle-commissioner/", views.toggle_commissioner, name="toggle_commissioner"),
    path("manage/games/new/", views.manage_game_create, name="manage_game_create"),
    path("manage/games/<int:pk>/edit/", views.manage_game_edit, name="manage_game_edit"),
]
