<?php

namespace Lsl50\Scorer\Controllers;

use Lsl50\Domain\Rules\LineupRules;
use Lsl50\Repository\GameRepository;
use Lsl50\Repository\LineupRepository;
use Lsl50\Repository\RosterRepository;
use PDO;
use Throwable;

final class LineupController
{
  public function __construct(
    private PDO $pdo,
    private int $seasonId,
    private GameRepository $games,
    private RosterRepository $roster,
    private LineupRepository $lineups
  ) {
  }

  public function saveLineup(): void
  {
    $gameId = (int)post("game_id");
    $teamId = (int)post("team_id");
    $game = $this->games->find($this->seasonId, $gameId);
    if (scorer_game_is_closed($game)) scorer_closed_redirect($gameId, "lineups");
    if ($game && in_array($teamId, [(int)$game["home_team_id"], (int)$game["away_team_id"]], true)) {
      $rowsForValidation = $this->roster->rowsForGame($game);
      $playerTeams = scorer_player_team_map($rowsForValidation);
      $lineupRows = $_POST["lineup"] ?? [];
      $errors = LineupRules::validate($lineupRows, $teamId, $playerTeams);

      if ($errors) {
        $_SESSION["scorer_message"] = "No se pudo guardar el lineup: " . implode(" ", array_slice($errors, 0, 5));
      } else {
        try {
          $saved = $this->lineups->saveTeamLineup($this->seasonId, $gameId, $teamId, $lineupRows);
          $_SESSION["scorer_message"] = $saved > 0 ? "Lineup guardado para el equipo seleccionado" : "Lineup actualizado";
        } catch (Throwable $e) {
          $_SESSION["scorer_message"] = "No se pudo guardar el lineup: " . $e->getMessage();
        }
      }
    } else {
      $_SESSION["scorer_message"] = "No se pudo guardar el lineup: equipo inválido para este juego.";
    }
    header("Location: /scorer/?game_id=" . $gameId . "&view=lineups#scorerTabs");
    exit;
  }

  public function addBorrowedPlayer(): void
  {
    $gameId = (int)post("game_id");
    $borrowedTeamId = (int)post("borrowed_team_id");
    $playerId = (int)post("player_id");
    $game = $this->games->find($this->seasonId, $gameId);
    if (scorer_game_is_closed($game)) scorer_closed_redirect($gameId, "lineups");
    if ($game && $playerId && in_array($borrowedTeamId, [(int)$game["home_team_id"], (int)$game["away_team_id"]], true)) {
      $player = $this->roster->findPlayer($playerId);
      $originalTeamId = (int)($player["team_id"] ?? 0);
      if ($player && $originalTeamId && $originalTeamId !== $borrowedTeamId) {
        $this->roster->addBorrowed(
          $this->seasonId,
          $gameId,
          $playerId,
          $originalTeamId,
          $borrowedTeamId,
          trim(post("reason")) ?: "Evitar forfeit",
          trim(post("approved_by")) ?: "Anotador"
        );
        $_SESSION["scorer_message"] = "Jugador prestado agregado para este juego";
      } else {
        $_SESSION["scorer_message"] = "No se pudo agregar: el jugador no puede ser del mismo equipo que lo toma prestado.";
      }
    } else {
      $_SESSION["scorer_message"] = "No se pudo agregar el jugador prestado: verifica juego, equipo y jugador.";
    }
    header("Location: /scorer/?game_id=" . $gameId . "&view=lineups#scorerTabs");
    exit;
  }

  public function removeBorrowedPlayer(): void
  {
    $gameId = (int)post("game_id");
    $game = $this->games->find($this->seasonId, $gameId);
    if (scorer_game_is_closed($game)) scorer_closed_redirect($gameId, "lineups");
    $borrowedId = (int)post("borrowed_id");
    $this->roster->deactivateBorrowed($borrowedId, $gameId);
    $_SESSION["scorer_message"] = "Jugador prestado retirado de este juego";
    header("Location: /scorer/?game_id=" . $gameId . "&view=lineups#scorerTabs");
    exit;
  }
}
