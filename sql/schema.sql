-- Hoops League schema (SQLite)

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    first_name TEXT NOT NULL DEFAULT '',
    last_name TEXT NOT NULL DEFAULT '',
    is_admin INTEGER NOT NULL DEFAULT 0,
    is_commissioner INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS players (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL DEFAULT '',
    last_initial TEXT NOT NULL DEFAULT '',
    display_name TEXT NOT NULL,
    current_ranking TEXT NOT NULL DEFAULT '',
    user_id INTEGER UNIQUE REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS teams (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    season_id INTEGER REFERENCES seasons(id) ON DELETE CASCADE,
    captain_id INTEGER NOT NULL REFERENCES players(id),
    display_name TEXT NOT NULL,
    color TEXT NOT NULL DEFAULT '',
    team_number TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS team_roster (
    team_id INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    player_id INTEGER NOT NULL REFERENCES players(id) ON DELETE CASCADE,
    PRIMARY KEY (team_id, player_id)
);

CREATE TABLE IF NOT EXISTS seasons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    start_date TEXT,
    end_date TEXT,
    is_active INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'playoffs', 'archived')),
    champion_team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS games (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    season_id INTEGER NOT NULL REFERENCES seasons(id) ON DELETE CASCADE,
    home_team_id INTEGER NOT NULL REFERENCES teams(id),
    away_team_id INTEGER NOT NULL REFERENCES teams(id),
    tipoff TEXT NOT NULL,
    location TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'final')),
    phase TEXT NOT NULL DEFAULT 'regular' CHECK (phase IN ('regular', 'playoff')),
    CHECK (home_team_id != away_team_id)
);

CREATE TABLE IF NOT EXISTS results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL UNIQUE REFERENCES games(id) ON DELETE CASCADE,
    home_score INTEGER NOT NULL,
    away_score INTEGER NOT NULL,
    submitted_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    submitted_at TEXT NOT NULL DEFAULT (datetime('now')),
    CHECK (home_score != away_score)
);

CREATE TABLE IF NOT EXISTS playoff_tournaments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    season_id INTEGER NOT NULL UNIQUE REFERENCES seasons(id) ON DELETE CASCADE,
    bracket_size INTEGER NOT NULL CHECK (bracket_size IN (4, 8)),
    status TEXT NOT NULL DEFAULT 'setup' CHECK (status IN ('setup', 'in_progress', 'complete'))
);

CREATE TABLE IF NOT EXISTS playoff_slots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tournament_id INTEGER NOT NULL REFERENCES playoff_tournaments(id) ON DELETE CASCADE,
    round INTEGER NOT NULL,
    slot_index INTEGER NOT NULL,
    team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL,
    seed INTEGER,
    game_id INTEGER REFERENCES games(id) ON DELETE SET NULL,
    feeds_slot_id INTEGER REFERENCES playoff_slots(id) ON DELETE SET NULL,
    planned_tipoff TEXT,
    planned_location TEXT NOT NULL DEFAULT '',
    UNIQUE (tournament_id, round, slot_index)
);

CREATE INDEX IF NOT EXISTS idx_games_season_tipoff ON games(season_id, tipoff);
CREATE INDEX IF NOT EXISTS idx_games_phase ON games(season_id, phase);
CREATE INDEX IF NOT EXISTS idx_team_roster_player ON team_roster(player_id);
CREATE INDEX IF NOT EXISTS idx_players_ranking ON players(current_ranking);
CREATE INDEX IF NOT EXISTS idx_playoff_slots_tournament ON playoff_slots(tournament_id, round, slot_index);
CREATE INDEX IF NOT EXISTS idx_playoff_slots_game ON playoff_slots(game_id);
