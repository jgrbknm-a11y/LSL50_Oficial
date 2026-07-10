<?php

namespace Lsl50\Scorer;

use Lsl50\Scorer\Controllers\ClosureController;
use Lsl50\Scorer\Controllers\LineupController;
use Lsl50\Scorer\Controllers\PlayController;
use Lsl50\Scorer\Controllers\ReviewController;
use Lsl50\Scorer\Controllers\StatsController;
use PDO;

final class AppRouter
{
  public function __construct(
    private PDO $pdo,
    private array $season,
    private string $scorerRoot
  ) {
  }

  public function run(): void
  {
    $seasonId = (int)$this->season["id"];
    $message = Auth::handle($this->pdo);
    $loggedIn = Auth::loggedIn();
    $repos = scorer_repos();

    if ($loggedIn) {
      $this->dispatchPost($seasonId, $repos);
    }

    $page = $this->buildPageData($loggedIn, $message, $seasonId, $repos);
    extract($page, EXTR_SKIP);
    $season = $this->season;
    require $this->scorerRoot . "/views/layout.php";
  }

  private function dispatchPost(int $seasonId, array $repos): void
  {
    $action = (string)post("action");
    if ($action === "" || in_array($action, ["login", "logout"], true)) {
      return;
    }

    $play = new PlayController($this->pdo, $seasonId, $repos["game"], $repos["roster"], $repos["plays"], $repos["box"]);
    $lineup = new LineupController($this->pdo, $seasonId, $repos["game"], $repos["roster"], $repos["lineup"]);
    $stats = new StatsController($this->pdo, $seasonId, $repos["game"]);
    $closure = new ClosureController($this->pdo, $seasonId, $repos["game"], $repos["box"]);
    $review = new ReviewController($seasonId, $repos["game"], $this->scorerRoot);

    match ($action) {
      "save_box" => $stats->saveBox(),
      "add_borrowed_player" => $lineup->addBorrowedPlayer(),
      "remove_borrowed_player" => $lineup->removeBorrowedPlayer(),
      "save_lineup" => $lineup->saveLineup(),
      "save_game_status" => $closure->saveGameStatus(),
      "generate_scorebook_pdf" => $review->generateScorebookPdf(),
      "save_play" => $play->savePlay(),
      "save_courtesy_runner" => $play->saveCourtesyRunner(),
      "delete_play" => $play->deletePlay(),
      default => null,
    };
  }

  private function buildPageData(bool $loggedIn, string $message, int $seasonId, array $repos): array
  {
    $games = [];
    $selectedGame = null;
    $scorerRows = [];
    $playEvents = [];
    $lineups = [];
    $borrowedPlayers = [];
    $borrowablePlayers = [];
    $gameContext = null;
    $gameClosed = false;
    $activeView = in_array((string)getv("view", "plays"), ["plays", "lineups", "stats", "review"], true)
      ? (string)getv("view", "plays")
      : "plays";
    $gameDateSearchEnabled = $activeView === "review";
    $gameDateFilterRaw = $gameDateSearchEnabled ? trim((string)getv("game_date", "")) : "";
    $gameDateFilter = $gameDateSearchEnabled ? scorer_parse_short_date($gameDateFilterRaw) : null;
    $gameDateFilterMessage = $gameDateFilterRaw !== "" && !$gameDateFilter
      ? "Formato de fecha inválido. Usa MM/DD/AA, por ejemplo 06/14/26."
      : "";
    $viewTabs = [
      "plays" => ["Cuaderno", "Jugada y diamante"],
      "lineups" => ["Lineup", "Orden y posiciones"],
      "stats" => ["Estadísticas", "Cuaderno numérico"],
      "review" => ["Consulta", "Auditoría y PDF"],
    ];
    $statKeys = ["AB", "H", "dbl", "tpl", "R", "RBI", "HR", "BB", "SO", "SB", "HBP", "SH", "SF", "E"];
    $statLabels = [
      "AB" => "AB", "H" => "H", "dbl" => "2B", "tpl" => "3B", "R" => "R", "RBI" => "RBI", "HR" => "HR",
      "BB" => "BB", "SO" => "SO", "SB" => "SB", "HBP" => "HBP", "SH" => "SH", "SF" => "SF", "E" => "E",
    ];

    if ($loggedIn) {
      $games = $repos["game"]->listForSeason($seasonId, $gameDateFilter);
      $requestedGameId = (int)getv("game_id", 0);
      $gameId = $requestedGameId;
      if (!$gameId) {
        foreach ($games as $candidate) {
          if (!scorer_game_is_closed($candidate)) {
            $gameId = (int)$candidate["id"];
            break;
          }
        }
        if (!$gameId) $gameId = (int)($games[0]["id"] ?? 0);
      }
      if ($gameId) {
        $selectedGame = $repos["game"]->find($seasonId, $gameId);
        if ($selectedGame) {
          $scorerRows = $repos["roster"]->rowsForGame($selectedGame);
          $playEvents = $repos["plays"]->forGame((int)$selectedGame["id"]);
          $lineups = $repos["lineup"]->forGame((int)$selectedGame["id"]);
          $borrowedPlayers = $repos["roster"]->borrowedPlayers((int)$selectedGame["id"]);
          $borrowablePlayers = $repos["roster"]->borrowablePool($selectedGame);
          $scorerRows = scorer_sort_rows_by_lineup($scorerRows, $lineups, $selectedGame);
          $gameContext = scorer_current_context($selectedGame, $playEvents, $lineups);
          $gameClosed = scorer_game_is_closed($selectedGame);
        }
      }
    }

    $notice = $_SESSION["scorer_message"] ?? $message;
    unset($_SESSION["scorer_message"]);

    return compact(
      "loggedIn",
      "games",
      "selectedGame",
      "scorerRows",
      "playEvents",
      "lineups",
      "borrowedPlayers",
      "borrowablePlayers",
      "gameContext",
      "gameClosed",
      "activeView",
      "gameDateSearchEnabled",
      "gameDateFilterRaw",
      "gameDateFilter",
      "gameDateFilterMessage",
      "viewTabs",
      "statKeys",
      "statLabels",
      "notice"
    );
  }
}
