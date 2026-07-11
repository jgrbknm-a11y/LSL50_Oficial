<?php
require __DIR__ . "/bootstrap.php";

$pdo = db();
$seasonId = api_v1_season($pdo)["id"];
$gameId = (int)($_GET["id"] ?? 0);
if (!$gameId) api_v1_json(["ok" => false, "error" => "id requerido"], 400);

$stmt = $pdo->prepare("SELECT g.*, ht.name home_name, at.name away_name
  FROM games g
  JOIN teams ht ON ht.id=g.home_team_id
  JOIN teams at ON at.id=g.away_team_id
  WHERE g.id=? AND COALESCE(g.season_id, $seasonId) = $seasonId");
$stmt->execute([$gameId]);
$game = $stmt->fetch();
if (!$game) api_v1_json(["ok" => false, "error" => "Juego no encontrado"], 404);

$stats = $pdo->prepare("SELECT " . lsl_sql_full_name("p") . " player_name, p.number, t.name team_name,
    gps.AB, gps.H, gps.R, gps.RBI, gps.HR
  FROM game_player_stats gps
  JOIN players p ON p.id=gps.player_id
  JOIN teams t ON t.id=gps.team_id
  WHERE gps.game_id=?
  ORDER BY gps.RBI DESC, gps.H DESC");
$stats->execute([$gameId]);

api_v1_json([
  "ok" => true,
  "game" => [
    "id" => (int)$game["id"],
    "date" => $game["game_date"],
    "home" => $game["home_name"],
    "away" => $game["away_name"],
    "final_home" => (int)$game["final_home"],
    "final_away" => (int)$game["final_away"],
    "status" => $game["status"],
    "result_type" => $game["result_type"],
    "youtube_video_id" => $game["youtube_video_id"] ?? null,
  ],
  "player_stats" => $stats->fetchAll(),
]);
