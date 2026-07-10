<?php

namespace Lsl50\Scorer\Controllers;

use Lsl50\Repository\BoxScoreRepository;
use Lsl50\Repository\GameRepository;
use Lsl50\Repository\PlayEventRepository;
use Lsl50\Repository\RosterRepository;
use Lsl50\Domain\Rules\Advancement;
use PDO;

final class PlayController
{
  public function __construct(
    private PDO $pdo,
    private int $seasonId,
    private GameRepository $games,
    private RosterRepository $roster,
    private PlayEventRepository $plays,
    private BoxScoreRepository $box
  ) {
  }

  public function savePlay(): void
  {
    $gameId = (int)post("game_id");
    $game = $this->game($gameId);
    if (scorer_game_is_closed($game)) scorer_closed_redirect($gameId, "plays");
    if ($game) {
      $rowsForValidation = $this->roster->rowsForGame($game);
      $playerTeams = scorer_player_team_map($rowsForValidation);
      $currentLineups = scorer_lineups($this->pdo, $gameId);
      $currentEvents = $this->plays->forGame($gameId);
      $currentContext = scorer_current_context($game, $currentEvents, $currentLineups);
      $validTeams = [(int)$game["home_team_id"], (int)$game["away_team_id"]];
      $battingTeamId = (int)post("batting_team_id");
      $batterId = (int)post("batter_id");
      $runnerIds = array_filter([(int)post("runner_1b_id"), (int)post("runner_2b_id"), (int)post("runner_3b_id")]);
      $playersMatchTeam = $batterId && (($playerTeams[$batterId] ?? 0) === $battingTeamId);
      $lineupReady = !empty($currentLineups[$battingTeamId]);
      $nextBatterMatches = !$lineupReady || (int)$currentContext["next_batter_id"] === $batterId;
      $flowReady = !empty($currentLineups[(int)$game["home_team_id"]]) || !empty($currentLineups[(int)$game["away_team_id"]]);
      $flowMatches = !$flowReady
        || ($battingTeamId === (int)$currentContext["batting_team_id"]
          && max(1, (int)post("inning", 1)) === (int)$currentContext["inning"]
          && (post("half") === "bottom" ? "bottom" : "top") === (string)$currentContext["half"]);
      foreach ($runnerIds as $runnerId) {
        if (($playerTeams[$runnerId] ?? 0) !== $battingTeamId) $playersMatchTeam = false;
      }
      if (in_array($battingTeamId, $validTeams, true) && $playersMatchTeam && $nextBatterMatches && $flowMatches) {
        $resolved = Advancement::apply(
          trim(post("result")) ?: "OUT",
          (string)post("batter_to"),
          (int)post("runner_1b_id") ?: null,
          post("runner_1b_to"),
          (int)post("runner_2b_id") ?: null,
          post("runner_2b_to"),
          (int)post("runner_3b_id") ?: null,
          post("runner_3b_to"),
          (int)post("outs_on_play"),
          (string)post("out_detail"),
          (int)post("rbi"),
          (int)post("runs_scored"),
          (int)($currentContext["outs"] ?? 0)
        );
        $this->plays->insert(
          $this->seasonId,
          $gameId,
          max(1, (int)post("inning", 1)),
          post("half") === "bottom" ? "bottom" : "top",
          $battingTeamId,
          $batterId,
          $resolved["result"],
          $resolved["batter_to"],
          $resolved["runner_1b_id"],
          $resolved["runner_1b_to"],
          $resolved["runner_2b_id"],
          $resolved["runner_2b_to"],
          $resolved["runner_3b_id"],
          $resolved["runner_3b_to"],
          $resolved["outs_on_play"],
          $resolved["out_detail"],
          $resolved["rbi"],
          $resolved["runs_scored"],
          trim(post("notes"))
        );
        $this->box->rebuildFromPlayEvents($game, $this->seasonId);
        $_SESSION["scorer_message"] = "Jugada guardada en el control de corredores";
      } elseif (!$flowMatches) {
        $_SESSION["scorer_message"] = "No se pudo guardar la jugada: el inning, la parte o el equipo al bate no corresponde al flujo actual del juego.";
      } elseif (!$nextBatterMatches) {
        $_SESSION["scorer_message"] = "No se pudo guardar la jugada: el bateador no corresponde al turno del lineup.";
      } else {
        $_SESSION["scorer_message"] = "No se pudo guardar la jugada: bateador y corredores deben ser del equipo que está bateando.";
      }
    }
    header("Location: /scorer/?game_id=" . $gameId . "&view=plays#plays");
    exit;
  }

