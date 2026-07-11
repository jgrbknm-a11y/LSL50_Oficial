<?php
/**
 * Unit tests for scorebook rebuild rules: DP, WP/PB, forfeit standings.
 *
 * Run: php LSL50_Website_System/tools/test_rebuild_rules.php
 */
declare(strict_types=1);

require __DIR__ . "/../config.php";
require __DIR__ . "/../src/autoload.php";

use Lsl50\Domain\Rules\Advancement;
use Lsl50\Domain\Rules\GameClosure;
use Lsl50\Domain\Rules\GameFlow;

$passed = 0;
$failed = 0;

function assert_test(bool $cond, string $label): void
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

echo "=== LSL50 Rebuild Rules Tests ===\n\n";

// --- Double play (DP 6-4-3) ---
$dp = Advancement::apply(
  "OUT", "", 101, "", 0, "", 103, "",
  2, "6-4-3", 0, 0, 1
);
assert_test($dp["result"] === "OUT", "DP 6-4-3 result OUT");
assert_test($dp["batter_to"] === "OUT", "DP 6-4-3 batter out");
assert_test($dp["outs_on_play"] === 2, "DP 6-4-3 two outs on play");
assert_test($dp["runner_1b_to"] === "OUT", "DP 6-4-3 runner from 1B out");
assert_test($dp["runs_scored"] === 0, "DP 6-4-3 with 1 out already — no run on 3B force");

$dp543 = Advancement::apply(
  "OUT", "", 101, "", 0, "", 103, "",
  2, "5-4-3", 0, 0, 0
);
assert_test($dp543["runner_1b_to"] === "OUT", "DP 5-4-3 runner from 1B out");
assert_test($dp543["outs_on_play"] === 2, "DP 5-4-3 two outs");

// --- Wild pitch / passed ball ---
$wp = Advancement::apply(
  "WP", "", 0, "", 102, "", 0, "",
  0, "", 0, 0, 0
);
assert_test($wp["result"] === "WP", "WP keeps result WP");
assert_test($wp["runner_2b_to"] === "3B", "WP runner on 2B advances to 3B");
assert_test($wp["runs_scored"] === 0, "WP no automatic run");
assert_test($wp["rbi"] === 0, "WP no RBI");

$pb = Advancement::apply(
  "PB", "", 101, "", 0, "", 0, "",
  0, "", 0, 0, 0
);
assert_test($pb["runner_1b_to"] === "2B", "PB runner on 1B advances to 2B");

// --- Forfeit closure ---
$game = ["home_team_id" => 11, "away_team_id" => 10];
$forfeitHome = GameClosure::resolve($game, "forfeit", 0, 0, 0, 11);
assert_test($forfeitHome["ok"] === true, "Forfeit home winner resolves");
assert_test($forfeitHome["result_type"] === "forfeit", "Forfeit result type");
assert_test($forfeitHome["home_score"] === 7 && $forfeitHome["away_score"] === 0, "Forfeit home 7-0 default");
assert_test($forfeitHome["forfeit_winner"] === 11, "Forfeit winner team id");
assert_test($forfeitHome["is_legal"] === 1, "Forfeit is legal game");

$forfeitAway = GameClosure::resolve($game, "forfeit", 0, 0, 0, 10);
assert_test($forfeitAway["away_score"] === 7 && $forfeitAway["home_score"] === 0, "Forfeit away 7-0 default");

// --- Team stats include forfeit without player stats ---
$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];

$homeId = 11;
$awayId = 10;
$beforeW = (int)$pdo->query("SELECT wins FROM team_stats WHERE team_id=$homeId")->fetchColumn();
$beforeL = (int)$pdo->query("SELECT losses FROM team_stats WHERE team_id=$awayId")->fetchColumn();

$testGameId = (int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM games")->fetchColumn();
$pdo->prepare("INSERT INTO games (id,season_id,home_team_id,away_team_id,game_date,location,final_home,final_away,status,result_type,is_legal_game)
  VALUES (?,?,?,?,?,?,?,?,?,?,?)")
  ->execute([$testGameId, $seasonId, $homeId, $awayId, "2099-01-01", "TEST FORFEIT", 7, 0, "final", "forfeit", 1]);

lsl_recalc_team_stats($pdo, $seasonId);

$afterW = (int)$pdo->query("SELECT wins FROM team_stats WHERE team_id=$homeId")->fetchColumn();
$afterL = (int)$pdo->query("SELECT losses FROM team_stats WHERE team_id=$awayId")->fetchColumn();
assert_test($afterW === $beforeW + 1, "Forfeit adds win to home team in standings");
assert_test($afterL === $beforeL + 1, "Forfeit adds loss to away team in standings");

$pdo->prepare("DELETE FROM games WHERE id=?")->execute([$testGameId]);
lsl_recalc_team_stats($pdo, $seasonId);

// --- GameFlow: 3 outs switch half ---
$playEvents = [
  ["result" => "OUT", "inning" => 1, "half" => "top", "batting_team_id" => 10, "outs_on_play" => 1],
  ["result" => "OUT", "inning" => 1, "half" => "top", "batting_team_id" => 10, "outs_on_play" => 1],
  ["result" => "OUT", "inning" => 1, "half" => "top", "batting_team_id" => 10, "outs_on_play" => 1],
];
$lineups = [10 => [1 => ["player_id" => 101, "batting_order" => 1]]];
$ctx = GameFlow::currentContext($game, $playEvents, $lineups);
assert_test($ctx["half"] === "bottom", "Three outs in top → bottom half");
assert_test((int)$ctx["outs"] === 0, "Outs reset after half change");
assert_test((int)$ctx["batting_team_id"] === $homeId, "Home team bats in bottom");

echo "\n=== Results: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
