<?php
/**
 * Integration tests: StatsEngine, AI news generator, public stats parity.
 *
 * Run: php LSL50_Website_System/tools/test_stats_public.php
 */
declare(strict_types=1);

require __DIR__ . "/../config.php";
require __DIR__ . "/../src/autoload.php";

use Lsl50\Api\V1\ApiSanitizer;
use Lsl50\Api\V1\LeadersResource;
use Lsl50\Api\V1\StandingsResource;
use Lsl50\Services\AiNewsGenerator;
use Lsl50\Services\AppSettingsService;
use Lsl50\Services\PublicStatsService;
use Lsl50\Services\StatsEngine;
use Lsl50\Services\YoutubeSyncService;

$passed = 0;
$failed = 0;

function assert_public(bool $cond, string $label): void
{
  global $passed, $failed;
  if ($cond) {
    echo "OK  $label\n";
    $passed++;
  } else {
    echo "FAIL $label\n";
    $failed++;
  }
}

echo "=== LSL50 Public Stats & AI Tests ===\n\n";

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];

$depts = StatsEngine::offensiveDepartments();
assert_public(count($depts) >= 14, "offensiveDepartments has 14+ categories");
$abbrs = array_column($depts, "abbr");
assert_public(in_array("OPS", $abbrs, true), "departments include OPS");
assert_public(in_array("OBP", $abbrs, true), "departments include OBP");
assert_public(in_array("SLG", $abbrs, true), "departments include SLG");

$adminDepts = StatsEngine::offensiveDepartments("admin");
assert_public(count($adminDepts) >= 20, "admin offensiveDepartments has 20 categories");
$adminAbbrs = array_column($adminDepts, "abbr");
assert_public(in_array("HBP", $adminAbbrs, true), "admin departments include HBP");
assert_public(in_array("GP", $adminAbbrs, true), "admin departments include GP");

$featured = StatsEngine::featuredOffensiveDepartments();
assert_public(count($featured) === 4, "featuredOffensiveDepartments has 4 entries");

$teamPa = StatsEngine::teamMinPlateAppearances($pdo, $seasonId);
assert_public(is_array($teamPa), "teamMinPlateAppearances returns array");

$pitchWins = StatsEngine::pitcherWinLeaders($pdo, $seasonId, 5);
assert_public(is_array($pitchWins), "pitcherWinLeaders returns array");

assert_public(!method_exists(\Lsl50\Services\LeaderboardService::class, "departments"), "LeaderboardService departments removed");

$ytScore = YoutubeSyncService::scoreVideoForGame(
  ["title" => "Cubs vs Bucaneros LSL50 Julio 2026", "published_date" => "2026-07-11"],
  ["home_name" => "Cubs", "away_name" => "Bucaneros", "game_date" => "2026-07-11"]
);
assert_public($ytScore >= 45, "YoutubeSyncService scores date+title match");

$creds = YoutubeSyncService::resolveCredentials($pdo);
assert_public(is_array($creds["source"]), "YoutubeSyncService resolveCredentials source");

assert_public(AppSettingsService::parseBool("1") === true, "AppSettingsService parseBool true");
assert_public(AppSettingsService::parseBool("0") === false, "AppSettingsService parseBool false");
assert_public(AppSettingsService::isAiAutoGenerateOnClose($pdo) === true, "ai_auto_generate_on_close default true");
AppSettingsService::setAiAutoGenerateOnClose($pdo, false);
assert_public(AppSettingsService::isAiAutoGenerateOnClose($pdo) === false, "ai_auto_generate_on_close saved false");
AppSettingsService::setAiAutoGenerateOnClose($pdo, true);
assert_public(AppSettingsService::isAiAutoGenerateOnClose($pdo) === true, "ai_auto_generate_on_close restored true");

$invalid = false;
try {
  AppSettingsService::sanitizeBoolKey("not_allowed_key");
} catch (InvalidArgumentException) {
  $invalid = true;
}
assert_public($invalid, "AppSettingsService rejects unknown bool keys");

$batters = StatsEngine::battingTable($pdo, $seasonId);
assert_public(is_array($batters), "battingTable returns array");
if ($batters) {
  $first = $batters[0];
  assert_public(array_key_exists("OPS", $first), "batter row has OPS");
  assert_public(array_key_exists("ISO", $first), "batter row has ISO");
  assert_public(array_key_exists("qual_label", $first), "batter row has qual_label");
}

$enriched = StatsEngine::enrichBatterRow([
  "AVG" => 0.300,
  "SLG" => 0.500,
  "OBP" => 0.400,
  "PA" => 20,
  "min_pa" => 15,
  "AB" => 18,
  "H" => 6,
  "HR" => 1,
  "BB" => 2,
  "SF" => 0,
]);
assert_public(abs($enriched["OPS"] - 0.900) < 0.001, "enrichBatterRow OPS = OBP + SLG");
assert_public(abs($enriched["ISO"] - 0.200) < 0.001, "enrichBatterRow ISO = SLG - AVG");
assert_public($enriched["qualified"] === true, "enrichBatterRow qualified when PA >= min_pa");

