-- LSL50 Unified Schema (portable reference)
-- SQLite dialect; adapt types for MySQL/PostgreSQL in phase 2

CREATE TABLE seasons (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  is_active INTEGER DEFAULT 0,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE teams (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  slug TEXT,
  city TEXT,
  logo_url TEXT,
  cover_url TEXT,
  description TEXT
);

CREATE TABLE players (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  team_id INTEGER NOT NULL REFERENCES teams(id),
  first_name TEXT,
  last_name TEXT,
  birth_date TEXT,
  number INTEGER,
  position TEXT,
  photo_url TEXT,
  bio TEXT
);

CREATE TABLE games (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  season_id INTEGER REFERENCES seasons(id),
  home_team_id INTEGER NOT NULL REFERENCES teams(id),
  away_team_id INTEGER NOT NULL REFERENCES teams(id),
  game_date TEXT,
  location TEXT,
  final_home INTEGER DEFAULT 0,
  final_away INTEGER DEFAULT 0,
  status TEXT DEFAULT 'scheduled',
  result_type TEXT DEFAULT 'pending',
  winning_pitcher_id INTEGER REFERENCES players(id),
  youtube_video_id TEXT,
  official_result_note TEXT,
  forfeit_winner_team_id INTEGER,
  forfeit_loser_team_id INTEGER,
  is_legal_game INTEGER DEFAULT 0,
  completed_innings INTEGER DEFAULT 0,
  started_at TEXT,
  ended_at TEXT,
  notes TEXT
);

CREATE TABLE game_player_stats (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  season_id INTEGER,
  game_id INTEGER NOT NULL REFERENCES games(id),
  team_id INTEGER NOT NULL,
  player_id INTEGER NOT NULL,
  AB INTEGER DEFAULT 0, H INTEGER DEFAULT 0,
  dbl INTEGER DEFAULT 0, tpl INTEGER DEFAULT 0,
  R INTEGER DEFAULT 0, RBI INTEGER DEFAULT 0, HR INTEGER DEFAULT 0,
  BB INTEGER DEFAULT 0, SO INTEGER DEFAULT 0, SB INTEGER DEFAULT 0,
  HBP INTEGER DEFAULT 0, SH INTEGER DEFAULT 0, SF INTEGER DEFAULT 0, E INTEGER DEFAULT 0,
  UNIQUE(game_id, player_id)
);

CREATE TABLE player_stats (
  player_id INTEGER PRIMARY KEY REFERENCES players(id),
  games_played INTEGER DEFAULT 0,
  AB INTEGER DEFAULT 0, H INTEGER DEFAULT 0,
  dbl INTEGER DEFAULT 0, tpl INTEGER DEFAULT 0, TB INTEGER DEFAULT 0,
  R INTEGER DEFAULT 0, RBI INTEGER DEFAULT 0, HR INTEGER DEFAULT 0,
  BB INTEGER DEFAULT 0, SO INTEGER DEFAULT 0, SB INTEGER DEFAULT 0,
  HBP INTEGER DEFAULT 0, SH INTEGER DEFAULT 0, SF INTEGER DEFAULT 0, E INTEGER DEFAULT 0,
  AVG REAL DEFAULT 0, OBP REAL DEFAULT 0, SLG REAL DEFAULT 0,
  updated_at TEXT
);

CREATE TABLE team_stats (
  team_id INTEGER PRIMARY KEY REFERENCES teams(id),
  wins INTEGER DEFAULT 0, losses INTEGER DEFAULT 0, ties INTEGER DEFAULT 0,
  runs_for INTEGER DEFAULT 0, runs_against INTEGER DEFAULT 0,
  updated_at TEXT
);

CREATE TABLE ai_game_notes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  season_id INTEGER,
  game_id INTEGER NOT NULL UNIQUE REFERENCES games(id),
  status TEXT DEFAULT 'draft',
  title TEXT, summary TEXT, body TEXT,
  video_url TEXT,
  clip_start_seconds INTEGER DEFAULT 0,
  clip_end_seconds INTEGER DEFAULT 0,
  highlight_reason TEXT,
  provider TEXT,
  published_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT
);

CREATE INDEX idx_gps_game ON game_player_stats(game_id);
CREATE INDEX idx_gps_player ON game_player_stats(player_id);
CREATE INDEX idx_games_season ON games(season_id);
CREATE INDEX idx_games_status ON games(status, result_type);
