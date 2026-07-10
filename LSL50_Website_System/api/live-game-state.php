<?php
/**
 * Live game state for OBS / Live Control Center.
 * Reads the modular scorebook (repos + GameFlow) — not the old monolith.
 *
 * GET /api/live-game-state.php
 * GET /api/live-game-state.php?game_id=5
 */
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "OPTIONS") {
  http_response_code(204);
  exit;
}

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../src/autoload.php";
require_once __DIR__ . "/../scorer/helpers.php";

use Lsl50\Domain\Rules\GameClosure;

function lsl_overlay_avg($value): string
{
  $number = (float)$value;
  if ($number <= 0) {
    return ".000";
  }
  $formatted = number_format($number, 3, ".", "");
  return str_starts_with($formatted, "0") ? substr($formatted, 1) : $formatted;
}

function lsl_inning_label(int $inning, string $half, bool $closed): string
{
  if ($closed) {
    return "FINAL";
  }
  $labels = [
    1 => "1RO",
    2 => "2DO",
    3 => "3RO",
    4 => "4TO",
    5 => "5TO",
    6 => "6TO",
    7 => "7MO",
  ];
  $base = $labels[$inning] ?? ($inning . "MO");
  return $half === "bottom" ? $base . "▼" : $base . "▲";
}

try {
  $pdo = db();
  $season = active_season($pdo);
  $seasonId = (int)$season["id"];
  $repos = scorer_repos();

  $requestedGameId = (int)($_GET["game_id"] ?? 0);
  $game = null;

  if ($requestedGameId > 0) {
    $game = $repos["game"]->find($seasonId, $requestedGameId);
  }

  if (!$game) {
    $games = $repos["game"]->listForSeason($seasonId);
    foreach ($games as $candidate) {
      if (!GameClosure::isClosed($candidate)) {
        $game = $candidate;
        break;
      }
    }
    if (!$game && $games) {
      $game = $games[0];
    }
  }

  if (!$game) {
    echo json_encode([
      "ok" => true,
      "has_game" => false,
      "message" => "No hay juegos en la temporada activa.",
      "overlay" => null,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
  }

  $gameId = (int)$game["id"];
  $playEvents = $repos["plays"]->forGame($gameId);
  $lineups = $repos["lineup"]->forGame($gameId);
  $context = scorer_current_context($game, $playEvents, $lineups);
  $baseState = scorer_base_state($playEvents, (int)$context["inning"], (string)$context["half"]);
  $closed = GameClosure::isClosed($game);

  $batterId = (int)($context["next_batter_id"] ?? 0);
  $batter = [
    "id" => 0,
    "number" => "",
    "name" => "",
    "avg" => ".000",
    "hr" => 0,
    "rbi" => 0,
    "photo" => "",
  ];

  if ($batterId > 0) {
    $stmt = $pdo->prepare("SELECT p.id, p.number, p.first_name || ' ' || p.last_name AS name,
        COALESCE(ps.AVG, 0) AS avg, COALESCE(ps.HR, 0) AS hr, COALESCE(ps.RBI, 0) AS rbi, COALESCE(p.photo_url, '') AS photo
      FROM players p
      LEFT JOIN player_stats ps ON ps.player_id = p.id
      WHERE p.id = ?");
    $stmt->execute([$batterId]);
    $row = $stmt->fetch();
    if ($row) {
      $batter = [
        "id" => (int)$row["id"],
        "number" => is_numeric($row["number"]) ? (int)$row["number"] : (string)($row["number"] ?? ""),
        "name" => (string)$row["name"],
        "avg" => lsl_overlay_avg($row["avg"]),
        "hr" => (int)$row["hr"],
        "rbi" => (int)$row["rbi"],
        "photo" => (string)$row["photo"],
      ];
    }
  }

  $lastPlay = null;
  foreach (array_reverse($playEvents) as $event) {
    if (($event["result"] ?? "") === "CR") {
      continue;
    }
    $lastPlay = [
      "id" => (int)$event["id"],
      "inning" => (int)$event["inning"],
      "half" => (string)$event["half"],
      "result" => (string)$event["result"],
      "batter_name" => (string)($event["batter_name"] ?? ""),
      "outs_on_play" => (int)$event["outs_on_play"],
      "runs_scored" => (int)$event["runs_scored"],
      "label" => scorer_result_label((string)$event["result"], $event["out_detail"] ?? ""),
    ];
    break;
  }

  $overlay = [
    "visitorTeam" => (string)$game["away_name"],
    "homeTeam" => (string)$game["home_name"],
    "visitorScore" => (int)$game["final_away"],
    "homeScore" => (int)$game["final_home"],
    "inning" => lsl_inning_label((int)$context["inning"], (string)$context["half"], $closed),
    "balls" => 0,
    "strikes" => 0,
    "outs" => (int)$context["outs"],
    "bases" => [
      "first" => !empty($baseState["1B"]),
      "second" => !empty($baseState["2B"]),
      "third" => !empty($baseState["3B"]),
    ],
    "batter" => $batter,
  ];

  echo json_encode([
    "ok" => true,
    "has_game" => true,
    "source" => "scorebook",
    "season" => ["id" => $seasonId, "name" => (string)$season["name"]],
    "game" => [
      "id" => $gameId,
      "date" => (string)($game["game_date"] ?? ""),
      "location" => (string)($game["location"] ?? ""),
      "status" => (string)($game["status"] ?? ""),
      "result_type" => (string)($game["result_type"] ?? "pending"),
      "closed" => $closed,
      "home_team_id" => (int)$game["home_team_id"],
      "away_team_id" => (int)$game["away_team_id"],
      "home_name" => (string)$game["home_name"],
      "away_name" => (string)$game["away_name"],
    ],
    "context" => [
      "inning" => (int)$context["inning"],
      "half" => (string)$context["half"],
      "outs" => (int)$context["outs"],
      "batting_team_id" => (int)$context["batting_team_id"],
      "next_batter_id" => (int)$context["next_batter_id"],
      "next_order" => (int)($context["next_order"] ?? 0),
      "play_count" => count($playEvents),
    ],
    "bases_detail" => [
      "1B" => $baseState["1B"]["name"] ?? null,
      "2B" => $baseState["2B"]["name"] ?? null,
      "3B" => $baseState["3B"]["name"] ?? null,
    ],
    "last_play" => $lastPlay,
    "overlay" => $overlay,
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
