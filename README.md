# Hoops League

Mobile-first Django app for organizing a basketball league: schedule, team W/L standings, and rosters. Captains report scores; commissioners run league ops; admins sit above them.

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
| `admin@hoops.local` | `hoops1234` | Admin |
| `commissioner@hoops.local` | `hoops1234` | Commissioner |
| `alex@hoops.local` | `hoops1234` | Court Kings captain |
| `morgan@hoops.local` | `hoops1234` | Fast Break captain |

## Features

- **Players** — view home, schedule, standings, team rosters
- **Captains** — edit own roster; report/edit scores for own team's games
- **Commissioner** — manage hub, create/edit games, assign captains
- **Admin** — everything a commissioner can do, plus promote/demote commissioners and Django admin

Standings, schedule, teams, and game pages are **public** (no login). Score reporting and manage tools still require an account.

Scores are **team totals only** (no individual player points).
