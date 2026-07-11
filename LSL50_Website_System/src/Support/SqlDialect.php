<?php

/**
 * Cross-database SQL helpers (SQLite, MySQL, PostgreSQL).
 */
final class SqlDialect
{
  public static function driver(?PDO $pdo = null): string
  {
    static $cached = null;
    if ($cached !== null) {
      return $cached;
    }

    $fromEnv = strtolower((string)(lsl_env("DB_DRIVER") ?: "sqlite"));
    if (in_array($fromEnv, ["sqlite", "mysql", "pgsql"], true)) {
      return $cached = $fromEnv;
    }

    if ($pdo instanceof PDO) {
      $name = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
      return $cached = match ($name) {
        "mysql" => "mysql",
        "pgsql" => "pgsql",
        default => "sqlite",
      };
    }

    return $cached = "sqlite";
  }

  public static function isSqlite(?PDO $pdo = null): bool
  {
    return self::driver($pdo) === "sqlite";
  }

  /** Full name without table alias (for subqueries). */
  public static function fullNameBare(): string
  {
    return match (self::driver()) {
      "mysql", "pgsql" => "CONCAT(first_name, ' ', last_name)",
      default => "first_name || ' ' || last_name",
    };
  }

  /** Full player name expression for SELECT lists. */
  public static function fullName(string $alias = "p"): string
  {
    $first = "{$alias}.first_name";
    $last = "{$alias}.last_name";
    return match (self::driver()) {
      "mysql", "pgsql" => "CONCAT({$first}, ' ', {$last})",
      default => "{$first} || ' ' || {$last}",
    };
  }

  public static function quoteIdent(string $ident): string
  {
    return match (self::driver()) {
      "mysql" => "`{$ident}`",
      "pgsql" => "\"{$ident}\"",
      default => $ident,
    };
  }

  public static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
  {
    if (self::isSqlite($pdo)) {
      $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
      foreach ($columns as $col) {
        if (($col["name"] ?? "") === $column) {
          return;
        }
      }
      try {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
      } catch (Throwable $e) {
        $message = strtolower($e->getMessage());
        if (!str_contains($message, "duplicate column")) {
          throw $e;
        }
      }
      return;
    }

    if (self::driver($pdo) === "mysql") {
      $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
      );
      $stmt->execute([$table, $column]);
      if ((int)$stmt->fetchColumn() > 0) {
        return;
      }
      $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
      return;
    }

