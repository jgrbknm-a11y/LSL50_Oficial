<?php

namespace Lsl50\Services;

use PDO;

/**
 * Motor central de estadísticas LSL50.
 * Recalcula agregados desde game_player_stats y expone métricas avanzadas
 * para standings, /estadisticas, /bateo-general y /pitcheo-general.
 */
final class StatsEngine
{
  public static function recalcAll(PDO $pdo, int $seasonId, array $playerIds = []): void
  {
    $seasonId = (int)$seasonId;
    foreach ($playerIds as $playerId) {
      if ((int)$playerId > 0) {
        lsl_recalc_player_stats($pdo, (int)$playerId, $seasonId);
      }
    }
    lsl_recalc_team_stats($pdo, $seasonId);
    self::syncPitching($pdo, $seasonId);
  }

  public static function recalcFromGame(PDO $pdo, int $seasonId, int $gameId): void
  {
    $seasonId = (int)$seasonId;
    $stmt = $pdo->prepare("SELECT DISTINCT player_id FROM game_player_stats WHERE game_id=?");
    $stmt->execute([$gameId]);
    $ids = array_map(static fn($r) => (int)$r["player_id"], $stmt->fetchAll());
    self::recalcAll($pdo, $seasonId, $ids);
  }

  public static function syncPitching(PDO $pdo, int $seasonId): void
  {
    LeaderboardService::ensurePitchingTable($pdo);
    LeaderboardService::syncPitchingWins($pdo, $seasonId);
    self::recalcPitchingRates($pdo, $seasonId);
  }

  public static function recalcPitchingRates(PDO $pdo, int $seasonId): void
  {
    LeaderboardService::ensurePitchingTable($pdo);
    $seasonId = (int)$seasonId;
    $rows = $pdo->query("SELECT player_id, IP, ER, H, BB, ERA, WHIP FROM player_pitching_stats WHERE season_id = $seasonId")->fetchAll();
    foreach ($rows as $row) {
      $ip = (float)$row["IP"];
      $er = (int)$row["ER"];
      $h = (int)$row["H"];
      $bb = (int)$row["BB"];
      $era = $ip > 0 ? round(($er * 9) / $ip, 2) : 0.0;
      $whip = $ip > 0 ? round(($bb + $h) / $ip, 2) : 0.0;
      if ((float)$row["ERA"] === $era && (float)$row["WHIP"] === $whip) {
        continue;
      }
      $pdo->prepare("UPDATE player_pitching_stats SET ERA=?, WHIP=?, updated_at=CURRENT_TIMESTAMP WHERE player_id=? AND season_id=?")
        ->execute([$era, $whip, (int)$row["player_id"], $seasonId]);
    }
  }

  public static function standings(PDO $pdo, int $seasonId, bool $activeOnly = true): array
  {
    return PublicStatsService::standings($pdo, $seasonId, $activeOnly);
  }

  public static function leagueGames(PDO $pdo, int $seasonId): int
  {
    return LeaderboardService::leagueGames($pdo, $seasonId);
  }

  /** @return list<array<string, mixed>> */
  public static function offensiveDepartments(string $scope = "public"): array
  {
    return match (strtolower(trim($scope))) {
      "full", "admin" => self::adminOffensiveDepartments(),
      default => self::publicOffensiveDepartments(),
    };
  }

  /** @return list<array<string, mixed>> */
  public static function featuredOffensiveDepartments(): array
  {
    return [
      ["title" => "Mejor promedio", "abbr" => "AVG", "expr" => "ps.AVG", "where" => "ps.AB > 0", "order" => "ps.AVG DESC", "type" => "avg", "qualified" => true],
      ["title" => "Más hits", "abbr" => "H", "expr" => "ps.H", "where" => "ps.H > 0", "order" => "ps.H DESC", "type" => "int"],
      ["title" => "Más impulsadas", "abbr" => "RBI", "expr" => "ps.RBI", "where" => "ps.RBI > 0", "order" => "ps.RBI DESC", "type" => "int"],
      ["title" => "Más anotadas", "abbr" => "R", "expr" => "ps.R", "where" => "ps.R > 0", "order" => "ps.R DESC", "type" => "int"],
    ];
  }

