<?php
session_start();

$ROOT_DIR = __DIR__;
$DATA_DIR = $ROOT_DIR . "/data";
$UPLOAD_DIR = $ROOT_DIR . "/public/uploads";
$DB_FILE = $DATA_DIR . "/lsl50_local.sqlite";
$PROJECT_ROOT = dirname($ROOT_DIR);

require_once $ROOT_DIR . "/src/Support/Env.php";
require_once $ROOT_DIR . "/src/Auth/AdminAuth.php";

lsl_load_env_files([
  $PROJECT_ROOT . "/.env",
  $DATA_DIR . "/.env",
  $ROOT_DIR . "/.env",
]);

if (!is_dir($DATA_DIR)) mkdir($DATA_DIR, 0775, true);
if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0775, true);

function db(): PDO {
  static $pdo = null;
  global $DB_FILE;
  if ($pdo instanceof PDO) return $pdo;

  $pdo = new PDO("sqlite:" . $DB_FILE);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $pdo->exec("PRAGMA foreign_keys = ON");
  $pdo->exec("PRAGMA busy_timeout = 5000");
  init_local_db($pdo);
  return $pdo;
}

function init_local_db(PDO $pdo): void {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS seasons (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      starts_at TEXT,
      ends_at TEXT,
      status TEXT NOT NULL DEFAULT 'active',
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      archived_at TEXT
    );
    CREATE TABLE IF NOT EXISTS season_archives (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      season_id INTEGER NOT NULL,
      season_name TEXT NOT NULL,
      snapshot_json TEXT NOT NULL,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      email TEXT UNIQUE NOT NULL,
      password_hash TEXT NOT NULL,
      role TEXT NOT NULL DEFAULT 'admin',
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS teams (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      slug TEXT UNIQUE,
      city TEXT,
      logo_url TEXT,
      cover_url TEXT,
      description TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS players (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      team_id INTEGER NULL,
      first_name TEXT NOT NULL,
      last_name TEXT NOT NULL,
      birth_date TEXT,
      number TEXT,
      position TEXT,
      photo_url TEXT,
      bio TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
    );
    CREATE TABLE IF NOT EXISTS games (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      season_id INTEGER,
      home_team_id INTEGER NOT NULL,
      away_team_id INTEGER NOT NULL,
      game_date TEXT NOT NULL,
      location TEXT,
      final_home INTEGER DEFAULT 0,
      final_away INTEGER DEFAULT 0,
      winning_pitcher_id INTEGER,
      status TEXT DEFAULT 'scheduled',
      result_type TEXT DEFAULT 'pending',
      official_result_note TEXT,
      forfeit_winner_team_id INTEGER,
      forfeit_loser_team_id INTEGER,
      is_legal_game INTEGER DEFAULT 0,
      completed_innings INTEGER DEFAULT 0,
      started_at TEXT,
      ended_at TEXT,
      notes TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE CASCADE,
      FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE CASCADE,
      FOREIGN KEY (winning_pitcher_id) REFERENCES players(id) ON DELETE SET NULL
    );
    CREATE TABLE IF NOT EXISTS schedule_entries (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      season_id INTEGER NOT NULL,
      stage TEXT NOT NULL,
      round_no INTEGER NOT NULL DEFAULT 0,
      game_no INTEGER NOT NULL DEFAULT 0,
      game_date TEXT NOT NULL,
      game_time TEXT NOT NULL,
      field TEXT,
      home_team_id INTEGER,
      away_team_id INTEGER,
      home_label TEXT,
      away_label TEXT,
      notes TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
      FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE SET NULL,
      FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE SET NULL
    );
    CREATE TABLE IF NOT EXISTS game_player_stats (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      season_id INTEGER,
      game_id INTEGER NOT NULL,
      team_id INTEGER NOT NULL,
      player_id INTEGER NOT NULL,
      AB INTEGER DEFAULT 0,
      H INTEGER DEFAULT 0,
      dbl INTEGER DEFAULT 0,
      tpl INTEGER DEFAULT 0,
      R INTEGER DEFAULT 0,
      RBI INTEGER DEFAULT 0,
      HR INTEGER DEFAULT 0,
      BB INTEGER DEFAULT 0,
      SO INTEGER DEFAULT 0,
      SB INTEGER DEFAULT 0,
      HBP INTEGER DEFAULT 0,
      SH INTEGER DEFAULT 0,
      SF INTEGER DEFAULT 0,
      E INTEGER DEFAULT 0,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      UNIQUE (game_id, player_id),
      FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
      FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
      FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS game_play_events (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      season_id INTEGER,
      game_id INTEGER NOT NULL,
      inning INTEGER NOT NULL DEFAULT 1,
      half TEXT NOT NULL DEFAULT 'top',
      batting_team_id INTEGER NOT NULL,
      batter_id INTEGER NOT NULL,
      result TEXT NOT NULL,
      batter_to TEXT,
      runner_1b_id INTEGER,
      runner_1b_to TEXT,
      runner_2b_id INTEGER,
      runner_2b_to TEXT,
      runner_3b_id INTEGER,
      runner_3b_to TEXT,
      outs_on_play INTEGER DEFAULT 0,
      out_detail TEXT,
      rbi INTEGER DEFAULT 0,
      runs_scored INTEGER DEFAULT 0,
      notes TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
      FOREIGN KEY (batting_team_id) REFERENCES teams(id) ON DELETE CASCADE,
      FOREIGN KEY (batter_id) REFERENCES players(id) ON DELETE CASCADE,
      FOREIGN KEY (runner_1b_id) REFERENCES players(id) ON DELETE SET NULL,
      FOREIGN KEY (runner_2b_id) REFERENCES players(id) ON DELETE SET NULL,
      FOREIGN KEY (runner_3b_id) REFERENCES players(id) ON DELETE SET NULL
    );
    CREATE TABLE IF NOT EXISTS game_lineups (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      season_id INTEGER,
      game_id INTEGER NOT NULL,
      team_id INTEGER NOT NULL,
      batting_order INTEGER NOT NULL,
      player_id INTEGER NOT NULL,
      field_position TEXT,
      active INTEGER DEFAULT 1,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
      UNIQUE (game_id, team_id, batting_order),
      FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
      FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
      FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS game_borrowed_players (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      season_id INTEGER,
      game_id INTEGER NOT NULL,
      player_id INTEGER NOT NULL,
      original_team_id INTEGER,
      borrowed_team_id INTEGER NOT NULL,
      reason TEXT,
      approved_by TEXT,
      active INTEGER DEFAULT 1,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      UNIQUE (game_id, player_id, borrowed_team_id),
      FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
      FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
      FOREIGN KEY (original_team_id) REFERENCES teams(id) ON DELETE SET NULL,
      FOREIGN KEY (borrowed_team_id) REFERENCES teams(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS player_stats (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      player_id INTEGER UNIQUE NOT NULL,
      games_played INTEGER DEFAULT 0,
      AB INTEGER DEFAULT 0,
      H INTEGER DEFAULT 0,
      dbl INTEGER DEFAULT 0,
      tpl INTEGER DEFAULT 0,
      TB INTEGER DEFAULT 0,
      R INTEGER DEFAULT 0,
      RBI INTEGER DEFAULT 0,
      HR INTEGER DEFAULT 0,
      BB INTEGER DEFAULT 0,
      SO INTEGER DEFAULT 0,
      SB INTEGER DEFAULT 0,
      HBP INTEGER DEFAULT 0,
      SH INTEGER DEFAULT 0,
      SF INTEGER DEFAULT 0,
      E INTEGER DEFAULT 0,
      AVG REAL DEFAULT 0.000,
      OBP REAL DEFAULT 0.000,
      SLG REAL DEFAULT 0.000,
      updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS team_stats (
      team_id INTEGER PRIMARY KEY,
      wins INTEGER DEFAULT 0,
      losses INTEGER DEFAULT 0,
      ties INTEGER DEFAULT 0,
      runs_for INTEGER DEFAULT 0,
      runs_against INTEGER DEFAULT 0,
      updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS media (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      type TEXT NOT NULL,
      title TEXT NOT NULL,
      url TEXT NOT NULL,
      thumbnail_url TEXT,
      featured INTEGER DEFAULT 0,
      week_start TEXT,
      week_end TEXT,
      tags TEXT,
      order_index INTEGER DEFAULT 0,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS ai_game_notes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      season_id INTEGER NOT NULL,
      game_id INTEGER NOT NULL,
      status TEXT NOT NULL DEFAULT 'draft',
      title TEXT NOT NULL,
      summary TEXT,
      body TEXT NOT NULL,
      video_url TEXT,
      clip_start_seconds INTEGER DEFAULT 0,
      clip_end_seconds INTEGER DEFAULT 45,
      highlight_reason TEXT,
      provider TEXT DEFAULT 'local',
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
      published_at TEXT,
      UNIQUE (game_id),
      FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
      FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS weekly_awards (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      week_start TEXT NOT NULL,
      week_end TEXT NOT NULL,
      player_id INTEGER NULL,
      team_id INTEGER NULL,
      award_type TEXT NOT NULL,
      description TEXT,
      media_url TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL,
      FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
    );
    CREATE TABLE IF NOT EXISTS app_settings (
      key TEXT PRIMARY KEY,
      value TEXT NOT NULL,
      updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
  ");
  ensure_column($pdo, "games", "season_id", "INTEGER");
  ensure_column($pdo, "games", "winning_pitcher_id", "INTEGER");
  ensure_column($pdo, "games", "status", "TEXT DEFAULT 'scheduled'");
  ensure_column($pdo, "games", "result_type", "TEXT DEFAULT 'pending'");
  ensure_column($pdo, "games", "official_result_note", "TEXT");
  ensure_column($pdo, "games", "forfeit_winner_team_id", "INTEGER");
  ensure_column($pdo, "games", "forfeit_loser_team_id", "INTEGER");
  ensure_column($pdo, "games", "is_legal_game", "INTEGER DEFAULT 0");
  ensure_column($pdo, "games", "completed_innings", "INTEGER DEFAULT 0");
  ensure_column($pdo, "games", "started_at", "TEXT");
  ensure_column($pdo, "games", "ended_at", "TEXT");
  ensure_column($pdo, "game_play_events", "season_id", "INTEGER");
  ensure_column($pdo, "game_play_events", "out_detail", "TEXT");
  ensure_column($pdo, "game_lineups", "season_id", "INTEGER");
  ensure_column($pdo, "game_player_stats", "season_id", "INTEGER");
  ensure_column($pdo, "game_player_stats", "HBP", "INTEGER DEFAULT 0");
  ensure_column($pdo, "game_player_stats", "SH", "INTEGER DEFAULT 0");
  ensure_column($pdo, "game_player_stats", "SF", "INTEGER DEFAULT 0");
  ensure_column($pdo, "game_player_stats", "E", "INTEGER DEFAULT 0");
  ensure_column($pdo, "player_stats", "HBP", "INTEGER DEFAULT 0");
  ensure_column($pdo, "player_stats", "SH", "INTEGER DEFAULT 0");
  ensure_column($pdo, "player_stats", "SF", "INTEGER DEFAULT 0");
  ensure_column($pdo, "player_stats", "E", "INTEGER DEFAULT 0");
  ensure_column($pdo, "players", "birth_date", "TEXT");

  $seasonCount = (int)$pdo->query("SELECT COUNT(*) FROM seasons")->fetchColumn();
  if ($seasonCount === 0) {
    $pdo->prepare("INSERT INTO seasons (name, starts_at, status) VALUES (?, ?, 'active')")
      ->execute(["Temporada Actual", date("Y-m-d")]);
  }

  $count = (int)$pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
  if ($count === 0) {
    $teams = [
      ["Bucaneros", "bucaneros", "Broward County, FL", "#111111"],
      ["Caribeños", "caribenos", "Broward County, FL", "#0033A0"],
      ["Cerveceros", "cerveceros", "Broward County, FL", "#7A1F2B"],
      ["Cubs", "cubs", "Broward County, FL", "#0E4F9F"],
      ["Hispanos", "hispanos", "Broward County, FL", "#0A5C3B"],
      ["Sharks", "sharks", "Broward County, FL", "#007C89"],
    ];
    $stmt = $pdo->prepare("INSERT INTO teams (name, slug, city, description) VALUES (?, ?, ?, ?)");
    foreach ($teams as $team) {
      $stmt->execute([$team[0], $team[1], $team[2], "Equipo activo de Legends Softball League 50+."]);
      $teamId = (int)$pdo->lastInsertId();
      $pdo->prepare("INSERT INTO team_stats (team_id) VALUES (?)")->execute([$teamId]);
    }
  }
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
  $columns = $pdo->query("PRAGMA table_info($table)")->fetchAll();
  foreach ($columns as $col) {
    if (($col["name"] ?? "") === $column) return;
  }
  try {
    $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
  } catch (Throwable $e) {
    $message = strtolower($e->getMessage());
    if (!str_contains($message, "duplicate column")) throw $e;
  }
}

function active_season(PDO $pdo = null): array {
  $pdo = $pdo ?: db();
  $season = $pdo->query("SELECT * FROM seasons WHERE status='active' ORDER BY id DESC LIMIT 1")->fetch();
  if (!$season) {
    $pdo->prepare("INSERT INTO seasons (name, starts_at, status) VALUES (?, ?, 'active')")
      ->execute(["Temporada Actual", date("Y-m-d")]);
    $season = $pdo->query("SELECT * FROM seasons WHERE status='active' ORDER BY id DESC LIMIT 1")->fetch();
  }
  return $season;
}

function archive_and_start_season(PDO $pdo, string $nextName, string $nextStart, bool $keepRoster = true): void {
  $season = active_season($pdo);
  $seasonId = (int)$season["id"];

  $snapshot = [
    "season" => $season,
    "teams" => $pdo->query("SELECT * FROM teams ORDER BY name")->fetchAll(),
    "players" => $pdo->query("SELECT p.*, t.name team_name FROM players p LEFT JOIN teams t ON t.id=p.team_id ORDER BY t.name, p.last_name, p.first_name")->fetchAll(),
    "games" => $pdo->query("SELECT * FROM games WHERE COALESCE(season_id, $seasonId) = $seasonId ORDER BY game_date, id")->fetchAll(),
    "schedule_entries" => $pdo->query("SELECT * FROM schedule_entries WHERE season_id = $seasonId ORDER BY game_date, game_time, id")->fetchAll(),
    "game_player_stats" => $pdo->query("SELECT * FROM game_player_stats WHERE COALESCE(season_id, $seasonId) = $seasonId ORDER BY game_id, player_id")->fetchAll(),
    "game_play_events" => $pdo->query("SELECT * FROM game_play_events WHERE COALESCE(season_id, $seasonId) = $seasonId ORDER BY game_id, inning, id")->fetchAll(),
    "game_lineups" => $pdo->query("SELECT * FROM game_lineups WHERE COALESCE(season_id, $seasonId) = $seasonId ORDER BY game_id, team_id, batting_order")->fetchAll(),
    "player_stats" => $pdo->query("SELECT ps.*, p.first_name || ' ' || p.last_name player_name FROM player_stats ps JOIN players p ON p.id=ps.player_id ORDER BY p.last_name, p.first_name")->fetchAll(),
    "team_stats" => $pdo->query("SELECT ts.*, t.name team_name FROM team_stats ts JOIN teams t ON t.id=ts.team_id ORDER BY t.name")->fetchAll(),
  ];

  $pdo->beginTransaction();
  try {
    $pdo->prepare("INSERT INTO season_archives (season_id, season_name, snapshot_json) VALUES (?, ?, ?)")
      ->execute([$seasonId, $season["name"], json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]);
    $pdo->prepare("UPDATE seasons SET status='archived', ends_at=COALESCE(ends_at, ?), archived_at=CURRENT_TIMESTAMP WHERE id=?")
      ->execute([date("Y-m-d"), $seasonId]);
    $pdo->prepare("INSERT INTO seasons (name, starts_at, status) VALUES (?, ?, 'active')")
      ->execute([$nextName, $nextStart ?: date("Y-m-d")]);

    $pdo->exec("DELETE FROM game_player_stats");
    $pdo->exec("DELETE FROM game_play_events");
    $pdo->exec("DELETE FROM game_lineups");
    $pdo->exec("DELETE FROM games");
    $pdo->exec("DELETE FROM schedule_entries");
    $pdo->exec("DELETE FROM player_stats");
    $pdo->exec("DELETE FROM team_stats");

    if (!$keepRoster) {
      $pdo->exec("DELETE FROM players");
    } else {
      $playerIds = $pdo->query("SELECT id FROM players")->fetchAll();
      $stmt = $pdo->prepare("INSERT OR IGNORE INTO player_stats (player_id) VALUES (?)");
      foreach ($playerIds as $row) $stmt->execute([(int)$row["id"]]);
    }
    $teamIds = $pdo->query("SELECT id FROM teams")->fetchAll();
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO team_stats (team_id) VALUES (?)");
    foreach ($teamIds as $row) $stmt->execute([(int)$row["id"]]);

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

function h($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function player_age(?string $birthDate): ?int {
  if (!$birthDate) return null;
  try {
    return (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable('today'))->y;
  } catch (Throwable $e) {
    return null;
  }
}

function age_status(?string $birthDate): array {
  $age = player_age($birthDate);
  if ($age === null) return ["label" => "Sin fecha", "ok" => false, "age" => null];
  return ["label" => $age . " años", "ok" => $age >= 50, "age" => $age];
}

function postseason_eligible(array $player): bool {
  $games = (int)($player["games_played"] ?? 0);
  return $games >= 3;
}

function lsl_setting(PDO $pdo, string $key, string $default = ""): string {
  try {
    $stmt = $pdo->prepare("SELECT value FROM app_settings WHERE key=?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (string)$value : $default;
  } catch (Throwable $e) {
    return $default;
  }
}

function lsl_set_setting(PDO $pdo, string $key, string $value): void {
  $pdo->prepare("INSERT INTO app_settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
    ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=CURRENT_TIMESTAMP")
    ->execute([$key, $value]);
}

function lsl_scorer_pin(PDO $pdo = null): string {
  $pin = getenv("LSL50_SCORER_PIN");
  $default = $pin !== false && $pin !== "" ? (string)$pin : "5050";
  $pdo = $pdo ?: db();
  return lsl_setting($pdo, "scorer_pin", $default);
}

function lsl_stat_int($value): int {
  return max((int)$value, 0);
}

function lsl_recalc_player_stats(PDO $pdo, int $playerId, int $seasonId): void {
  $seasonId = (int)$seasonId;
  $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN (AB + BB + HBP + SH + SF) > 0 THEN 1 ELSE 0 END),0) GP, COALESCE(SUM(AB),0) AB, COALESCE(SUM(H),0) H, COALESCE(SUM(dbl),0) dbl, COALESCE(SUM(tpl),0) tpl, COALESCE(SUM(R),0) R, COALESCE(SUM(RBI),0) RBI, COALESCE(SUM(HR),0) HR, COALESCE(SUM(BB),0) BB, COALESCE(SUM(SO),0) SO, COALESCE(SUM(SB),0) SB, COALESCE(SUM(HBP),0) HBP, COALESCE(SUM(SH),0) SH, COALESCE(SUM(SF),0) SF, COALESCE(SUM(E),0) E FROM game_player_stats WHERE player_id=? AND COALESCE(season_id, $seasonId) = $seasonId");
  $stmt->execute([$playerId]);
  $row = $stmt->fetch();
  if (!$row) return;

  $AB = max((int)$row["AB"], 0);
  $rawH = (int)$row["H"];
  $DBL = (int)$row["dbl"];
  $TPL = (int)$row["tpl"];
  $HR = (int)$row["HR"];
  $BB = (int)$row["BB"];
  $HBP = (int)$row["HBP"];
  $SH = (int)$row["SH"];
  $SF = (int)$row["SF"];
  $E = (int)$row["E"];
  $extraBaseHits = $DBL + $TPL + $HR;
  $H = max($rawH, $extraBaseHits);
  $singles = max($H - $extraBaseHits, 0);
  $TB = $singles + ($DBL * 2) + ($TPL * 3) + ($HR * 4);
  $AVG = $AB > 0 ? round($H / $AB, 3) : 0;
  $OBP_DEN = $AB + $BB + $HBP + $SF;
  $OBP = $OBP_DEN > 0 ? round(($H + $BB + $HBP) / $OBP_DEN, 3) : 0;
  $SLG = $AB > 0 ? round($TB / $AB, 3) : 0;

  $pdo->prepare("INSERT INTO player_stats (player_id,games_played,AB,H,dbl,tpl,TB,R,RBI,HR,BB,SO,SB,HBP,SH,SF,E,AVG,OBP,SLG)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ON CONFLICT(player_id) DO UPDATE SET games_played=excluded.games_played, AB=excluded.AB, H=excluded.H, dbl=excluded.dbl, tpl=excluded.tpl, TB=excluded.TB, R=excluded.R, RBI=excluded.RBI, HR=excluded.HR, BB=excluded.BB, SO=excluded.SO, SB=excluded.SB, HBP=excluded.HBP, SH=excluded.SH, SF=excluded.SF, E=excluded.E, AVG=excluded.AVG, OBP=excluded.OBP, SLG=excluded.SLG, updated_at=CURRENT_TIMESTAMP")
    ->execute([$playerId, (int)$row["GP"], $AB, $H, $DBL, $TPL, $TB, (int)$row["R"], (int)$row["RBI"], $HR, $BB, (int)$row["SO"], (int)$row["SB"], $HBP, $SH, $SF, $E, $AVG, $OBP, $SLG]);
}

function lsl_recalc_team_stats(PDO $pdo, int $seasonId): void {
  $seasonId = (int)$seasonId;
  $pdo->exec("UPDATE team_stats SET wins=0, losses=0, ties=0, runs_for=0, runs_against=0, updated_at=CURRENT_TIMESTAMP");
  $teamIds = $pdo->query("SELECT id FROM teams")->fetchAll();
  $ensure = $pdo->prepare("INSERT OR IGNORE INTO team_stats (team_id,wins,losses,ties,runs_for,runs_against) VALUES (?,0,0,0,0,0)");
  foreach ($teamIds as $team) $ensure->execute([(int)$team["id"]]);

  $games = $pdo->query("SELECT * FROM games g
    WHERE COALESCE(g.season_id, $seasonId) = $seasonId
      AND (
        EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id = g.id)
        OR COALESCE(g.result_type, '') = 'forfeit'
        OR COALESCE(g.status, '') = 'final'
      )");
  foreach ($games->fetchAll() as $g) {
    $home = (int)$g["home_team_id"];
    $away = (int)$g["away_team_id"];
    $homeRuns = (int)$g["final_home"];
    $awayRuns = (int)$g["final_away"];

    foreach ([[$home, $homeRuns, $awayRuns], [$away, $awayRuns, $homeRuns]] as $row) {
      [$teamId, $runsFor, $runsAgainst] = $row;
      $pdo->prepare("UPDATE team_stats SET runs_for = runs_for + ?, runs_against = runs_against + ?, updated_at=CURRENT_TIMESTAMP WHERE team_id=?")
        ->execute([$runsFor, $runsAgainst, $teamId]);
    }
    if ($homeRuns > $awayRuns) {
      $pdo->prepare("UPDATE team_stats SET wins=wins+1, updated_at=CURRENT_TIMESTAMP WHERE team_id=?")->execute([$home]);
      $pdo->prepare("UPDATE team_stats SET losses=losses+1, updated_at=CURRENT_TIMESTAMP WHERE team_id=?")->execute([$away]);
    } elseif ($awayRuns > $homeRuns) {
      $pdo->prepare("UPDATE team_stats SET wins=wins+1, updated_at=CURRENT_TIMESTAMP WHERE team_id=?")->execute([$away]);
      $pdo->prepare("UPDATE team_stats SET losses=losses+1, updated_at=CURRENT_TIMESTAMP WHERE team_id=?")->execute([$home]);
    } else {
      $pdo->prepare("UPDATE team_stats SET ties=ties+1, updated_at=CURRENT_TIMESTAMP WHERE team_id IN (?,?)")->execute([$home, $away]);
    }
  }
}

function lsl_save_game_box(PDO $pdo, int $seasonId, int $gameId, array $rows, int $winningPitcherId = 0): array {
  $seasonId = (int)$seasonId;
  $gameStmt = $pdo->prepare("SELECT * FROM games WHERE id=? AND COALESCE(season_id, $seasonId) = $seasonId");
  $gameStmt->execute([$gameId]);
  $game = $gameStmt->fetch();
  if (!$game) throw new RuntimeException("No se encontró el juego para anotar.");

  $affected = [];
  $homeRuns = 0;
  $awayRuns = 0;

  $pdo->beginTransaction();
  try {
    $pdo->prepare("DELETE FROM game_player_stats WHERE game_id=?")->execute([$gameId]);
    foreach ($rows as $row) {
      $playerId = (int)($row["player_id"] ?? 0);
      $teamId = (int)($row["team_id"] ?? 0);
      if (!$playerId || !$teamId) continue;

      $AB = lsl_stat_int($row["AB"] ?? 0);
      $H = lsl_stat_int($row["H"] ?? 0);
      $DBL = lsl_stat_int($row["dbl"] ?? 0);
      $TPL = lsl_stat_int($row["tpl"] ?? 0);
      $R = lsl_stat_int($row["R"] ?? 0);
      $RBI = lsl_stat_int($row["RBI"] ?? 0);
      $HR = lsl_stat_int($row["HR"] ?? 0);
      $BB = lsl_stat_int($row["BB"] ?? 0);
      $SO = lsl_stat_int($row["SO"] ?? 0);
      $SB = lsl_stat_int($row["SB"] ?? 0);
      $HBP = lsl_stat_int($row["HBP"] ?? 0);
      $SH = lsl_stat_int($row["SH"] ?? 0);
      $SF = lsl_stat_int($row["SF"] ?? 0);
      $E = lsl_stat_int($row["E"] ?? 0);

      if ($teamId === (int)$game["home_team_id"]) $homeRuns += $R;
      if ($teamId === (int)$game["away_team_id"]) $awayRuns += $R;

      $pdo->prepare("INSERT INTO game_player_stats (season_id,game_id,team_id,player_id,AB,H,dbl,tpl,R,RBI,HR,BB,SO,SB,HBP,SH,SF,E)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$seasonId, $gameId, $teamId, $playerId, $AB, $H, $DBL, $TPL, $R, $RBI, $HR, $BB, $SO, $SB, $HBP, $SH, $SF, $E]);
      $affected[$playerId] = 1;
    }

    foreach (array_keys($affected) as $playerId) lsl_recalc_player_stats($pdo, (int)$playerId, $seasonId);

    $validPitcher = null;
    if ($winningPitcherId && $homeRuns !== $awayRuns) {
      $check = $pdo->prepare("SELECT id FROM players WHERE id=? AND team_id IN (?, ?)");
      $check->execute([$winningPitcherId, (int)$game["home_team_id"], (int)$game["away_team_id"]]);
      $validPitcher = $check->fetchColumn() ? $winningPitcherId : null;
    }

    $pdo->prepare("UPDATE games SET final_home=?, final_away=?, winning_pitcher_id=? WHERE id=?")
      ->execute([$homeRuns, $awayRuns, $validPitcher, $gameId]);
    lsl_recalc_team_stats($pdo, $seasonId);
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }

  return ["home_runs" => $homeRuns, "away_runs" => $awayRuns, "winning_pitcher_id" => $validPitcher];
}

function post(string $key, $default = "") {
  return $_POST[$key] ?? $default;
}

function getv(string $key, $default = "") {
  return $_GET[$key] ?? $default;
}

function flash(string $message): void {
  $_SESSION["flashes"][] = $message;
}

function flashes(): void {
  foreach ($_SESSION["flashes"] ?? [] as $message) {
    echo '<div class="notice">' . h($message) . '</div>';
  }
  $_SESSION["flashes"] = [];
}
