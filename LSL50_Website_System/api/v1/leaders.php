<?php
require __DIR__ . "/bootstrap.php";

$pdo = db();
$seasonId = api_v1_season($pdo)["id"];

$departments = [
  "avg" => ["expr" => "ps.AVG", "where" => "ps.AB > 0", "order" => "ps.AVG DESC", "qualified" => true, "format" => "avg"],
  "hits" => ["expr" => "ps.H", "where" => "ps.H > 0", "order" => "ps.H DESC", "qualified" => false, "format" => "int"],
  "hr" => ["expr" => "ps.HR", "where" => "ps.HR > 0", "order" => "ps.HR DESC", "qualified" => false, "format" => "int"],
  "rbi" => ["expr" => "ps.RBI", "where" => "ps.RBI > 0", "order" => "ps.RBI DESC", "qualified" => false, "format" => "int"],
];

$leaders = [];
foreach ($departments as $key => $cfg) {
  $qual = "";
  if ($cfg["qualified"]) {
    $qual = " AND (ps.AB + ps.BB + ps.HBP + ps.SH + ps.SF) >= (
      SELECT CAST((COUNT(*) * 3.1) + 0.999999 AS INTEGER) FROM games g
      WHERE (g.home_team_id=t.id OR g.away_team_id=t.id)
        AND EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id=g.id)
    )";
  }
  $sql = "SELECT p.id player_id, " . lsl_sql_full_name("p") . " player_name, p.number, t.name team_name, {$cfg["expr"]} value
    FROM player_stats ps JOIN players p ON p.id=ps.player_id LEFT JOIN teams t ON t.id=p.team_id
    WHERE {$cfg["where"]} $qual ORDER BY {$cfg["order"]} LIMIT 1";
  $row = $pdo->query($sql)->fetch();
  $leaders[$key] = $row ? [
    "player" => $row["player_name"],
    "number" => $row["number"],
    "team" => $row["team_name"],
    "value" => $cfg["format"] === "avg" ? round((float)$row["value"], 3) : (int)$row["value"],
  ] : null;
}

api_v1_json(["ok" => true, "season_id" => $seasonId, "leaders" => $leaders]);