  /** @return list<array<string, mixed>> */
  private static function publicOffensiveDepartments(): array
  {
    return [
      ["title" => "Champion Bate", "abbr" => "AVG", "expr" => "ps.AVG", "where" => "ps.AB > 0", "order" => "ps.AVG DESC", "type" => "avg", "qualified" => true, "featured" => true],
      ["title" => "Jonrones", "abbr" => "HR", "expr" => "ps.HR", "where" => "ps.HR > 0", "order" => "ps.HR DESC", "type" => "int"],
      ["title" => "Dobles", "abbr" => "2B", "expr" => "ps.dbl", "where" => "ps.dbl > 0", "order" => "ps.dbl DESC", "type" => "int"],
      ["title" => "Triples", "abbr" => "3B", "expr" => "ps.tpl", "where" => "ps.tpl > 0", "order" => "ps.tpl DESC", "type" => "int"],
      ["title" => "Carreras Impulsadas", "abbr" => "RBI", "expr" => "ps.RBI", "where" => "ps.RBI > 0", "order" => "ps.RBI DESC", "type" => "int"],
      ["title" => "Promedio", "abbr" => "AVG", "expr" => "ps.AVG", "where" => "ps.AB > 0", "order" => "ps.AVG DESC", "type" => "avg", "qualified" => true],
      ["title" => "Hits", "abbr" => "H", "expr" => "ps.H", "where" => "ps.H > 0", "order" => "ps.H DESC", "type" => "int"],
      ["title" => "Anotadas", "abbr" => "R", "expr" => "ps.R", "where" => "ps.R > 0", "order" => "ps.R DESC", "type" => "int"],
      ["title" => "Embazado", "abbr" => "OBP", "expr" => "ps.OBP", "where" => "ps.AB + ps.BB + ps.HBP + ps.SF > 0", "order" => "ps.OBP DESC", "type" => "avg", "qualified" => true],
      ["title" => "Slugging", "abbr" => "SLG", "expr" => "ps.SLG", "where" => "ps.AB > 0", "order" => "ps.SLG DESC", "type" => "avg", "qualified" => true],
      ["title" => "OPS", "abbr" => "OPS", "expr" => "(ps.OBP + ps.SLG)", "where" => "ps.AB > 0", "order" => "(ps.OBP + ps.SLG) DESC", "type" => "avg", "qualified" => true],
      ["title" => "Bases por bolas", "abbr" => "BB", "expr" => "ps.BB", "where" => "ps.BB > 0", "order" => "ps.BB DESC", "type" => "int"],
      ["title" => "Ponches", "abbr" => "SO", "expr" => "ps.SO", "where" => "ps.SO > 0", "order" => "ps.SO DESC", "type" => "int"],
      ["title" => "Bases robadas", "abbr" => "SB", "expr" => "ps.SB", "where" => "ps.SB > 0", "order" => "ps.SB DESC", "type" => "int"],
      ["title" => "Bases totales", "abbr" => "TB", "expr" => "ps.TB", "where" => "ps.TB > 0", "order" => "ps.TB DESC", "type" => "int"],
    ];
  }

