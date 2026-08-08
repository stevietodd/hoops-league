# Hoops League (PHP)

Mobile-first PHP app for organizing a basketball league on **DreamHost shared hosting**: schedule, team W/L standings, and rosters.

The previous Django implementation is preserved in [`backup-django/`](backup-django/).

## Stack

- PHP 8+ (SQLite via PDO)
- Plain PHP front controller (no framework)
- Tailwind CSS (CDN)
- Apache `mod_rewrite` (`.htaccess` in `public/`)

## Local quick start

```bash
cd hoops-league
php bin/seed.php
php -S 127.0.0.1:8000 -t public
```

Open http://127.0.0.1:8000/

| Email | Password | Role |
|-------|----------|------|
| `admin@hoops.local` | `hoops1234` | Admin |
| `commissioner@hoops.local` | `hoops1234` | Commissioner |
| `alex@hoops.local` | `hoops1234` | Court Kings captain |

## DreamHost deploy

1. Upload the project to your account (e.g. `~/hoops-league`).
2. In the DreamHost panel, set the domain’s **web directory** to `hoops-league/public`  
   **or** copy/symlink `public/` contents into `~/bball.dreamhosters.com/` and keep `app/`, `config/`, `templates/`, `data/`, `sql/` outside the web root (adjust paths if needed).
3. Ensure `data/` is writable by the web server.
4. SSH in and run:

```bash
cd ~/hoops-league
php bin/seed.php
```

5. Visit your domain.

For production, create `config/config.local.php`:

```php
<?php
return [
    'debug' => false,
    'base_url' => '',
];
```

## Features

- **Public** — standings, schedule, teams/rosters, game pages
- **Captains** — edit own roster; report scores for own games
- **Commissioner** — manage hub, create/edit games, assign captains
- **Admin** — promote/demote commissioners

Scores are **team totals only** (no individual player points).
