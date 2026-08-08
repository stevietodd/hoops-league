from django import forms

from .models import Game, Player, Result


class ScoreReportForm(forms.ModelForm):
    class Meta:
        model = Result
        fields = ("home_score", "away_score")
        widgets = {
            "home_score": forms.NumberInput(
                attrs={
                    "min": 0,
                    "class": "w-full rounded-lg border border-slate-300 px-3 py-3 text-lg",
                    "inputmode": "numeric",
                }
            ),
            "away_score": forms.NumberInput(
                attrs={
                    "min": 0,
                    "class": "w-full rounded-lg border border-slate-300 px-3 py-3 text-lg",
                    "inputmode": "numeric",
                }
            ),
        }

    def clean(self):
        cleaned = super().clean()
        home = cleaned.get("home_score")
        away = cleaned.get("away_score")
        if home is not None and away is not None and home == away:
            raise forms.ValidationError("Games cannot end in a tie.")
        return cleaned


class GameForm(forms.ModelForm):
    class Meta:
        model = Game
        fields = ("season", "home_team", "away_team", "tipoff", "location", "status")
        widgets = {
            "tipoff": forms.DateTimeInput(
                attrs={"type": "datetime-local", "class": "field-input"},
                format="%Y-%m-%dT%H:%M",
            ),
            "season": forms.Select(attrs={"class": "field-input"}),
            "home_team": forms.Select(attrs={"class": "field-input"}),
            "away_team": forms.Select(attrs={"class": "field-input"}),
            "location": forms.TextInput(attrs={"class": "field-input"}),
            "status": forms.Select(attrs={"class": "field-input"}),
        }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.fields["tipoff"].input_formats = ["%Y-%m-%dT%H:%M", "%Y-%m-%d %H:%M:%S", "%Y-%m-%d %H:%M"]
        if self.instance and self.instance.pk and self.instance.tipoff:
            self.initial["tipoff"] = self.instance.tipoff.strftime("%Y-%m-%dT%H:%M")


class AssignCaptainForm(forms.Form):
    player = forms.ModelChoiceField(
        queryset=Player.objects.none(),
        widget=forms.Select(attrs={"class": "field-input"}),
    )

    def __init__(self, team, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.team = team
        self.fields["player"].queryset = Player.objects.filter(team=team).order_by("display_name")


class RosterPlayerForm(forms.ModelForm):
    class Meta:
        model = Player
        fields = ("display_name",)
        widgets = {
            "display_name": forms.TextInput(
                attrs={
                    "class": "field-input",
                    "placeholder": "Player name",
                }
            ),
        }
