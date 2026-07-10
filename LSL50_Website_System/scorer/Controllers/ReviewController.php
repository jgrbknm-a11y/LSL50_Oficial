<?php

namespace Lsl50\Scorer\Controllers;

use Lsl50\Repository\GameRepository;

final class ReviewController
{
  public function __construct(
    private int $seasonId,
    private GameRepository $games,
    private string $scorerRoot
  ) {
  }

  public function generateScorebookPdf(): void
  {
    $gameId = (int)post("game_id");
    $game = $this->games->find($this->seasonId, $gameId);
    if ($game) {
      $python = "/Users/joseramirez/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/bin/python3";
      $script = $this->scorerRoot . "/../tools/generate_scorebook_pdf.py";
      $cmd = escapeshellcmd($python) . " " . escapeshellarg($script) . " " . escapeshellarg((string)$gameId);
      exec($cmd, $out, $code);
      $_SESSION["scorer_message"] = $code === 0 ? "PDF oficial del cuaderno generado correctamente" : "No se pudo generar el PDF oficial del cuaderno.";
    } else {
      $_SESSION["scorer_message"] = "No se pudo generar el PDF: juego no encontrado.";
    }
    header("Location: /scorer/?game_id=" . $gameId . "&view=review#scorebookReview");
    exit;
  }
}
