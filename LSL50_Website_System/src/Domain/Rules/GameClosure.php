<?php

namespace Lsl50\Domain\Rules;

final class GameClosure
{
  public static function resultTypes(): array
  {
    return [
      "pending" => "Pendiente / sin cerrar",
      "normal" => "Final normal",
      "time_limit" => "Final por tiempo 1h 45m",
      "innings_limit" => "Final por 7 innings",
      "extra_innings" => "Final en extra inning",
      "rain_legal" => "Suspendido por lluvia - juego legal",
      "rain_suspended" => "Suspendido por lluvia - no legal",
      "forfeit" => "Forfeit",
    ];
  }

  public static function isClosed(?array $game): bool
  {
    if (!$game) return false;
    $status = (string)($game["status"] ?? "");
    $resultType = (string)($game["result_type"] ?? "pending");
    return in_array($status, ["final", "suspended"], true) && $resultType !== "" && $resultType !== "pending";
  }

  /**
   * Pure resolution of official game status fields.
   *
   * @return array{ok:bool,error?:string,status?:string,result_type?:string,is_legal?:int,completed_innings?:int,home_score?:int,away_score?:int,forfeit_winner?:int,forfeit_loser?:int}
   */
  public static function resolve(
    array $game,
    string $resultType,
    int $completedInnings,
    int $homeScore,
    int $awayScore,
    int $forfeitWinnerTeamId
  ): array {
    if (!array_key_exists($resultType, self::resultTypes())) {
      $resultType = "pending";
    }

    $status = "final";
    $isLegal = 0;
    $forfeitWinner = 0;
    $forfeitLoser = 0;
    $completedInnings = max(0, min(20, $completedInnings));
    $homeScore = max(0, $homeScore);
    $awayScore = max(0, $awayScore);

    if ($resultType === "pending") {
      $status = "scheduled";
      $isLegal = 0;
    }

    if (in_array($resultType, ["normal", "time_limit", "innings_limit", "extra_innings"], true)) {
      $status = "final";
      $isLegal = 1;
    }

    if ($resultType === "rain_legal") {
      if ($completedInnings < 5) {
        return [
          "ok" => false,
          "error" => "No se guardó: para que una suspensión por lluvia sea juego legal debe tener 5 innings completos.",
        ];
      }
      $status = "final";
      $isLegal = 1;
    }

    if ($resultType === "rain_suspended") {
      $status = "suspended";
      $isLegal = 0;
    }

    if ($resultType === "forfeit") {
      $forfeitWinner = $forfeitWinnerTeamId;
      $validTeams = [(int)$game["home_team_id"], (int)$game["away_team_id"]];
      if (!in_array($forfeitWinner, $validTeams, true)) $forfeitWinner = (int)$game["home_team_id"];
      $forfeitLoser = $forfeitWinner === (int)$game["home_team_id"] ? (int)$game["away_team_id"] : (int)$game["home_team_id"];
      if ($forfeitWinner === (int)$game["home_team_id"]) {
        $homeScore = $homeScore ?: 7;
        $awayScore = 0;
      } else {
        $awayScore = $awayScore ?: 7;
        $homeScore = 0;
      }
      $isLegal = 1;
      $status = "final";
    }

    return [
      "ok" => true,
      "status" => $status,
      "result_type" => $resultType,
      "is_legal" => $isLegal,
      "completed_innings" => $completedInnings,
      "home_score" => $homeScore,
      "away_score" => $awayScore,
      "forfeit_winner" => $forfeitWinner,
      "forfeit_loser" => $forfeitLoser,
    ];
  }
}
