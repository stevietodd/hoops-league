from django.contrib import messages
from django.contrib.auth import get_user_model
from django.contrib.auth.decorators import login_required
from django.db import transaction
from django.db.models import Q
from django.http import HttpResponseForbidden
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_http_methods, require_POST

from .forms import AssignCaptainForm, GameForm, RosterPlayerForm, ScoreReportForm
from .models import Game, Player, Result, Season, Team
from .permissions import can_manage_roster, can_report_score, is_admin, is_commissioner
from .services.standings import compute_standings, upcoming_games

User = get_user_model()


@login_required
def home(request):
    season = Season.get_active()
    return render(
        request,
        "league/home.html",
        {
            "season": season,
            "standings": compute_standings(season),
            "teams": Team.objects.all(),
        },
    )


@login_required
def schedule(request):
    season = Season.get_active()
    team_id = request.GET.get("team")
    games = Game.objects.none()
    if season:
        games = (
            Game.objects.filter(season=season)
            .select_related("home_team", "away_team", "result")
            .order_by("tipoff")
        )
        if team_id:
            games = games.filter(Q(home_team_id=team_id) | Q(away_team_id=team_id))
    return render(
        request,
        "league/schedule.html",
        {
            "season": season,
            "games": games,
            "teams": Team.objects.all(),
            "selected_team": team_id,
        },
    )


@login_required
def game_detail(request, pk):
    game = get_object_or_404(
        Game.objects.select_related("home_team", "away_team", "season", "result"),
        pk=pk,
    )
    return render(
        request,
        "league/game_detail.html",
        {
            "game": game,
            "can_report": can_report_score(request.user, game),
        },
    )


@login_required
def standings(request):
    season = Season.get_active()
    return render(
        request,
        "league/standings.html",
        {
            "season": season,
            "standings": compute_standings(season),
        },
    )


@login_required
def team_list(request):
    teams = Team.objects.prefetch_related("players").all()
    return render(request, "league/team_list.html", {"teams": teams})


@login_required
def team_detail(request, pk):
    team = get_object_or_404(Team.objects.prefetch_related("players__user"), pk=pk)
    return render(
        request,
        "league/team_detail.html",
        {
            "team": team,
            "can_manage": can_manage_roster(request.user, team),
            "is_commissioner": is_commissioner(request.user),
            "roster_form": RosterPlayerForm(),
            "captain_form": AssignCaptainForm(team) if is_commissioner(request.user) else None,
        },
    )


@login_required
@require_http_methods(["GET", "POST"])
def report_score(request, pk):
    game = get_object_or_404(
        Game.objects.select_related("home_team", "away_team", "result"),
        pk=pk,
    )
    if not can_report_score(request.user, game):
        return HttpResponseForbidden("Only captains of this game's teams or the commissioner can report scores.")

    initial = {}
    if hasattr(game, "result"):
        initial = {"home_score": game.result.home_score, "away_score": game.result.away_score}

    if request.method == "POST":
        form = ScoreReportForm(request.POST)
        if form.is_valid():
            with transaction.atomic():
                result, _ = Result.objects.update_or_create(
                    game=game,
                    defaults={
                        "home_score": form.cleaned_data["home_score"],
                        "away_score": form.cleaned_data["away_score"],
                        "submitted_by": request.user,
                    },
                )
                game.status = Game.Status.FINAL
                game.save(update_fields=["status"])
            messages.success(request, "Score saved. Game marked final.")
            if request.htmx:
                return render(
                    request,
                    "league/partials/score_saved.html",
                    {"game": game, "result": result},
                )
            return redirect("game_detail", pk=game.pk)
    else:
        form = ScoreReportForm(initial=initial)

    template = "league/partials/score_form.html" if request.htmx else "league/report_score.html"
    return render(request, template, {"game": game, "form": form})


