# Hoops League

Mobile-first Django app for organizing a basketball league: schedule, team W/L standings, and rosters. Captains report scores; commissioners manage the league.

## Stack

- Django 4.2 + django-htmx
- Tailwind CSS (CDN) + HTMX
- SQLite (local)

## Quick start

```bash
cd hoops-league
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python manage.py migrate
python manage.py seed_demo
python manage.py runserver
```

Open http://127.0.0.1:8000/ and sign in:

| Email | Password | Role |
|-------|----------|------|
| `commissioner@hoops.local` | `hoops1234` | Commissioner |
| `alex@hoops.local` | `hoops1234` | Court Kings captain |
| `morgan@hoops.local` | `hoops1234` | Fast Break captain |

## Features

- **Players** — view home, schedule, standings, team rosters
- **Captains** — edit own roster; report/edit scores for own team's games
- **Commissioner** — manage hub, create/edit games, assign captains, full Django admin

Scores are **team totals only** (no individual player points).
