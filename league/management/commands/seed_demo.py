from datetime import timedelta

from django.core.management.base import BaseCommand
from django.utils import timezone

from accounts.models import User
from league.models import Game, Player, Result, Season, Team


class Command(BaseCommand):
    help = "Seed a demo season with teams, players, and a few games."

    def handle(self, *args, **options):
        admin_user, admin_created = User.objects.get_or_create(
            email="admin@hoops.local",
            defaults={
                "first_name": "Avery",
                "last_name": "Admin",
                "is_admin": True,
                "is_commissioner": True,
                "is_staff": True,
            },
        )
        if admin_created or not admin_user.has_usable_password():
            admin_user.set_password("hoops1234")
            admin_user.is_admin = True
            admin_user.is_commissioner = True
            admin_user.is_staff = True
            admin_user.save()
        else:
            admin_user.is_admin = True
            admin_user.is_commissioner = True
            admin_user.is_staff = True
            admin_user.save(update_fields=["is_admin", "is_commissioner", "is_staff"])

        commissioner, created = User.objects.get_or_create(
            email="commissioner@hoops.local",
            defaults={
                "first_name": "Casey",
                "last_name": "Commissioner",
                "is_commissioner": True,
                "is_staff": False,
                "is_admin": False,
            },
        )
        if created or not commissioner.has_usable_password():
            commissioner.set_password("hoops1234")
            commissioner.is_commissioner = True
            commissioner.is_staff = False
            commissioner.is_admin = False
            commissioner.save()
        else:
            # Keep commissioner below admin: no staff/admin flags from older seeds.
            commissioner.is_commissioner = True
            commissioner.is_staff = False
            commissioner.is_admin = False
            commissioner.save(update_fields=["is_commissioner", "is_staff", "is_admin"])

        season, _ = Season.objects.get_or_create(
            name="Summer 2026",
            defaults={
                "start_date": timezone.localdate() - timedelta(days=14),
                "end_date": timezone.localdate() + timedelta(days=60),
                "is_active": True,
            },
        )
        if not season.is_active:
            season.is_active = True
            season.save()

        roster_plan = {
            "Court Kings": {
                "abbrev": "KNG",
                "color": "#1d4ed8",
                "captain": ("Alex", "King", "alex@hoops.local"),
                "players": ["Jordan Lee", "Sam Rivera", "Chris Patton"],
            },
            "Fast Break": {
                "abbrev": "FBK",
                "color": "#b45309",
                "captain": ("Morgan", "Swift", "morgan@hoops.local"),
                "players": ["Riley Chen", "Taylor Brooks", "Jamie Ortiz"],
            },
            "Rim Runners": {
                "abbrev": "RIM",
                "color": "#166534",
                "captain": ("Drew", "Hayes", "drew@hoops.local"),
                "players": ["Casey Nguyen", "Avery Scott", "Quinn Diaz"],
            },
            "Alley-Oops": {
                "abbrev": "AOP",
                "color": "#7c3aed",
                "captain": ("Parker", "Lane", "parker@hoops.local"),
                "players": ["Reese Kim", "Blake Torres", "Cameron Wells"],
            },
        }

        teams = {}
        for name, data in roster_plan.items():
            team, _ = Team.objects.get_or_create(
                name=name,
                defaults={"abbrev": data["abbrev"], "color": data["color"]},
            )
            teams[name] = team

            first, last, email = data["captain"]
            user, user_created = User.objects.get_or_create(
                email=email,
                defaults={"first_name": first, "last_name": last},
            )
            if user_created or not user.has_usable_password():
                user.set_password("hoops1234")
                user.save()

            captain, _ = Player.objects.update_or_create(
                team=team,
                display_name=f"{first} {last}",
                defaults={"user": user, "is_captain": True},
            )
            if not captain.is_captain:
                captain.is_captain = True
                captain.save()

            for player_name in data["players"]:
                Player.objects.get_or_create(
                    team=team,
                    display_name=player_name,
                    defaults={"is_captain": False},
                )

        now = timezone.now()
        schedule = [
            ("Fast Break", "Court Kings", now - timedelta(days=7), "North Gym", (62, 58), True),
            ("Rim Runners", "Alley-Oops", now - timedelta(days=7), "North Gym", (71, 70), True),
            ("Court Kings", "Rim Runners", now - timedelta(days=3), "South Court", (55, 60), True),
            ("Alley-Oops", "Fast Break", now + timedelta(days=2), "North Gym", None, False),
            ("Court Kings", "Alley-Oops", now + timedelta(days=5), "South Court", None, False),
            ("Fast Break", "Rim Runners", now + timedelta(days=9), "North Gym", None, False),
        ]

        for away, home, tipoff, location, scores, is_final in schedule:
            game, _ = Game.objects.get_or_create(
                season=season,
                home_team=teams[home],
                away_team=teams[away],
                tipoff=tipoff,
                defaults={
                    "location": location,
                    "status": Game.Status.FINAL if is_final else Game.Status.SCHEDULED,
                },
            )
            if is_final and scores:
                Result.objects.update_or_create(
                    game=game,
                    defaults={
                        "away_score": scores[0],
                        "home_score": scores[1],
                        "submitted_by": commissioner,
                    },
                )
                if game.status != Game.Status.FINAL:
                    game.status = Game.Status.FINAL
                    game.save(update_fields=["status"])

        self.stdout.write(self.style.SUCCESS("Demo league ready."))
        self.stdout.write("  admin@hoops.local / hoops1234         (admin)")
        self.stdout.write("  commissioner@hoops.local / hoops1234  (commissioner)")
        self.stdout.write("  alex@hoops.local / hoops1234          (Court Kings captain)")
        self.stdout.write("  morgan@hoops.local / hoops1234        (Fast Break captain)")
