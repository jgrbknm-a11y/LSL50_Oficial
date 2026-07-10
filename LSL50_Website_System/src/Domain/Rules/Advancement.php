<?php

namespace Lsl50\Domain\Rules;

/**
 * Pure play advancement rules for the LSL50 digital scorebook.
 *
 * Note: this league scorebook does not track pitch-by-pitch balls/strikes.
 * Outs, runner destinations, RBI, and runs are resolved here.
 */
final class Advancement
{
  /**
   * Apply official advancement defaults to a submitted play.
   *
   * @return array{
   *   result:string,
   *   batter_to:string,
   *   runner_1b_id:?int,
   *   runner_1b_to:mixed,
   *   runner_2b_id:?int,
   *   runner_2b_to:mixed,
   *   runner_3b_id:?int,
   *   runner_3b_to:mixed,
   *   outs_on_play:int,
   *   out_detail:string,
   *   rbi:int,
   *   runs_scored:int
   * }
   */
  public static function apply(
    string $result,
    string $batterTo,
    ?int $runner1Id,
    $runner1To,
    ?int $runner2Id,
    $runner2To,
    ?int $runner3Id,
    $runner3To,
    int $outsOnPlay,
    string $outDetail,
    int $rbi,
    int $runsScored,
    int $currentOuts = 0
  ): array {
    $result = $result !== "" ? $result : "OUT";
    if ($batterTo === "") {
      $batterTo = PlayResults::defaultBatterDestination($result);
    }

    if ($result === "HR") {
      $batterTo = "H";
      if ($runner1Id && ($runner1To === "" || $runner1To === "STAY")) $runner1To = "H";
      if ($runner2Id && ($runner2To === "" || $runner2To === "STAY")) $runner2To = "H";
      if ($runner3Id && ($runner3To === "" || $runner3To === "STAY")) $runner3To = "H";
    }
    if ($result === "2B" && $runner1Id && in_array($runner1To, ["", "STAY", "1B", "2B"], true)) {
      $runner1To = "3B";
    }
    $rbi = max(0, $rbi);
    $runsScored = max(0, $runsScored);
    if ($result === "1B") {
      $batterTo = "1B";
      if ($runner1Id && in_array($runner1To, ["", "STAY", "1B"], true)) $runner1To = "2B";
      if ($runner2Id && in_array($runner2To, ["", "STAY", "2B"], true)) $runner2To = "3B";
      if ($runner3Id && in_array($runner3To, ["", "STAY", "3B"], true)) {
        $runner3To = "H";
        $runsScored = max($runsScored, 1);
        $rbi = max($rbi, 1);
      }
    }
    if ($result === "3B") {
      $batterTo = "3B";
      $scoringRunners = 0;
      if ($runner1Id && in_array($runner1To, ["", "STAY", "1B", "2B", "3B"], true)) {
        $runner1To = "H";
        $scoringRunners++;
      }
      if ($runner2Id && in_array($runner2To, ["", "STAY", "2B", "3B"], true)) {
        $runner2To = "H";
        $scoringRunners++;
      }
      if ($runner3Id && in_array($runner3To, ["", "STAY", "3B"], true)) {
        $runner3To = "H";
        $scoringRunners++;
      }
      if ($scoringRunners > 0) {
        $runsScored = max($runsScored, $scoringRunners);
        $rbi = max($rbi, $scoringRunners);
      }
    }
    if (in_array($result, ["BB", "HBP"], true)) {
      $batterTo = "1B";
      if ($runner1Id && in_array($runner1To, ["", "STAY", "1B"], true)) $runner1To = "2B";
      if ($runner1Id && $runner2Id && in_array($runner2To, ["", "STAY", "2B"], true)) $runner2To = "3B";
      if ($runner1Id && $runner2Id && $runner3Id && in_array($runner3To, ["", "STAY", "3B"], true)) {
        $runner3To = "H";
        $runsScored = max($runsScored, 1);
        $rbi = max($rbi, 1);
      } elseif ($runner3Id && ($runner3To === "" || $runner3To === "STAY")) {
        $runner3To = "3B";
      }
    }
    if ($result === "E") {
      $batterTo = "1B";
      if ($runner1Id && in_array($runner1To, ["", "STAY", "1B"], true)) $runner1To = "2B";
      if ($runner1Id && $runner2Id && in_array($runner2To, ["", "STAY", "2B"], true)) $runner2To = "3B";
      if ($runner1Id && $runner2Id && $runner3Id && in_array($runner3To, ["", "STAY", "3B"], true)) {
        $runner3To = "H";
        $runsScored = max($runsScored, 1);
        $rbi = 0;
      } elseif ($runner3Id && ($runner3To === "" || $runner3To === "STAY")) {
        $runner3To = "3B";
      }
    }
    if (in_array($result, ["WP", "PB"], true)) {
      $batterTo = "OUT";
      $rbi = 0;
      $runsScored = 0;
      if ($runner1Id) $runner1To = in_array($runner1To, ["2B", "3B", "OUT"], true) ? $runner1To : "2B";
      if ($runner2Id) $runner2To = in_array($runner2To, ["3B", "OUT"], true) ? $runner2To : "3B";
      if ($runner3Id) $runner3To = in_array($runner3To, ["OUT", "STAY", "3B"], true) ? ($runner3To === "STAY" ? "3B" : $runner3To) : "3B";
    }
    if ($result === "SF") {
      $batterTo = "OUT";
      if ($runner3Id && in_array($runner3To, ["", "STAY", "3B"], true)) {
        $runner3To = "H";
        $runsScored = max($runsScored, 1);
        $rbi = max($rbi, 1);
      }
    }

    $outsOnPlay = max(0, min(3, $outsOnPlay));
    if (in_array($result, ["OUT", "SO", "SF", "SH"], true) && $outsOnPlay === 0) $outsOnPlay = 1;
    $outDetail = strtoupper(trim($outDetail));
    if ($outDetail === "") {
      $outDetail = PlayResults::defaultOutDetail($result);
    }

    $forceOutBase = ForceAndDoublePlay::forceOutBase($outDetail);
    $doublePlayBase = ForceAndDoublePlay::doublePlayBase($outDetail);
    $isForceOut = $forceOutBase !== "";
    $isDoublePlay = $doublePlayBase !== "";

    if ($isForceOut && $runner1Id) {
      $result = "FC";
      $batterTo = "1B";
      if ($forceOutBase === "1B") $runner1To = "OUT";
      if ($forceOutBase === "3B") {
        if ($runner3Id) $runner3To = "OUT";
        if ($runner2Id && in_array($runner2To, ["", "STAY", "2B"], true)) $runner2To = "3B";
        if ($runner1Id && in_array($runner1To, ["", "STAY", "1B"], true)) $runner1To = "2B";
      }
      $outsOnPlay = max($outsOnPlay, 1);
    }
    if ($isDoublePlay && ($runner1Id || $runner3Id)) {
      $result = "OUT";
      $batterTo = "OUT";
      $inningEndsOnPlay = ($currentOuts + max(2, $outsOnPlay)) >= 3;
      if ($doublePlayBase === "1B") {
        if ($runner1Id) $runner1To = "OUT";
        if (!$inningEndsOnPlay && $runner2Id && in_array($runner2To, ["", "STAY", "2B"], true)) $runner2To = "3B";
        if (!$inningEndsOnPlay && $runner3Id && in_array($runner3To, ["", "STAY", "3B"], true)) {
          $runner3To = "H";
          $runsScored = max($runsScored, 1);
        }
      }
      if ($doublePlayBase === "3B") {
        if ($runner3Id) $runner3To = "OUT";
        if (!$inningEndsOnPlay && $runner2Id && in_array($runner2To, ["", "STAY", "2B"], true)) $runner2To = "3B";
        if (!$inningEndsOnPlay && $runner1Id && in_array($runner1To, ["", "STAY", "1B"], true)) $runner1To = "2B";
      }
      $outsOnPlay = max($outsOnPlay, 2);
      $rbi = 0;
    }
    if (!in_array($result, ["OUT", "SO", "SF", "SH", "FC"], true)) $outDetail = "";

    return [
      "result" => $result,
      "batter_to" => $batterTo,
      "runner_1b_id" => $runner1Id,
      "runner_1b_to" => $runner1To,
      "runner_2b_id" => $runner2Id,
      "runner_2b_to" => $runner2To,
      "runner_3b_id" => $runner3Id,
      "runner_3b_to" => $runner3To,
      "outs_on_play" => $outsOnPlay,
      "out_detail" => $outDetail,
      "rbi" => $rbi,
      "runs_scored" => $runsScored,
    ];
  }
}
