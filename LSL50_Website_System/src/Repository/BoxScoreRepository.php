<?php

namespace Lsl50\Repository;

use PDO;
use Throwable;

final class BoxScoreRepository
{
  public function __construct(
    private PDO $pdo,
    private RosterRepository $roster,
    private PlayEventRepository $playEvents
  ) {
  }

  public function rebuildFromPlayEvents(array $game, int $seasonId): void
  {
    $gameId = (int)$game["id"];
    $playerTeams = $this->roster->playerTeamsForGame($game);
    $events = $this->playEvents->forGame($gameId);
    $stats = [];
    $touch = function (int $playerId) use (&$stats, $playerTeams): ?array {
      if (!$playerId || empty($playerTeams[$playerId])) return null;
      if (!isset($stats[$playerId])) {
        $stats[$playerId] = [
          "team_id" => $playerTeams[$playerId],
          "AB" => 0, "H" => 0, "dbl" => 0, "tpl" => 0, "R" => 0, "RBI" => 0,
          "HR" => 0, "BB" => 0, "SO" => 0, "SB" => 0, "HBP" => 0, "SH" => 0,
          "SF" => 0, "E" => 0,
        ];
      }
      return $stats[$playerId];
    };
    $addRun = function (?int $playerId) use (&$stats, $touch): void {
      if (!$playerId) return;
      $touch($playerId);
      if (isset($stats[$playerId])) $stats[$playerId]["R"]++;
    };

    foreach ($events as $event) {
      $result = (string)($event["result"] ?? "");
      if ($result === "CR") continue;

      $batterId = (int)($event["batter_id"] ?? 0);
      $touch($batterId);

      if (!in_array($result, ["WP", "PB", "SB"], true) && isset($stats[$batterId])) {
        if (in_array($result, ["1B", "2B", "3B", "HR"], true)) {
          $stats[$batterId]["AB"]++;
          $stats[$batterId]["H"]++;
          if ($result === "2B") $stats[$batterId]["dbl"]++;
          if ($result === "3B") $stats[$batterId]["tpl"]++;
          if ($result === "HR") $stats[$batterId]["HR"]++;
        } elseif ($result === "BB") {
          $stats[$batterId]["BB"]++;
        } elseif ($result === "HBP") {
          $stats[$batterId]["HBP"]++;
        } elseif ($result === "SH") {
          $stats[$batterId]["SH"]++;
        } elseif ($result === "SF") {
          $stats[$batterId]["SF"]++;
        } elseif ($result === "SO") {
          $stats[$batterId]["AB"]++;
          $stats[$batterId]["SO"]++;
        } elseif (in_array($result, ["OUT", "E", "FC"], true)) {
          $stats[$batterId]["AB"]++;
        }
        $stats[$batterId]["RBI"] += max(0, (int)($event["rbi"] ?? 0));
      }

      if (($event["batter_to"] ?? "") === "H" && !in_array($result, ["WP", "PB"], true)) $addRun($batterId);
      if (($event["runner_1b_to"] ?? "") === "H") $addRun((int)($event["runner_1b_id"] ?? 0));
      if (($event["runner_2b_to"] ?? "") === "H") $addRun((int)($event["runner_2b_id"] ?? 0));
      if (($event["runner_3b_to"] ?? "") === "H") $addRun((int)($event["runner_3b_id"] ?? 0));
    }

    $this->pdo->beginTransaction();
    try {
      $this->pdo->prepare("DELETE FROM game_player_stats WHERE game_id=?")->execute([$gameId]);
      $insert = $this->pdo->prepare("INSERT INTO game_player_stats (season_id,game_id,team_id,player_id,AB,H,dbl,tpl,R,RBI,HR,BB,SO,SB,HBP,SH,SF,E)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $homeRuns = 0;
      $awayRuns = 0;
      foreach ($stats as $playerId => $row) {
        $hasStats = false;
        foreach (["AB","H","dbl","tpl","R","RBI","HR","BB","SO","SB","HBP","SH","SF","E"] as $key) {
          if ((int)$row[$key] > 0) $hasStats = true;
        }
        if (!$hasStats) continue;
        if ((int)$row["team_id"] === (int)$game["home_team_id"]) $homeRuns += (int)$row["R"];
        if ((int)$row["team_id"] === (int)$game["away_team_id"]) $awayRuns += (int)$row["R"];
        $insert->execute([
          $seasonId, $gameId, (int)$row["team_id"], (int)$playerId,
          (int)$row["AB"], (int)$row["H"], (int)$row["dbl"], (int)$row["tpl"],
          (int)$row["R"], (int)$row["RBI"], (int)$row["HR"], (int)$row["BB"],
          (int)$row["SO"], (int)$row["SB"], (int)$row["HBP"], (int)$row["SH"],
          (int)$row["SF"], (int)$row["E"],
        ]);
      }
      foreach (array_keys($playerTeams) as $playerId) {
        lsl_recalc_player_stats($this->pdo, (int)$playerId, $seasonId);
      }
      $this->pdo->prepare("UPDATE games SET final_home=?, final_away=? WHERE id=?")
        ->execute([$homeRuns, $awayRuns, $gameId]);
      lsl_recalc_team_stats($this->pdo, $seasonId);
      $this->pdo->commit();
    } catch (Throwable $e) {
      $this->pdo->rollBack();
      throw $e;
    }
  }
}
