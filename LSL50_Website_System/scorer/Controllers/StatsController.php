<?php

namespace Lsl50\Scorer\Controllers;

use Lsl50\Repository\GameRepository;
use PDO;
use Throwable;

final class StatsController
{
  public function __construct(
    private PDO $pdo,
    private int $seasonId,
    private GameRepository $games
  ) {
  }

  public function saveBox(): void
  {
    $gameId = (int)post("game_id");
    $game = $this->games->find($this->seasonId, $gameId);
    if (scorer_game_is_closed($game)) scorer_closed_redirect($gameId, "stats");
    try {
      lsl_save_game_box($this->pdo, $this->seasonId, $gameId, $_POST["rows"] ?? [], (int)post("winning_pitcher_id"));
      $_SESSION["scorer_message"] = "Cuaderno guardado y estadísticas actualizadas";
    } catch (Throwable $e) {
      $_SESSION["scorer_message"] = "No se pudo guardar: " . $e->getMessage();
    }
    header("Location: /scorer/?game_id=" . $gameId . "&view=stats#scorerTabs");
    exit;
  }
}
