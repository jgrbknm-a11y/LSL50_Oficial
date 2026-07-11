<?php

namespace Lsl50\Services;

use PDO;
use SqlDialect;

/** Líderes ofensivos/defensivos — lee player_stats recalculados por StatsEngine. */
final class LeaderboardService
{
  public static function leagueGames(PDO $pdo, int $seasonId): int
  {
    $seasonId = (int)$seasonId;
    return (int)$pdo->query("SELECT COUNT(DISTINCT id) FROM games g
      WHERE COALESCE(g.season_id, $seasonId) = $seasonId
        AND EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id = g.id)")->fetchColumn();
  }

  public static function minPaSubquery(): string
  {
    return "(SELECT CAST((COUNT(*) * 3.1) + 0.999999 AS INTEGER)
      FROM games g
      WHERE (g.home_team_id = t.id OR g.away_team_id = t.id)
        AND EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id = g.id))";
  }

  public static function offensiveLeaders(
    PDO $pdo,
    string $expression,
    string $where,
    string $order,
    int $limit = 10,
    bool $qualifiedRate = false
  ): array {
    $qualification = $qualifiedRate ? " AND (ps.AB + ps.BB + ps.HBP + ps.SH + ps.SF) >= " . self::minPaSubquery() : "";
    $sql = "SELECT p.id, p.number, " . lsl_sql_full_name("p") . " player_name, t.name team_name,
        ps.games_played, ps.AB, ps.H, ps.dbl, ps.tpl, ps.HR, ps.RBI, ps.R, ps.BB, ps.SO, ps.SB,
        ps.HBP, ps.SH, ps.SF, ps.E, ps.TB, ps.AVG, ps.OBP, ps.SLG,
        (ps.AB + ps.BB + ps.HBP + ps.SH + ps.SF) PA, $expression AS leader_value
      FROM player_stats ps
      JOIN players p ON p.id = ps.player_id
      LEFT JOIN teams t ON t.id = p.team_id
      WHERE $where $qualification
      ORDER BY $order, ps.AB DESC, p.last_name, p.first_name
      LIMIT $limit";
    return $pdo->query($sql)->fetchAll();
  }

  public static function allBatters(PDO $pdo): array
  {
    $minPa = self::minPaSubquery();
    return $pdo->query("SELECT p.id, p.number, " . lsl_sql_full_name("p") . " player_name, t.name team_name,
        ps.games_played, ps.AB, ps.R, ps.H, ps.dbl, ps.tpl, ps.HR, ps.TB, ps.RBI, ps.BB, ps.SO, ps.SB,
        ps.HBP, ps.SH, ps.SF, ps.E, ps.AVG, ps.OBP, ps.SLG, (ps.OBP + ps.SLG) OPS,
        (ps.AB + ps.BB + ps.HBP + ps.SH + ps.SF) PA, $minPa min_pa
      FROM player_stats ps
      JOIN players p ON p.id = ps.player_id
      LEFT JOIN teams t ON t.id = p.team_id
      WHERE ps.AB > 0 OR ps.BB > 0 OR ps.HBP > 0 OR ps.SH > 0 OR ps.SF > 0
        OR ps.R > 0 OR ps.H > 0 OR ps.RBI > 0
      ORDER BY ps.H DESC, ps.RBI DESC, ps.AVG DESC, p.last_name, p.first_name")->fetchAll();
  }

  public static function pitcherWins(PDO $pdo, int $seasonId, int $limit = 10): array
  {
    $seasonId = (int)$seasonId;
    return $pdo->query("SELECT p.id, p.number, " . lsl_sql_full_name("p") . " player_name, t.name team_name,
        COUNT(*) wins
      FROM games g
      JOIN players p ON p.id = g.winning_pitcher_id
      LEFT JOIN teams t ON t.id = p.team_id
      WHERE COALESCE(g.season_id, $seasonId) = $seasonId
        AND g.winning_pitcher_id IS NOT NULL AND g.final_home != g.final_away
      GROUP BY p.id, p.number, p.first_name, p.last_name, t.name
      ORDER BY wins DESC, p.last_name, p.first_name
      LIMIT $limit")->fetchAll();
  }

  public static function pitchingRows(PDO $pdo, int $seasonId): array
  {
    self::ensurePitchingTable($pdo);
    self::syncPitchingWins($pdo, $seasonId);
    $seasonId = (int)$seasonId;

    return $pdo->query("SELECT p.id, p.number, " . lsl_sql_full_name("p") . " player_name, t.name team_name,
        COALESCE(pps.IP, 0) IP, COALESCE(pps.W, 0) W, COALESCE(pps.L, 0) L,
        COALESCE(pps.SO, 0) SO, COALESCE(pps.BB, 0) BB, COALESCE(pps.H, 0) H,
        COALESCE(pps.ER, 0) ER, COALESCE(pps.ERA, 0) ERA, COALESCE(pps.WHIP, 0) WHIP,
        COALESCE(pps.games_pitched, 0) GP
      FROM players p
      LEFT JOIN teams t ON t.id = p.team_id
      LEFT JOIN player_pitching_stats pps ON pps.player_id = p.id AND pps.season_id = $seasonId
      WHERE COALESCE(pps.W, 0) > 0 OR COALESCE(pps.IP, 0) > 0
        OR p.id IN (
          SELECT winning_pitcher_id FROM games
          WHERE COALESCE(season_id, $seasonId) = $seasonId AND winning_pitcher_id IS NOT NULL
        )
      ORDER BY COALESCE(pps.W, 0) DESC, CASE WHEN COALESCE(pps.ERA, 0) = 0 THEN 999 ELSE pps.ERA END ASC, p.last_name")->fetchAll();
  }

  public static function fmtLeaderValue($value, string $type): string
  {
    return $type === "avg" ? lsl_public_fmt_avg((float)$value) : (string)(int)$value;
  }

  public static function syncPitchingWins(PDO $pdo, int $seasonId): void
  {
    self::ensurePitchingTable($pdo);
    $seasonId = (int)$seasonId;
    $wins = $pdo->query("SELECT winning_pitcher_id player_id, COUNT(*) wins FROM games
      WHERE COALESCE(season_id, $seasonId) = $seasonId AND winning_pitcher_id IS NOT NULL AND final_home != final_away
      GROUP BY winning_pitcher_id")->fetchAll();

    foreach ($wins as $row) {
      $playerId = (int)$row["player_id"];
      if ($playerId <= 0) {
        continue;
      }
      if (SqlDialect::isSqlite($pdo)) {
        $pdo->prepare("INSERT INTO player_pitching_stats (player_id, season_id, W, updated_at)
          VALUES (?, ?, ?, CURRENT_TIMESTAMP)
          ON CONFLICT(player_id, season_id) DO UPDATE SET W = excluded.W, updated_at = CURRENT_TIMESTAMP")
          ->execute([$playerId, $seasonId, (int)$row["wins"]]);
      } elseif (SqlDialect::driver($pdo) === "mysql") {
        $pdo->prepare("INSERT INTO player_pitching_stats (player_id, season_id, W, updated_at)
          VALUES (?, ?, ?, CURRENT_TIMESTAMP)
          ON DUPLICATE KEY UPDATE W = VALUES(W), updated_at = CURRENT_TIMESTAMP")
          ->execute([$playerId, $seasonId, (int)$row["wins"]]);
      }
    }
  }

  public static function ensurePitchingTable(PDO $pdo): void
  {
    if (SqlDialect::isSqlite($pdo)) {
      $pdo->exec("CREATE TABLE IF NOT EXISTS player_pitching_stats (
        player_id INTEGER NOT NULL,
        season_id INTEGER NOT NULL,
        games_pitched INTEGER DEFAULT 0,
        IP REAL DEFAULT 0,
        W INTEGER DEFAULT 0,
        L INTEGER DEFAULT 0,
        SO INTEGER DEFAULT 0,
        BB INTEGER DEFAULT 0,
        H INTEGER DEFAULT 0,
        ER INTEGER DEFAULT 0,
        ERA REAL DEFAULT 0,
        WHIP REAL DEFAULT 0,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (player_id, season_id)
      )");
      return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS player_pitching_stats (
      player_id INT UNSIGNED NOT NULL,
      season_id INT UNSIGNED NOT NULL,
      games_pitched INT DEFAULT 0,
      IP DECIMAL(6,1) DEFAULT 0,
      W INT DEFAULT 0,
      L INT DEFAULT 0,
      SO INT DEFAULT 0,
      BB INT DEFAULT 0,
      H INT DEFAULT 0,
      ER INT DEFAULT 0,
      ERA DECIMAL(6,2) DEFAULT 0,
      WHIP DECIMAL(6,2) DEFAULT 0,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (player_id, season_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
}