@login_required
@require_POST
def add_roster_player(request, pk):
    team = get_object_or_404(Team, pk=pk)
    if not can_manage_roster(request.user, team):
        return HttpResponseForbidden("Only this team's captain or the commissioner can edit the roster.")

    form = RosterPlayerForm(request.POST)
    if form.is_valid():
        player = form.save(commit=False)
        player.team = team
        player.save()
        messages.success(request, f"Added {player.display_name} to the roster.")
    else:
        messages.error(request, "Could not add player. Check the name and try again.")
    return redirect("team_detail", pk=team.pk)


@login_required
@require_POST
def remove_roster_player(request, team_pk, player_pk):
    team = get_object_or_404(Team, pk=team_pk)
    if not can_manage_roster(request.user, team):
        return HttpResponseForbidden("Only this team's captain or the commissioner can edit the roster.")

    player = get_object_or_404(Player, pk=player_pk, team=team)
    name = player.display_name
    player.delete()
    messages.success(request, f"Removed {name} from the roster.")
    return redirect("team_detail", pk=team.pk)


@login_required
@require_POST
def assign_captain(request, pk):
    if not is_commissioner(request.user):
        return HttpResponseForbidden("Only the commissioner can assign captains.")

    team = get_object_or_404(Team, pk=pk)
    form = AssignCaptainForm(team, request.POST)
    if form.is_valid():
        player = form.cleaned_data["player"]
        player.is_captain = True
        player.save()
        messages.success(request, f"{player.display_name} is now captain of {team.name}.")
    else:
        messages.error(request, "Could not assign captain.")
    return redirect("team_detail", pk=team.pk)


@login_required
@require_http_methods(["GET", "POST"])
def manage_game_create(request):
    if not is_commissioner(request.user):
        return HttpResponseForbidden("Only the commissioner can create games.")

    season = Season.get_active()
    if request.method == "POST":
        form = GameForm(request.POST)
        if form.is_valid():
            game = form.save()
            messages.success(request, "Game added to the schedule.")
            return redirect("game_detail", pk=game.pk)
    else:
        form = GameForm(initial={"season": season, "status": Game.Status.SCHEDULED})

    return render(
        request,
        "league/manage_game_form.html",
        {"form": form, "title": "Add game", "season": season},
    )


@login_required
@require_http_methods(["GET", "POST"])
def manage_game_edit(request, pk):
    if not is_commissioner(request.user):
        return HttpResponseForbidden("Only the commissioner can edit games.")

    game = get_object_or_404(Game, pk=pk)
    if request.method == "POST":
        form = GameForm(request.POST, instance=game)
        if form.is_valid():
            form.save()
            messages.success(request, "Game updated.")
            return redirect("game_detail", pk=game.pk)
    else:
        form = GameForm(instance=game)

    return render(
        request,
        "league/manage_game_form.html",
        {"form": form, "title": "Edit game", "game": game},
    )


@login_required
def manage_hub(request):
    if not is_commissioner(request.user):
        return HttpResponseForbidden("Only commissioners and admins can access manage.")

    season = Season.get_active()
    return render(
        request,
        "league/manage.html",
        {
            "season": season,
            "teams": Team.objects.prefetch_related("players").all(),
            "upcoming": upcoming_games(season, limit=10),
        },
    )


@login_required
def manage_users(request):
    if not is_admin(request.user):
        return HttpResponseForbidden("Only admins can manage user roles.")

    users = User.objects.order_by("-is_admin", "-is_commissioner", "email")
    return render(request, "league/manage_users.html", {"users": users})


@login_required
@require_POST
def toggle_commissioner(request, pk):
    if not is_admin(request.user):
        return HttpResponseForbidden("Only admins can assign commissioners.")

    target = get_object_or_404(User, pk=pk)
    if target.is_admin:
        messages.error(request, "Admin roles are managed separately; cannot change commissioner on an admin.")
        return redirect("manage_users")

    target.is_commissioner = not target.is_commissioner
    target.save(update_fields=["is_commissioner"])
    if target.is_commissioner:
        messages.success(request, f"{target.email} is now a commissioner.")
    else:
        messages.success(request, f"{target.email} is no longer a commissioner.")
    return redirect("manage_users")