  public function saveCourtesyRunner(): void
  {
    $gameId = (int)post("game_id");
    $game = $this->game($gameId);
    if (scorer_game_is_closed($game)) scorer_closed_redirect($gameId, "plays");
    if ($game) {
      $rowsForValidation = $this->roster->rowsForGame($game);
      $playerTeams = scorer_player_team_map($rowsForValidation);
      $currentLineups = scorer_lineups($this->pdo, $gameId);
      $currentEvents = $this->plays->forGame($gameId);
      $currentContext = scorer_current_context($game, $currentEvents, $currentLineups);
      $baseState = scorer_base_state($currentEvents, (int)$currentContext["inning"], (string)$currentContext["half"]);
      $base = strtoupper(trim((string)post("base")));
      $runnerOutId = (int)post("runner_out_id");
      $runnerInId = (int)post("runner_in_id");
      $battingTeamId = (int)$currentContext["batting_team_id"];
      $runnerColumn = ["1B" => "runner_1b_id", "2B" => "runner_2b_id", "3B" => "runner_3b_id"][$base] ?? "";
      $runnerToColumn = ["1B" => "runner_1b_to", "2B" => "runner_2b_to", "3B" => "runner_3b_to"][$base] ?? "";
      $baseHasRunner = $runnerColumn && (int)($baseState[$base]["id"] ?? 0) === $runnerOutId;
      $playersMatchTeam = $runnerOutId && $runnerInId
        && (($playerTeams[$runnerOutId] ?? 0) === $battingTeamId)
        && (($playerTeams[$runnerInId] ?? 0) === $battingTeamId);
      if ($runnerOutId === $runnerInId) $playersMatchTeam = false;

      if ($runnerColumn && $runnerToColumn && $baseHasRunner && $playersMatchTeam) {
        $data = [
          "season_id" => $this->seasonId,
          "game_id" => $gameId,
          "inning" => (int)$currentContext["inning"],
          "half" => (string)$currentContext["half"],
          "batting_team_id" => $battingTeamId,
          "batter_id" => $runnerInId,
          "result" => "CR",
          "batter_to" => $base,
          "runner_1b_id" => null,
          "runner_1b_to" => null,
          "runner_2b_id" => null,
          "runner_2b_to" => null,
          "runner_3b_id" => null,
          "runner_3b_to" => null,
          "outs_on_play" => 0,
          "rbi" => 0,
          "runs_scored" => 0,
          "notes" => trim(post("notes")) ?: "Corredor emergente en $base",
        ];
        $data[$runnerColumn] = $runnerOutId;
        $data[$runnerToColumn] = "OUT";
        $this->plays->insertNamed($data);
        $_SESSION["scorer_message"] = "Corredor emergente guardado sin alterar el turno al bate";
      } elseif (!$baseHasRunner) {
        $_SESSION["scorer_message"] = "No se pudo guardar el corredor emergente: esa base no tiene ese corredor.";
      } else {
        $_SESSION["scorer_message"] = "No se pudo guardar el corredor emergente: ambos jugadores deben ser del equipo al bate y no pueden ser el mismo.";
      }
    }
    header("Location: /scorer/?game_id=" . $gameId . "&view=plays#plays");
    exit;
  }

  public function deletePlay(): void
  {
    $gameId = (int)post("game_id");
    $game = $this->game($gameId);
    if (scorer_game_is_closed($game)) scorer_closed_redirect($gameId, "plays");
    $this->plays->delete((int)post("play_id"), $gameId);
    if ($game) $this->box->rebuildFromPlayEvents($game, $this->seasonId);
    $_SESSION["scorer_message"] = "Jugada eliminada";
    header("Location: /scorer/?game_id=" . $gameId . "&view=plays#plays");
    exit;
  }

  private function game(int $gameId): ?array
  {
    return $this->games->find($this->seasonId, $gameId);
  }
}
