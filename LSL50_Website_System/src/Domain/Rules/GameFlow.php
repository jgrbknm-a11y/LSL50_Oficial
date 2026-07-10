<?php

namespace Lsl50\Domain\Rules;

final class GameFlow
{
  public static function nextHalf(array $game, int $inning, string $half): array
  {
    if ($half === "top") return [$inning, "bottom", (int)$game["home_team_id"]];
    return [$inning + 1, "top", (int)$game["away_team_id"]];
  }

  public static function currentContext(array $game, array $playEvents, array $lineups): array
  {
    $inning = 1;
    $half = "top";
    $battingTeamId = (int)$game["away_team_id"];
    $outs = 0;
    $plateAppearances = [];
    $lastNonCourtesyEvent = null;
    $inningChangedOnLastPlay = false;

    foreach ($playEvents as $event) {
      if (($event["result"] ?? "") === "CR") continue;
      $inningChangedOnLastPlay = false;
      $teamId = (int)$event["batting_team_id"];
      if (!in_array((string)($event["result"] ?? ""), ["WP", "PB"], true)) {
        $plateAppearances[$teamId] = ($plateAppearances[$teamId] ?? 0) + 1;
      }
      $eventInning = (int)$event["inning"];
      $eventHalf = (string)$event["half"];
      if ($eventInning !== $inning || $eventHalf !== $half) {
        $inning = $eventInning;
        $half = $eventHalf === "bottom" ? "bottom" : "top";
        $battingTeamId = $half === "top" ? (int)$game["away_team_id"] : (int)$game["home_team_id"];
        $outs = 0;
      }
      $outs += max(0, min(3, (int)$event["outs_on_play"]));
      if ($outs >= 3) {
        [$inning, $half, $battingTeamId] = self::nextHalf($game, $inning, $half);
        $outs = 0;
        $inningChangedOnLastPlay = true;
      }
      $lastNonCourtesyEvent = $event;
    }

    $lineupRows = array_values($lineups[$battingTeamId] ?? []);
    usort($lineupRows, fn($a, $b) => (int)$a["batting_order"] <=> (int)$b["batting_order"]);
    $nextBatterId = 0;
    $nextOrder = 0;
    if ($lineupRows) {
      $index = ($plateAppearances[$battingTeamId] ?? 0) % count($lineupRows);
      $nextBatterId = (int)$lineupRows[$index]["player_id"];
      $nextOrder = (int)$lineupRows[$index]["batting_order"];
    }

    return [
      "inning" => $inning,
      "half" => $half,
      "batting_team_id" => $battingTeamId,
      "outs" => $outs,
      "next_batter_id" => $nextBatterId,
      "next_order" => $nextOrder,
      "last_event_id" => (int)($lastNonCourtesyEvent["id"] ?? 0),
      "inning_changed_on_last_play" => $inningChangedOnLastPlay,
    ];
  }
}
