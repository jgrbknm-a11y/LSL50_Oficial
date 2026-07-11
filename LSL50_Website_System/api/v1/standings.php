<?php
require __DIR__ . "/bootstrap.php";

$pdo = db();
$season = api_v1_season($pdo);
$seasonId = $season["id"];

$rows = $pdo->query("SELECT t.id, t.name, t.slug, t.logo_url,
    COALESCE(ts.wins,0) wins, COALESCE(ts.losses,0) losses, COALESCE(ts.ties,0) ties,
    COALESCE(ts.runs_for,0) runs_for, COALESCE(ts.runs_against,0) runs_against,
    (COALESCE(ts.runs_for,0) - COALESCE(ts.runs_against,0)) run_diff
  FROM teams t
  LEFT JOIN team_stats ts ON ts.team_id=t.id
  ORDER BY wins DESC, ties DESC, run_diff DESC, runs_for DESC, t.name")->fetchAll();

api_v1_json([
  "ok" => true,
  "season" => $season,
  "standings" => array_map(static function ($r) {
    return [
      "team_id" => (int)$r["id"],
      "name" => $r["name"],
      "logo_url" => $r["logo_url"],
      "wins" => (int)$r["wins"],
      "losses" => (int)$r["losses"],
      "ties" => (int)$r["ties"],
      "runs_for" => (int)$r["runs_for"],
      "runs_against" => (int)$r["runs_against"],
      "run_diff" => (int)$r["run_diff"],
    ];
  }, $rows),
]);
