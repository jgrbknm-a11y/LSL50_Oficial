<?php

namespace Lsl50\Scorer\Controllers;

use Lsl50\Domain\Rules\GameClosure;
use Lsl50\Repository\BoxScoreRepository;
use Lsl50\Repository\GameRepository;
use PDO;

final class ClosureController
{
  public function __construct(
    private PDO $pdo,
    private int $seasonId,
    private GameRepository $games,
    private BoxScoreRepository $box
  ) {
  }

  public function saveGameStatus(): void
  {
    $gameId = (int)post("game_id");
    $game = $this->games->find($this->seasonId, $gameId);
    $resultType = (string)post("result_type");
    if ($game) {
      $resolved = GameClosure::resolve(
        $game,
        $resultType,
        (int)post("completed_innings"),
        (int)post("official_home_score"),
        (int)post("official_away_score"),
        (int)post("forfeit_winner_team_id")
      );
      if (!$resolved["ok"]) {
        $_SESSION["scorer_message"] = $resolved["error"];
        header("Location: /scorer/?game_id=" . $gameId . "&view=lineups#scorerTabs");
        exit;
      }

      $resultType = $resolved["result_type"];
      $status = $resolved["status"];
      $isLegal = $resolved["is_legal"];
      $completedInnings = $resolved["completed_innings"];
      $homeScore = $resolved["home_score"];
      $awayScore = $resolved["away_score"];
      $forfeitWinner = $resolved["forfeit_winner"];
      $forfeitLoser = $resolved["forfeit_loser"];

      if ($resultType !== "pending" && $resultType !== "forfeit") {
        $this->box->rebuildFromPlayEvents($game, $this->seasonId);
        $freshGame = $this->games->find($this->seasonId, $gameId);
        if ($freshGame) {
          $homeScore = (int)$freshGame["final_home"];
          $awayScore = (int)$freshGame["final_away"];
        }
      }

      $endedAt = $resultType === "pending" ? null : date("Y-m-d H:i:s");
      $this->games->updateOfficialStatus(
        $gameId,
        $status,
        $resultType,
        $homeScore,
        $awayScore,
        $forfeitWinner ?: null,
        $forfeitLoser ?: null,
        $isLegal,
        $completedInnings,
        trim(post("official_result_note")),
        $endedAt
      );
      lsl_recalc_team_stats($this->pdo, $this->seasonId);
      $_SESSION["scorer_message"] = $resultType === "pending"
        ? "Juego reabierto para corrección"
        : "Juego cerrado y estadísticas guardadas. Selecciona el próximo juego abierto.";
    }
    if (($resultType ?? "pending") === "pending") {
      header("Location: /scorer/?game_id=" . $gameId . "&view=lineups#officialValidation");
    } else {
      header("Location: /scorer/?view=plays#scorerTabs");
    }
    exit;
  }
}