  /** @return list<array<string, mixed>> */
  private static function adminOffensiveDepartments(): array
  {
    return [
      ["title" => "Promedio", "abbr" => "AVG", "expr" => "ps.AVG", "where" => "ps.AB > 0", "order" => "ps.AVG DESC", "type" => "avg", "qualified" => true],
      ["title" => "Hits", "abbr" => "H", "expr" => "ps.H", "where" => "ps.H > 0", "order" => "ps.H DESC", "type" => "int"],
      ["title" => "Dobles", "abbr" => "2B", "expr" => "ps.dbl", "where" => "ps.dbl > 0", "order" => "ps.dbl DESC", "type" => "int"],
      ["title" => "Triples", "abbr" => "3B", "expr" => "ps.tpl", "where" => "ps.tpl > 0", "order" => "ps.tpl DESC", "type" => "int"],
      ["title" => "Jonrones", "abbr" => "HR", "expr" => "ps.HR", "where" => "ps.HR > 0", "order" => "ps.HR DESC", "type" => "int"],
      ["title" => "Impulsadas", "abbr" => "RBI", "expr" => "ps.RBI", "where" => "ps.RBI > 0", "order" => "ps.RBI DESC", "type" => "int"],
      ["title" => "Anotadas", "abbr" => "R", "expr" => "ps.R", "where" => "ps.R > 0", "order" => "ps.R DESC", "type" => "int"],
      ["title" => "Bases por bolas", "abbr" => "BB", "expr" => "ps.BB", "where" => "ps.BB > 0", "order" => "ps.BB DESC", "type" => "int"],
      ["title" => "Golpeados", "abbr" => "HBP", "expr" => "ps.HBP", "where" => "ps.HBP > 0", "order" => "ps.HBP DESC", "type" => "int"],
      ["title" => "Toques sacrificio", "abbr" => "SH", "expr" => "ps.SH", "where" => "ps.SH > 0", "order" => "ps.SH DESC", "type" => "int"],
      ["title" => "Elevados sacrificio", "abbr" => "SF", "expr" => "ps.SF", "where" => "ps.SF > 0", "order" => "ps.SF DESC", "type" => "int"],
      ["title" => "Ponches recibidos", "abbr" => "SO", "expr" => "ps.SO", "where" => "ps.SO > 0", "order" => "ps.SO DESC", "type" => "int"],
      ["title" => "Bases robadas", "abbr" => "SB", "expr" => "ps.SB", "where" => "ps.SB > 0", "order" => "ps.SB DESC", "type" => "int"],
      ["title" => "Errores defensivos", "abbr" => "E", "expr" => "ps.E", "where" => "ps.E > 0", "order" => "ps.E DESC", "type" => "int"],
      ["title" => "Embazado", "abbr" => "OBP", "expr" => "ps.OBP", "where" => "ps.AB + ps.BB + ps.HBP + ps.SF > 0", "order" => "ps.OBP DESC", "type" => "avg", "qualified" => true],
      ["title" => "Slugging", "abbr" => "SLG", "expr" => "ps.SLG", "where" => "ps.AB > 0", "order" => "ps.SLG DESC", "type" => "avg", "qualified" => true],
      ["title" => "OPS", "abbr" => "OPS", "expr" => "(ps.OBP + ps.SLG)", "where" => "ps.AB > 0", "order" => "(ps.OBP + ps.SLG) DESC", "type" => "avg", "qualified" => true],
      ["title" => "Bases totales", "abbr" => "TB", "expr" => "ps.TB", "where" => "ps.TB > 0", "order" => "ps.TB DESC", "type" => "int"],
      ["title" => "Turnos", "abbr" => "AB", "expr" => "ps.AB", "where" => "ps.AB > 0", "order" => "ps.AB DESC", "type" => "int"],
      ["title" => "Juegos legales", "abbr" => "GP", "expr" => "ps.games_played", "where" => "ps.games_played > 0", "order" => "ps.games_played DESC", "type" => "int"],
    ];
  }

  /** @return list<array<string, mixed>> */
  public static function teamMinPlateAppearances(PDO $pdo, int $seasonId): array
  {
    $seasonId = (int)$seasonId;
    return $pdo->query("SELECT t.name, COUNT(DISTINCT g.id) scored_games,
        CAST((COUNT(DISTINCT g.id) * 3.1) + 0.999999 AS INTEGER) min_pa
      FROM teams t
      LEFT JOIN games g ON (g.home_team_id = t.id OR g.away_team_id = t.id)
        AND COALESCE(g.season_id, $seasonId) = $seasonId
        AND EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id = g.id)
      GROUP BY t.id, t.name
      ORDER BY t.name")->fetchAll();
  }

  /** @return list<array<string, mixed>> */
  public static function pitcherWinLeaders(PDO $pdo, int $seasonId, int $limit = 10): array
  {
    return LeaderboardService::pitcherWins($pdo, $seasonId, max(1, min(50, $limit)));
  }

  public static function offensiveLeaders(
    PDO $pdo,
    string $expression,
    string $where,
    string $order,
    int $limit = 10,
    bool $qualifiedRate = false
  ): array {
    return LeaderboardService::offensiveLeaders($pdo, $expression, $where, $order, $limit, $qualifiedRate);
  }

  public static function battingTable(PDO $pdo, int $seasonId): array
  {
    $rows = LeaderboardService::allBatters($pdo);
    $out = [];
    foreach ($rows as $row) {
      $out[] = self::enrichBatterRow($row);
    }
    return $out;
  }