$pitching = StatsEngine::pitchingTable($pdo, $seasonId);
assert_public(is_array($pitching), "pitchingTable returns array");
foreach ($pitching as $row) {
  if ((float)($row["IP"] ?? 0) > 0 && (int)($row["ER"] ?? 0) >= 0) {
    $expectedEra = round(((int)$row["ER"] * 9) / (float)$row["IP"], 2);
    assert_public(abs((float)$row["ERA"] - $expectedEra) < 0.02, "pitching ERA computed for " . ($row["player_name"] ?? "?"));
    break;
  }
}

$standings = StatsEngine::standings($pdo, $seasonId);
assert_public(is_array($standings), "standings returns array");
if ($standings) {
  assert_public(isset($standings[0]["streak"]), "standings row has streak");
  assert_public(isset($standings[0]["l10"]), "standings row has l10");
}

$calendar = PublicStatsService::calendarEvents($pdo, $seasonId);
assert_public(is_array($calendar), "calendarEvents returns array");
if ($calendar) {
  assert_public(isset($calendar[0]["game_time"]), "calendar event has game_time");
  assert_public(isset($calendar[0]["sort_key"]), "calendar event has sort_key");
}
$byMonth = PublicStatsService::calendarByMonth($pdo, $seasonId);
assert_public(is_array($byMonth), "calendarByMonth returns array");

$apiPayload = StandingsResource::build($pdo, ["id" => $seasonId, "name" => (string)$season["name"]], true);
assert_public(($apiPayload["ok"] ?? false) === true, "StandingsResource build ok");
assert_public(isset($apiPayload["standings"][0]["form"]["streak"]), "API standings has streak");
assert_public(isset($apiPayload["standings"][0]["form"]["last_10"]), "API standings has last_10");
assert_public(isset($apiPayload["standings"][0]["record"]["pct_display"]), "API standings has pct_display");

$leadersPayload = LeadersResource::build($pdo, ["id" => $seasonId, "name" => (string)$season["name"]], 1, "legacy");
assert_public(($leadersPayload["ok"] ?? false) === true, "LeadersResource build ok");
assert_public(isset($leadersPayload["leaders"]["avg"]), "API leaders has avg key");
assert_public(isset($leadersPayload["leaders"]["avg"]["department"]["abbr"]), "API leaders avg abbr");
assert_public(array_key_exists("leader", $leadersPayload["leaders"]["avg"]), "API leaders avg leader slot");
assert_public(isset($leadersPayload["meta"]["league_games"]), "API leaders league_games meta");

$leadersFull = LeadersResource::build($pdo, ["id" => $seasonId, "name" => (string)$season["name"]], 3, "full");
assert_public(count($leadersFull["leaders"]) >= 10, "LeadersResource full scope has 10+ departments");
assert_public(ApiSanitizer::clampInt("5", 1, 10, 1) === 5, "ApiSanitizer clampInt");

$gameId = (int)$pdo->query("SELECT id FROM games ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($gameId > 0) {
  $box = StatsEngine::gameBoxSummary($pdo, $gameId);
  assert_public(isset($box["teams"]) && is_array($box["teams"]), "gameBoxSummary has teams");
  assert_public(array_key_exists("mvp", $box), "gameBoxSummary has mvp key");

  $prompt = AiNewsGenerator::buildSportsPrompt([
    "game" => [
      "game_date" => "2026-07-10",
      "away_name" => "Visitante",
      "home_name" => "Local",
      "final_away" => 3,
      "final_home" => 5,
      "location" => "Campo",
      "winning_pitcher_name" => "Juan Pérez",
    ],
    "box" => $box,
    "leaders" => [],
    "plays" => [],
    "video_url" => "https://www.youtube.com/watch?v=test123",
  ], "profesional", 90);
  assert_public(str_contains($prompt, "MVP") || str_contains($prompt, "mvp_sugerido"), "AI prompt references MVP");
  assert_public(str_contains($prompt, "Visitante"), "AI prompt includes away team");

  $localNote = AiNewsGenerator::buildLocalNote([
    "game" => [
      "home_name" => "Local",
      "away_name" => "Visitante",
      "final_home" => 5,
      "final_away" => 3,
      "winning_pitcher_name" => "Juan Pérez",
    ],
    "box" => $box,
  ], 90);
  assert_public($localNote["title"] !== "", "local note has title");
  assert_public(str_contains($localNote["body"], "Local"), "local note body mentions home team");
  assert_public(str_contains($localNote["body"], "Juan Pérez"), "local note mentions winning pitcher");
}

assert_public(method_exists(AiNewsGenerator::class, "generateForGame"), "AiNewsGenerator generateForGame exists");
assert_public(method_exists(AiNewsGenerator::class, "buildContext"), "AiNewsGenerator buildContext exists");

echo "\n=== Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
exit($failed > 0 ? 1 : 0);
