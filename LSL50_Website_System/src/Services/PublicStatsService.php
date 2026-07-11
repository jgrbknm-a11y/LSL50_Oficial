<?php

namespace Lsl50\Services;

use PDO;
use SqlDialect;

/** Standings, calendario, resultados — datos públicos desde team_stats / games. */
final class PublicStatsService
{
  public static function standings(PDO $pdo, int $seasonId, bool $activeTeamsOnly = true): array
  {
    $seasonId = (int)$seasonId;
    $where = $activeTeamsOnly
      ? "WHERE EXISTS (
          SELECT 1 FROM games g
          WHERE COALESCE(g.season_id, $seasonId) = $seasonId
            AND (g.home_team_id = t.id OR g.away_team_id = t.id)
            AND (
              EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id = g.id)
              OR g.final_home > 0 OR g.final_away > 0
              OR COALESCE(g.status, '') = 'final'
            )
        )"
      : "";

    $rows = $pdo->query("SELECT t.id, t.name, t.slug, t.logo_url,
        COALESCE(ts.wins,0) wins, COALESCE(ts.losses,0) losses, COALESCE(ts.ties,0) ties,
        COALESCE(ts.runs_for,0) runs_for, COALESCE(ts.runs_against,0) runs_against
      FROM teams t
      LEFT JOIN team_stats ts ON ts.team_id = t.id
      $where
      ORDER BY wins DESC, ties DESC, (runs_for - runs_against) DESC, runs_for DESC, t.name")->fetchAll();

    $leaderWins = $rows ? (int)$rows[0]["wins"] : 0;
    $out = [];
    $pos = 0;
    foreach ($rows as $row) {
      $pos++;
      $w = (int)$row["wins"];
      $l = (int)$row["losses"];
      $t = (int)$row["ties"];
      $gp = $w + $l + $t;
      $rf = (int)$row["runs_for"];
      $ra = (int)$row["runs_against"];
      $teamId = (int)$row["id"];
      $form = self::teamForm($pdo, $seasonId, $teamId);

      $out[] = [
        "pos" => $pos,
        "team_id" => $teamId,
        "name" => $row["name"],
        "slug" => $row["slug"],
        "logo_url" => $row["logo_url"],
        "wins" => $w,
        "losses" => $l,
        "ties" => $t,
        "gp" => $gp,
        "pct" => $gp > 0 ? $w / $gp : 0,
        "pct_fmt" => lsl_public_fmt_pct($w, $l, $t),
        "runs_for" => $rf,
        "runs_against" => $ra,
        "run_diff" => $rf - $ra,
        "gb" => $leaderWins > $w ? number_format($leaderWins - $w, 1, ".", "") : "-",
        "streak" => $form["streak"],
        "l10" => $form["l10"],
        "record" => lsl_public_fmt_record($w, $l, $t),
      ];
    }
    return $out;
  }

  /** @return array{streak: string, l10: string} */
  public static function teamForm(PDO $pdo, int $seasonId, int $teamId): array
  {
    $results = self::teamResults($pdo, $seasonId, $teamId);
    if (!$results) {
      return ["streak" => "-", "l10" => "-"];
    }

    $first = $results[0];
    $streakCount = 0;
    foreach ($results as $r) {
      if ($r === $first) {
        $streakCount++;
      } else {
        break;
      }
    }
    $streak = ($first === "T" ? "E" : $first) . $streakCount;

    $last10 = array_slice($results, 0, 10);
    $w10 = count(array_filter($last10, fn($r) => $r === "W"));
    $l10 = count(array_filter($last10, fn($r) => $r === "L"));
    $t10 = count(array_filter($last10, fn($r) => $r === "T"));

    return [
      "streak" => $streak,
      "l10" => lsl_public_fmt_record($w10, $l10, $t10),
    ];
  }

  /** @return list<'W'|'L'|'T'> newest first */
  public static function teamResults(PDO $pdo, int $seasonId, int $teamId): array
  {
    $seasonId = (int)$seasonId;
    $stmt = $pdo->prepare("SELECT g.* FROM games g
      WHERE COALESCE(g.season_id, $seasonId) = $seasonId
        AND (g.home_team_id = ? OR g.away_team_id = ?)
        AND (
          EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id = g.id)
          OR g.final_home > 0 OR g.final_away > 0
          OR COALESCE(g.status, '') = 'final'
        )
      ORDER BY g.game_date DESC, g.id DESC");
    $stmt->execute([$teamId, $teamId]);
    $results = [];
    foreach ($stmt->fetchAll() as $g) {
      $home = (int)$g["final_home"];
      $away = (int)$g["final_away"];
      $isHome = (int)$g["home_team_id"] === $teamId;
      if ($home === $away) {
        $results[] = "T";
      } elseif (($isHome && $home > $away) || (!$isHome && $away > $home)) {
        $results[] = "W";
      } else {
        $results[] = "L";
      }
    }
    return $results;
  }

  public static function latestGameDay(PDO $pdo, int $seasonId): ?string
  {
    $seasonId = (int)$seasonId;
    $date = $pdo->query("SELECT MAX(g.game_date) FROM games g
      WHERE COALESCE(g.season_id, $seasonId) = $seasonId
        AND (
          EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id = g.id)
          OR g.final_home > 0 OR g.final_away > 0
        )")->fetchColumn();
    return $date ? (string)$date : null;
  }

  public static function gamesOnDate(PDO $pdo, int $seasonId, string $date): array
  {
    $seasonId = (int)$seasonId;
    $stmt = $pdo->prepare("SELECT g.*, ht.name home_name, ht.logo_url home_logo, at.name away_name, at.logo_url away_logo
      FROM games g
      JOIN teams ht ON ht.id = g.home_team_id
      JOIN teams at ON at.id = g.away_team_id
      WHERE COALESCE(g.season_id, $seasonId) = $seasonId AND g.game_date = ?
        AND (
          EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id = g.id)
          OR g.final_home > 0 OR g.final_away > 0
          OR COALESCE(g.status, '') = 'final'
        )
      ORDER BY g.id");
    $stmt->execute([$date]);
    return $stmt->fetchAll();
  }

  public static function gameLineTotals(PDO $pdo, array $gameIds): array
  {
    if (!$gameIds) {
      return [];
    }
    $placeholders = implode(",", array_fill(0, count($gameIds), "?"));
    $hitExpr = SqlDialect::greatest("H", "dbl + tpl + HR");
    $stmt = $pdo->prepare("SELECT game_id, team_id,
        COALESCE(SUM({$hitExpr}),0) hits, COALESCE(SUM(E),0) errors
      FROM game_player_stats
      WHERE game_id IN ($placeholders)
      GROUP BY game_id, team_id");
    $stmt->execute($gameIds);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
      $out[(int)$row["game_id"]][(int)$row["team_id"]] = [
        "hits" => (int)$row["hits"],
        "errors" => (int)$row["errors"],
      ];
    }
    return $out;
  }

  public static function recentGameDays(PDO $pdo, int $seasonId, int $limit = 4): array
  {
    $seasonId = (int)$seasonId;
    $stmt = $pdo->prepare("SELECT DISTINCT g.game_date FROM games g
      WHERE COALESCE(g.season_id, $seasonId) = $seasonId
        AND (
          EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id = g.id)
          OR g.final_home > 0 OR g.final_away > 0
        )
      ORDER BY g.game_date DESC
      LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return array_column($stmt->fetchAll(), "game_date");
  }

  public static function recentResults(PDO $pdo, int $seasonId, int $limit = 8): array
  {
    $seasonId = (int)$seasonId;
    $stmt = $pdo->prepare("SELECT g.*, ht.name home_name, ht.logo_url home_logo, at.name away_name, at.logo_url away_logo
      FROM games g
      JOIN teams ht ON ht.id = g.home_team_id
      JOIN teams at ON at.id = g.away_team_id
      WHERE COALESCE(g.season_id, $seasonId) = $seasonId
        AND (
          EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id = g.id)
          OR g.final_home > 0 OR g.final_away > 0
        )
      ORDER BY g.game_date DESC, g.id DESC
      LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
  }

  public static function upcomingGames(PDO $pdo, int $seasonId, int $limit = 6): array
  {
    $seasonId = (int)$seasonId;
    $today = date("Y-m-d");
    if (SqlDialect::isSqlite($pdo)) {
      $sql = "SELECT s.*, ht.name home_name, ht.logo_url home_logo, at.name away_name, at.logo_url away_logo
        FROM schedule_entries s
        LEFT JOIN teams ht ON ht.id = s.home_team_id
        LEFT JOIN teams at ON at.id = s.away_team_id
        WHERE s.season_id = $seasonId AND s.game_date >= date('now')
        ORDER BY s.game_date, s.game_time, s.id
        LIMIT $limit";
      return $pdo->query($sql)->fetchAll();
    }
    $stmt = $pdo->prepare("SELECT s.*, ht.name home_name, ht.logo_url home_logo, at.name away_name, at.logo_url away_logo
      FROM schedule_entries s
      LEFT JOIN teams ht ON ht.id = s.home_team_id
      LEFT JOIN teams at ON at.id = s.away_team_id
      WHERE s.season_id = ? AND s.game_date >= ?
      ORDER BY s.game_date, s.game_time, s.id
      LIMIT ?");
    $stmt->execute([$seasonId, $today, $limit]);
    return $stmt->fetchAll();
  }

  /** @return array<string, list<array>> keyed by Y-m */
  public static function calendarByMonth(PDO $pdo, int $seasonId): array
  {
    $events = self::calendarEvents($pdo, $seasonId);
    $grouped = [];
    foreach ($events as $event) {
      $monthKey = substr($event["game_date"], 0, 7);
      $grouped[$monthKey][] = $event;
    }
    ksort($grouped);
    return $grouped;
  }

  public static function calendarEvents(PDO $pdo, int $seasonId): array
  {
    $seasonId = (int)$seasonId;
    $games = $pdo->query("SELECT g.id, g.game_date, g.game_time, g.location, g.final_home, g.final_away,
        g.status, g.home_team_id, g.away_team_id,
        ht.name home_name, ht.logo_url home_logo, at.name away_name, at.logo_url away_logo
      FROM games g
      JOIN teams ht ON ht.id = g.home_team_id
      JOIN teams at ON at.id = g.away_team_id
      WHERE COALESCE(g.season_id, $seasonId) = $seasonId
      ORDER BY g.game_date, g.id")->fetchAll();

    $schedule = $pdo->query("SELECT s.*, ht.name home_name, ht.logo_url home_logo, at.name away_name, at.logo_url away_logo
      FROM schedule_entries s
      LEFT JOIN teams ht ON ht.id = s.home_team_id
      LEFT JOIN teams at ON at.id = s.away_team_id
      WHERE s.season_id = $seasonId
      ORDER BY s.game_date, s.game_time, s.id")->fetchAll();

    $indexed = [];
    foreach ($games as $g) {
      $key = $g["game_date"] . "|" . (int)$g["home_team_id"] . "|" . (int)$g["away_team_id"];
      $indexed[$key] = self::normalizeEvent($g, true);
    }

    foreach ($schedule as $s) {
      $homeId = (int)($s["home_team_id"] ?? 0);
      $awayId = (int)($s["away_team_id"] ?? 0);
      if (!$homeId || !$awayId) {
        $indexed["sched-" . $s["id"]] = self::normalizeEvent($s, false);
        continue;
      }
      $key = $s["game_date"] . "|" . $homeId . "|" . $awayId;
      if (!isset($indexed[$key])) {
        $indexed[$key] = self::normalizeEvent($s, false);
      }
    }

    $events = array_values($indexed);
    usort($events, static fn($a, $b) => [$a["game_date"], $a["sort_key"]] <=> [$b["game_date"], $b["sort_key"]]);
    return $events;
  }

  private static function normalizeEvent(array $row, bool $fromGame): array
  {
    $homeScore = (int)($row["final_home"] ?? 0);
    $awayScore = (int)($row["final_away"] ?? 0);
    $hasScore = $fromGame && ($homeScore > 0 || $awayScore > 0 || ($row["status"] ?? "") === "final");
    $isFinal = $fromGame && (
      ($row["status"] ?? "") === "final"
      || ($hasScore && $homeScore !== $awayScore)
      || ($homeScore > 0 || $awayScore > 0)
    );

    return [
      "id" => (int)($row["id"] ?? 0),
      "game_date" => (string)$row["game_date"],
      "game_time" => (string)($row["game_time"] ?? "12:00"),
      "location" => (string)($row["location"] ?? $row["field"] ?? "Campo Principal"),
      "home_team_id" => (int)($row["home_team_id"] ?? 0),
      "away_team_id" => (int)($row["away_team_id"] ?? 0),
      "home_name" => (string)($row["home_label"] ?? $row["home_name"] ?? "TBD"),
      "away_name" => (string)($row["away_label"] ?? $row["away_name"] ?? "TBD"),
      "home_logo" => $row["home_logo"] ?? null,
      "away_logo" => $row["away_logo"] ?? null,
      "final_home" => $homeScore,
      "final_away" => $awayScore,
      "is_final" => $isFinal,
      "is_scheduled" => !$isFinal,
      "sort_key" => (string)($row["game_time"] ?? "12:00"),
    ];
  }

  public static function teamsWithManagers(PDO $pdo): array
  {
    try {
      return $pdo->query("SELECT t.*, COALESCE(t.manager_name, 'N/A') manager_display
        FROM teams t ORDER BY t.name")->fetchAll();
    } catch (Throwable $e) {
      return $pdo->query("SELECT t.*, 'N/A' manager_display FROM teams t ORDER BY t.name")->fetchAll();
    }
  }
}
