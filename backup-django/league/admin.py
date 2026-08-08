from django.contrib import admin

from .models import Game, Player, Result, Season, Team


class PlayerInline(admin.TabularInline):
    model = Player
    extra = 1
    fields = ("display_name", "user", "is_captain")


@admin.register(Team)
class TeamAdmin(admin.ModelAdmin):
    list_display = ("name", "abbrev", "color")
    search_fields = ("name", "abbrev")
    inlines = [PlayerInline]


@admin.register(Season)
class SeasonAdmin(admin.ModelAdmin):
    list_display = ("name", "start_date", "end_date", "is_active")
    list_filter = ("is_active",)


@admin.register(Player)
class PlayerAdmin(admin.ModelAdmin):
    list_display = ("display_name", "team", "is_captain", "user")
    list_filter = ("team", "is_captain")
    search_fields = ("display_name", "user__email")


class ResultInline(admin.StackedInline):
    model = Result
    extra = 0
    max_num = 1


@admin.register(Game)
class GameAdmin(admin.ModelAdmin):
    list_display = ("tipoff", "away_team", "home_team", "location", "status", "season")
    list_filter = ("season", "status", "home_team", "away_team")
    search_fields = ("home_team__name", "away_team__name", "location")
    date_hierarchy = "tipoff"
    inlines = [ResultInline]


@admin.register(Result)
class ResultAdmin(admin.ModelAdmin):
    list_display = ("game", "away_score", "home_score", "submitted_by", "submitted_at")
    search_fields = ("game__home_team__name", "game__away_team__name")
