<?php

/**
 * PDO connection factory — sqlite (default), mysql, pgsql via .env DB_DRIVER.
 */
final class Database
{
  public static function connect(): PDO
  {
    static $pdo = null;
    if ($pdo instanceof PDO) {
      return $pdo;
    }

    $driver = strtolower((string)(lsl_env("DB_DRIVER") ?: "sqlite"));
    if (!in_array($driver, ["sqlite", "mysql", "pgsql"], true)) {
      $driver = "sqlite";
    }

    if ($driver === "mysql") {
      $host = lsl_env("DB_HOST", "127.0.0.1") ?: "127.0.0.1";
      $port = lsl_env("DB_PORT", "3306") ?: "3306";
      $name = lsl_env("DB_NAME", "lsl50") ?: "lsl50";
      $user = lsl_env("DB_USER", "lsl50") ?: "lsl50";
      $pass = lsl_env("DB_PASS", "") ?? "";
      $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
      $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);
      $pdo->exec("SET NAMES utf8mb4");
      $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } elseif ($driver === "pgsql") {
      $host = lsl_env("DB_HOST", "127.0.0.1") ?: "127.0.0.1";
      $port = lsl_env("DB_PORT", "5432") ?: "5432";
      $name = lsl_env("DB_NAME", "lsl50") ?: "lsl50";
      $user = lsl_env("DB_USER", "lsl50") ?: "lsl50";
      $pass = lsl_env("DB_PASS", "") ?? "";
      $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
      $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    } else {
      global $DB_FILE;
      $pdo = new PDO("sqlite:" . $DB_FILE);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      $pdo->exec("PRAGMA foreign_keys = ON");
      $pdo->exec("PRAGMA busy_timeout = 5000");
    }

    self::initSchema($pdo);
    return $pdo;
  }

  public static function driver(?PDO $pdo = null): string
  {
    return SqlDialect::driver($pdo);
  }

  private static function initSchema(PDO $pdo): void
  {
    if (SqlDialect::isSqlite($pdo)) {
      init_local_db($pdo);
      return;
    }

    if (!self::tableExists($pdo, "teams")) {
      $schemaPath = dirname(__DIR__, 2) . "/../docs/schema-mysql.sql";
      if (!is_file($schemaPath)) {
        $schemaPath = dirname(__DIR__, 3) . "/docs/schema-mysql.sql";
      }
      SqlDialect::execSqlFile($pdo, $schemaPath);
    }

    self::ensureRuntimeColumns($pdo);
    self::seedDefaults($pdo);
  }

  private static function tableExists(PDO $pdo, string $table): bool
  {
    if (SqlDialect::driver($pdo) === "mysql") {
      $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
      );
      $stmt->execute([$table]);
      return (int)$stmt->fetchColumn() > 0;
    }

    $stmt = $pdo->prepare(
      "SELECT COUNT(*) FROM information_schema.tables
       WHERE table_schema = current_schema() AND table_name = ?"
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
  }

  private static function ensureRuntimeColumns(PDO $pdo): void
  {
    $columns = [
      ["games", "season_id", "INT NULL"],
      ["games", "winning_pitcher_id", "INT NULL"],
      ["games", "status", "VARCHAR(32) DEFAULT 'scheduled'"],
      ["games", "result_type", "VARCHAR(32) DEFAULT 'pending'"],
      ["games", "official_result_note", "TEXT NULL"],
      ["games", "forfeit_winner_team_id", "INT NULL"],
      ["games", "forfeit_loser_team_id", "INT NULL"],
      ["games", "is_legal_game", "TINYINT(1) DEFAULT 0"],
      ["games", "completed_innings", "INT DEFAULT 0"],
      ["games", "started_at", "DATETIME NULL"],
      ["games", "ended_at", "DATETIME NULL"],
      ["games", "youtube_video_id", "VARCHAR(32) NULL"],
      ["game_play_events", "season_id", "INT NULL"],
      ["game_play_events", "out_detail", "VARCHAR(64) NULL"],
      ["game_lineups", "season_id", "INT NULL"],
      ["game_player_stats", "season_id", "INT NULL"],
      ["game_player_stats", "HBP", "INT DEFAULT 0"],
      ["game_player_stats", "SH", "INT DEFAULT 0"],
      ["game_player_stats", "SF", "INT DEFAULT 0"],
      ["game_player_stats", "E", "INT DEFAULT 0"],
      ["player_stats", "HBP", "INT DEFAULT 0"],
      ["player_stats", "SH", "INT DEFAULT 0"],
      ["player_stats", "SF", "INT DEFAULT 0"],
      ["player_stats", "E", "INT DEFAULT 0"],
      ["players", "birth_date", "DATE NULL"],
    ];

    foreach ($columns as [$table, $column, $definition]) {
      SqlDialect::ensureColumn($pdo, $table, $column, $definition);
    }
  }

  public static function seedDefaults(PDO $pdo): void
  {
    $seasonCount = (int)$pdo->query("SELECT COUNT(*) FROM seasons")->fetchColumn();
    if ($seasonCount === 0) {
      $pdo->prepare("INSERT INTO seasons (name, starts_at, status) VALUES (?, ?, 'active')")
        ->execute(["Temporada Actual", date("Y-m-d")]);
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
    if ($count === 0) {
      $teams = [
        ["Bucaneros", "bucaneros", "Broward County, FL"],
        ["Caribeños", "caribenos", "Broward County, FL"],
        ["Cerveceros", "cerveceros", "Broward County, FL"],
        ["Cubs", "cubs", "Broward County, FL"],
        ["Hispanos", "hispanos", "Broward County, FL"],
        ["Sharks", "sharks", "Broward County, FL"],
      ];
      $stmt = $pdo->prepare("INSERT INTO teams (name, slug, city, description) VALUES (?, ?, ?, ?)");
      foreach ($teams as $team) {
        $stmt->execute([$team[0], $team[1], $team[2], "Equipo activo de Legends Softball League 50+."]);
        $teamId = (int)$pdo->lastInsertId();
        SqlDialect::insertIgnore($pdo, "team_stats", ["team_id"], [$teamId]);
      }
    }
  }
}