    $stmt = $pdo->prepare(
      "SELECT COUNT(*) FROM information_schema.columns
       WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?"
    );
    $stmt->execute([$table, $column]);
    if ((int)$stmt->fetchColumn() > 0) {
      return;
    }
    $pdo->exec("ALTER TABLE \"{$table}\" ADD COLUMN \"{$column}\" {$definition}");
  }

  public static function insertIgnore(PDO $pdo, string $table, array $columns, array $values): void
  {
    $cols = implode(", ", array_map(fn($c) => self::isSqlite($pdo) ? $c : self::quoteIdent($c), $columns));
    $placeholders = implode(", ", array_fill(0, count($columns), "?"));
    $sql = match (self::driver($pdo)) {
      "mysql" => "INSERT IGNORE INTO `{$table}` ({$cols}) VALUES ({$placeholders})",
      "pgsql" => "INSERT INTO \"{$table}\" ({$cols}) VALUES ({$placeholders}) ON CONFLICT DO NOTHING",
      default => "INSERT OR IGNORE INTO {$table} ({$cols}) VALUES ({$placeholders})",
    };
    $pdo->prepare($sql)->execute($values);
  }

  public static function upsertAppSetting(PDO $pdo, string $key, string $value): void
  {
    if (self::isSqlite($pdo)) {
      $pdo->prepare(
        "INSERT INTO app_settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
         ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=CURRENT_TIMESTAMP"
      )->execute([$key, $value]);
      return;
    }

    if (self::driver($pdo) === "mysql") {
      $pdo->prepare(
        "INSERT INTO app_settings (`key`, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=CURRENT_TIMESTAMP"
      )->execute([$key, $value]);
      return;
    }

    $pdo->prepare(
      "INSERT INTO app_settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
       ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value, updated_at=CURRENT_TIMESTAMP"
    )->execute([$key, $value]);
  }

  public static function upsertPlayerStats(
    PDO $pdo,
    int $playerId,
    int $gamesPlayed,
    int $AB,
    int $H,
    int $DBL,
    int $TPL,
    int $TB,
    int $R,
    int $RBI,
    int $HR,
    int $BB,
    int $SO,
    int $SB,
    int $HBP,
    int $SH,
    int $SF,
    int $E,
    float $AVG,
    float $OBP,
    float $SLG
  ): void {
    $params = [$playerId, $gamesPlayed, $AB, $H, $DBL, $TPL, $TB, $R, $RBI, $HR, $BB, $SO, $SB, $HBP, $SH, $SF, $E, $AVG, $OBP, $SLG];

    if (self::isSqlite($pdo)) {
      $pdo->prepare(
        "INSERT INTO player_stats (player_id,games_played,AB,H,dbl,tpl,TB,R,RBI,HR,BB,SO,SB,HBP,SH,SF,E,AVG,OBP,SLG)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON CONFLICT(player_id) DO UPDATE SET games_played=excluded.games_played, AB=excluded.AB, H=excluded.H,
           dbl=excluded.dbl, tpl=excluded.tpl, TB=excluded.TB, R=excluded.R, RBI=excluded.RBI, HR=excluded.HR,
           BB=excluded.BB, SO=excluded.SO, SB=excluded.SB, HBP=excluded.HBP, SH=excluded.SH, SF=excluded.SF,
           E=excluded.E, AVG=excluded.AVG, OBP=excluded.OBP, SLG=excluded.SLG, updated_at=CURRENT_TIMESTAMP"
      )->execute($params);
      return;
    }

    if (self::driver($pdo) === "mysql") {
      $pdo->prepare(
        "INSERT INTO player_stats (player_id,games_played,AB,H,dbl,tpl,TB,R,RBI,HR,BB,SO,SB,HBP,SH,SF,E,AVG,OBP,SLG)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE games_played=VALUES(games_played), AB=VALUES(AB), H=VALUES(H),
           dbl=VALUES(dbl), tpl=VALUES(tpl), TB=VALUES(TB), R=VALUES(R), RBI=VALUES(RBI), HR=VALUES(HR),
           BB=VALUES(BB), SO=VALUES(SO), SB=VALUES(SB), HBP=VALUES(HBP), SH=VALUES(SH), SF=VALUES(SF),
           E=VALUES(E), AVG=VALUES(AVG), OBP=VALUES(OBP), SLG=VALUES(SLG), updated_at=CURRENT_TIMESTAMP"
      )->execute($params);
      return;
    }

    $pdo->prepare(
      "INSERT INTO player_stats (player_id,games_played,AB,H,dbl,tpl,TB,R,RBI,HR,BB,SO,SB,HBP,SH,SF,E,AVG,OBP,SLG)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
       ON CONFLICT (player_id) DO UPDATE SET games_played=EXCLUDED.games_played, AB=EXCLUDED.AB, H=EXCLUDED.H,
         dbl=EXCLUDED.dbl, tpl=EXCLUDED.tpl, TB=EXCLUDED.TB, R=EXCLUDED.R, RBI=EXCLUDED.RBI, HR=EXCLUDED.HR,
         BB=EXCLUDED.BB, SO=EXCLUDED.SO, SB=EXCLUDED.SB, HBP=EXCLUDED.HBP, SH=EXCLUDED.SH, SF=EXCLUDED.SF,
         E=EXCLUDED.E, AVG=EXCLUDED.AVG, OBP=EXCLUDED.OBP, SLG=EXCLUDED.SLG, updated_at=CURRENT_TIMESTAMP"
    )->execute($params);
  }

  public static function greatest(string $left, string $right): string
  {
    return match (self::driver()) {
      "mysql" => "GREATEST({$left}, {$right})",
      "pgsql" => "GREATEST({$left}, {$right})",
      default => "MAX({$left}, {$right})",
    };
  }

  public static function orderByUniformNumber(string $alias = "p"): string
  {
    $col = "{$alias}.number";
    return match (self::driver()) {
      "mysql" => "CAST(NULLIF({$col}, '') AS UNSIGNED)",
      default => "CAST(NULLIF({$col}, '') AS INTEGER)",
    };
  }

  public static function upsertLineup(
    PDO $pdo,
    int $seasonId,
    int $gameId,
    int $teamId,
    int $battingOrder,
    int $playerId,
    string $position
  ): void {
    if (self::isSqlite($pdo)) {
      $pdo->prepare(
        "INSERT INTO game_lineups (season_id,game_id,team_id,batting_order,player_id,field_position,active,updated_at)
         VALUES (?,?,?,?,?,?,1,CURRENT_TIMESTAMP)
         ON CONFLICT(game_id, team_id, batting_order) DO UPDATE SET player_id=excluded.player_id, field_position=excluded.field_position, active=1, updated_at=CURRENT_TIMESTAMP"
      )->execute([$seasonId, $gameId, $teamId, $battingOrder, $playerId, $position]);
      return;
    }

    if (self::driver($pdo) === "mysql") {
      $pdo->prepare(
        "INSERT INTO game_lineups (season_id,game_id,team_id,batting_order,player_id,field_position,active,updated_at)
         VALUES (?,?,?,?,?,?,1,CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE player_id=VALUES(player_id), field_position=VALUES(field_position), active=1, updated_at=CURRENT_TIMESTAMP"
      )->execute([$seasonId, $gameId, $teamId, $battingOrder, $playerId, $position]);
      return;
    }

    $pdo->prepare(
      "INSERT INTO game_lineups (season_id,game_id,team_id,batting_order,player_id,field_position,active,updated_at)
       VALUES (?,?,?,?,?,?,1,CURRENT_TIMESTAMP)
       ON CONFLICT (game_id, team_id, batting_order) DO UPDATE SET player_id=EXCLUDED.player_id, field_position=EXCLUDED.field_position, active=1, updated_at=CURRENT_TIMESTAMP"
    )->execute([$seasonId, $gameId, $teamId, $battingOrder, $playerId, $position]);
  }

  public static function upsertBorrowedPlayer(
    PDO $pdo,
    int $seasonId,
    int $gameId,
    int $playerId,
    int $originalTeamId,
    int $borrowedTeamId,
    string $reason,
    string $approvedBy
  ): void {
    if (self::isSqlite($pdo)) {
      $pdo->prepare(
        "INSERT INTO game_borrowed_players (season_id,game_id,player_id,original_team_id,borrowed_team_id,reason,approved_by,active)
         VALUES (?,?,?,?,?,?,?,1)
         ON CONFLICT(game_id, player_id, borrowed_team_id) DO UPDATE SET active=1, reason=excluded.reason, approved_by=excluded.approved_by"
      )->execute([$seasonId, $gameId, $playerId, $originalTeamId, $borrowedTeamId, $reason, $approvedBy]);
      return;
    }

    if (self::driver($pdo) === "mysql") {
      $pdo->prepare(
        "INSERT INTO game_borrowed_players (season_id,game_id,player_id,original_team_id,borrowed_team_id,reason,approved_by,active)
         VALUES (?,?,?,?,?,?,?,1)
         ON DUPLICATE KEY UPDATE active=1, reason=VALUES(reason), approved_by=VALUES(approved_by)"
      )->execute([$seasonId, $gameId, $playerId, $originalTeamId, $borrowedTeamId, $reason, $approvedBy]);
      return;
    }

    $pdo->prepare(
      "INSERT INTO game_borrowed_players (season_id,game_id,player_id,original_team_id,borrowed_team_id,reason,approved_by,active)
       VALUES (?,?,?,?,?,?,?,1)
       ON CONFLICT (game_id, player_id, borrowed_team_id) DO UPDATE SET active=1, reason=EXCLUDED.reason, approved_by=EXCLUDED.approved_by"
    )->execute([$seasonId, $gameId, $playerId, $originalTeamId, $borrowedTeamId, $reason, $approvedBy]);
  }

  public static function upsertAiGameNote(
    PDO $pdo,
    int $seasonId,
    int $gameId,
    string $status,
    string $title,
    string $summary,
    string $body,
    string $videoUrl,
    int $clipStart,
    int $clipEnd,
    string $highlightReason,
    string $provider,
    ?string $publishedAt
  ): void {
    $params = [
      $seasonId,
      $gameId,
      $status,
      $title,
      $summary,
      $body,
      $videoUrl,
      $clipStart,
      $clipEnd,
      $highlightReason,
      $provider,
      $publishedAt,
    ];

    if (self::isSqlite($pdo)) {
      $pdo->prepare(
        "INSERT INTO ai_game_notes
          (season_id, game_id, status, title, summary, body, video_url, clip_start_seconds, clip_end_seconds, highlight_reason, provider, published_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
         ON CONFLICT(game_id) DO UPDATE SET
           status=excluded.status, title=excluded.title, summary=excluded.summary, body=excluded.body,
           video_url=excluded.video_url, clip_start_seconds=excluded.clip_start_seconds,
           clip_end_seconds=excluded.clip_end_seconds, highlight_reason=excluded.highlight_reason,
           provider=excluded.provider, published_at=COALESCE(excluded.published_at, ai_game_notes.published_at),
           updated_at=CURRENT_TIMESTAMP"
      )->execute($params);
      return;
    }

    if (self::driver($pdo) === "mysql") {
      $pdo->prepare(
        "INSERT INTO ai_game_notes
          (season_id, game_id, status, title, summary, body, video_url, clip_start_seconds, clip_end_seconds, highlight_reason, provider, published_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE
           status=VALUES(status), title=VALUES(title), summary=VALUES(summary), body=VALUES(body),
           video_url=VALUES(video_url), clip_start_seconds=VALUES(clip_start_seconds),
           clip_end_seconds=VALUES(clip_end_seconds), highlight_reason=VALUES(highlight_reason),
           provider=VALUES(provider), published_at=COALESCE(VALUES(published_at), ai_game_notes.published_at),
           updated_at=CURRENT_TIMESTAMP"
      )->execute($params);
      return;
    }

    $pdo->prepare(
      "INSERT INTO ai_game_notes
        (season_id, game_id, status, title, summary, body, video_url, clip_start_seconds, clip_end_seconds, highlight_reason, provider, published_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
       ON CONFLICT (game_id) DO UPDATE SET
         status=EXCLUDED.status, title=EXCLUDED.title, summary=EXCLUDED.summary, body=EXCLUDED.body,
         video_url=EXCLUDED.video_url, clip_start_seconds=EXCLUDED.clip_start_seconds,
         clip_end_seconds=EXCLUDED.clip_end_seconds, highlight_reason=EXCLUDED.highlight_reason,
         provider=EXCLUDED.provider, published_at=COALESCE(EXCLUDED.published_at, ai_game_notes.published_at),
         updated_at=CURRENT_TIMESTAMP"
    )->execute($params);
  }

  public static function execSqlFile(PDO $pdo, string $path): void
  {
    if (!is_file($path)) {
      throw new RuntimeException("SQL file not found: {$path}");
    }
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === "") {
      throw new RuntimeException("SQL file empty: {$path}");
    }

    if (self::isSqlite($pdo)) {
      $pdo->exec($sql);
      return;
    }

    $statements = preg_split('/;\s*\n/', $sql) ?: [];
    foreach ($statements as $statement) {
      $statement = trim($statement);
      if ($statement === "" || str_starts_with($statement, "--")) {
        continue;
      }
      $pdo->exec($statement);
    }
  }
}

function lsl_sql_full_name_bare(): string
{
  return SqlDialect::fullNameBare();
}

function lsl_sql_full_name(string $alias = "p"): string
{
  return SqlDialect::fullName($alias);
}

function lsl_db_driver(?PDO $pdo = null): string
{
  return SqlDialect::driver($pdo);
}
