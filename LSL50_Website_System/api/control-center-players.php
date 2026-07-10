<?php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../config.php";

function avg_for_overlay($value): string {
  $number = (float)$value;
  if ($number <= 0) return ".000";
  $formatted = number_format($number, 3, ".", "");
  return str_starts_with($formatted, "0") ? substr($formatted, 1) : $formatted;
}

try {
  $postseasonOnly = ($_GET["postseason"] ?? "") === "1";
  $rows = db()->query("
    SELECT
      p.id,
      p.number,
      p.birth_date,
      p.first_name || ' ' || p.last_name AS name,
      COALESCE(t.name, 'Sin equipo') AS team,
      COALESCE(ps.games_played, 0) AS games_played,
      COALESCE(ps.AVG, 0) AS avg,
      COALESCE(ps.HR, 0) AS hr,
      COALESCE(ps.RBI, 0) AS rbi
    FROM players p
    LEFT JOIN teams t ON t.id = p.team_id
    LEFT JOIN player_stats ps ON ps.player_id = p.id
    ORDER BY t.name, CAST(NULLIF(p.number, '') AS INTEGER), p.last_name, p.first_name
  ")->fetchAll();

  if ($postseasonOnly) {
    $rows = array_values(array_filter($rows, "postseason_eligible"));
  }

  $players = array_map(function ($row) {
    $age = age_status($row["birth_date"] ?? null);
    return [
      "id" => (int)$row["id"],
      "number" => is_numeric($row["number"]) ? (int)$row["number"] : (string)($row["number"] ?? ""),
      "name" => $row["name"],
      "team" => $row["team"],
      "age" => $age["age"],
      "games_played" => (int)$row["games_played"],
      "postseason_eligible" => postseason_eligible($row),
      "avg" => avg_for_overlay($row["avg"]),
      "hr" => (int)$row["hr"],
      "rbi" => (int)$row["rbi"],
    ];
  }, $rows);

  echo json_encode([
    "ok" => true,
    "count" => count($players),
    "players" => $players,
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
