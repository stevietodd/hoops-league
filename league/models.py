from django.conf import settings
from django.core.exceptions import ValidationError
from django.db import models
from django.db.models import Q


class Team(models.Model):
    name = models.CharField(max_length=100, unique=True)
    abbrev = models.CharField(max_length=5, blank=True)
    color = models.CharField(
        max_length=7,
        blank=True,
        help_text="Optional hex color, e.g. #1d4ed8",
    )

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return self.name

    @property
    def short_name(self):
        return self.abbrev or self.name[:3].upper()


class Season(models.Model):
    name = models.CharField(max_length=100)
    start_date = models.DateField(null=True, blank=True)
    end_date = models.DateField(null=True, blank=True)
    is_active = models.BooleanField(default=False)

    class Meta:
        ordering = ["-is_active", "-start_date", "name"]

    def __str__(self):
        return self.name

    def save(self, *args, **kwargs):
        super().save(*args, **kwargs)
        if self.is_active:
            Season.objects.exclude(pk=self.pk).filter(is_active=True).update(is_active=False)

    @classmethod
    def get_active(cls):
        return cls.objects.filter(is_active=True).first()


class Player(models.Model):
    user = models.OneToOneField(
        settings.AUTH_USER_MODEL,
        on_delete=models.CASCADE,
        related_name="player_profile",
        null=True,
        blank=True,
    )
    team = models.ForeignKey(Team, on_delete=models.CASCADE, related_name="players")
    display_name = models.CharField(max_length=100)
    is_captain = models.BooleanField(default=False)

    class Meta:
        ordering = ["-is_captain", "display_name"]

    def __str__(self):
        role = " (C)" if self.is_captain else ""
        return f"{self.display_name}{role} — {self.team.name}"

    def save(self, *args, **kwargs):
        super().save(*args, **kwargs)
        if self.is_captain:
            Player.objects.filter(team=self.team, is_captain=True).exclude(pk=self.pk).update(
                is_captain=False
            )


class Game(models.Model):
    class Status(models.TextChoices):
        SCHEDULED = "scheduled", "Scheduled"
        FINAL = "final", "Final"

    season = models.ForeignKey(Season, on_delete=models.CASCADE, related_name="games")
    home_team = models.ForeignKey(Team, on_delete=models.CASCADE, related_name="home_games")
    away_team = models.ForeignKey(Team, on_delete=models.CASCADE, related_name="away_games")
    tipoff = models.DateTimeField()
    location = models.CharField(max_length=200, blank=True)
    status = models.CharField(
        max_length=20,
        choices=Status.choices,
        default=Status.SCHEDULED,
    )

    class Meta:
        ordering = ["tipoff"]
        constraints = [
            models.CheckConstraint(
                check=~Q(home_team=models.F("away_team")),
                name="game_home_away_different",
            ),
        ]

    def __str__(self):
        return f"{self.away_team} @ {self.home_team} ({self.tipoff:%b %d})"

    def clean(self):
        if self.home_team_id and self.away_team_id and self.home_team_id == self.away_team_id:
            raise ValidationError("Home and away teams must be different.")

    @property
    def is_final(self):
        return self.status == self.Status.FINAL


class Result(models.Model):
    game = models.OneToOneField(Game, on_delete=models.CASCADE, related_name="result")
    home_score = models.PositiveIntegerField()
    away_score = models.PositiveIntegerField()
    submitted_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="submitted_results",
    )
    submitted_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ["-submitted_at"]

    def __str__(self):
        return (
            f"{self.game.away_team.short_name} {self.away_score} - "
            f"{self.home_score} {self.game.home_team.short_name}"
        )

    def clean(self):
        if self.home_score == self.away_score:
            raise ValidationError("Games cannot end in a tie.")

    @property
    def winner(self):
        if self.home_score > self.away_score:
            return self.game.home_team
        return self.game.away_team