  public static function enrichBatterRow(array $row): array
  {
    $avg = (float)($row["AVG"] ?? 0);
    $slg = (float)($row["SLG"] ?? 0);
    $obp = (float)($row["OBP"] ?? 0);
    $ops = (float)($row["OPS"] ?? ($obp + $slg));
    $pa = (int)($row["PA"] ?? 0);
    $minPa = (int)($row["min_pa"] ?? 0);
    $ab = (int)($row["AB"] ?? 0);
    $h = (int)($row["H"] ?? 0);
    $hr = (int)($row["HR"] ?? 0);
    $bb = (int)($row["BB"] ?? 0);
    $sf = (int)($row["SF"] ?? 0);

    $iso = round($slg - $avg, 3);
    $babipDen = $ab - $hr - $sf + max(0, $sf);
    $babip = $babipDen > 0 ? round(($h - $hr) / $babipDen, 3) : 0.0;

    $row["OPS"] = $ops;
    $row["ISO"] = $iso;
    $row["BABIP"] = $babip;
    $row["qualified"] = $pa >= $minPa && $minPa > 0;
    $row["qual_label"] = $row["qualified"] ? "OK" : ($minPa > 0 ? "{$pa}/{$minPa}" : "N/A");
    return $row;
  }

  public static function pitchingTable(PDO $pdo, int $seasonId): array
  {
    self::syncPitching($pdo, $seasonId);
    $rows = LeaderboardService::pitchingRows($pdo, $seasonId);
    $out = [];
    foreach ($rows as $row) {
      $ip = (float)($row["IP"] ?? 0);
      $er = (int)($row["ER"] ?? 0);
      $h = (int)($row["H"] ?? 0);
      $bb = (int)($row["BB"] ?? 0);
      $so = (int)($row["SO"] ?? 0);
      $w = (int)($row["W"] ?? $row["wins"] ?? 0);
      $era = $ip > 0 ? round(($er * 9) / $ip, 2) : (float)($row["ERA"] ?? 0);
      $whip = $ip > 0 ? round(($bb + $h) / $ip, 2) : (float)($row["WHIP"] ?? 0);
      $k9 = $ip > 0 ? round(($so * 9) / $ip, 1) : 0.0;
      $bb9 = $ip > 0 ? round(($bb * 9) / $ip, 1) : 0.0;
      $row["ERA"] = $era;
      $row["WHIP"] = $whip;
      $row["K9"] = $k9;
      $row["BB9"] = $bb9;
      $row["W"] = $w;
      $out[] = $row;
    }
    return $out;
  }

  public static function gameBoxSummary(PDO $pdo, int $gameId): array
  {
    $stmt = $pdo->prepare("SELECT gps.team_id, t.name team_name,
        COALESCE(SUM(gps.R),0) runs,
        COALESCE(SUM(gps.H),0) hits,
        COALESCE(SUM(gps.HR),0) hrs,
        COALESCE(SUM(gps.RBI),0) rbi,
        COALESCE(SUM(gps.E),0) errors,
        COALESCE(SUM(gps.BB),0) walks,
        COALESCE(SUM(gps.SO),0) strikeouts
      FROM game_player_stats gps
      JOIN teams t ON t.id = gps.team_id
      WHERE gps.game_id = ?
      GROUP BY gps.team_id, t.name
      ORDER BY t.name");
    $stmt->execute([$gameId]);
    $teams = $stmt->fetchAll();

    $mvpStmt = $pdo->prepare("SELECT p.id player_id, p.number, " . lsl_sql_full_name("p") . " player_name,
        t.name team_name, gps.R, gps.RBI, gps.H, gps.HR, gps.AB,
        (gps.R * 2 + gps.RBI * 2 + gps.H + gps.HR * 4) mvp_score
      FROM game_player_stats gps
      JOIN players p ON p.id = gps.player_id
      JOIN teams t ON t.id = gps.team_id
      WHERE gps.game_id = ?
      ORDER BY mvp_score DESC, gps.RBI DESC, gps.H DESC
      LIMIT 1");
    $mvpStmt->execute([$gameId]);
    $mvp = $mvpStmt->fetch() ?: null;

    return [
      "teams" => $teams,
      "mvp" => $mvp,
    ];
  }

  public static function fmtLeaderValue($value, string $type): string
  {
    return LeaderboardService::fmtLeaderValue($value, $type);
  }
}
