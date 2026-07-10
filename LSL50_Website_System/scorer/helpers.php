<?php

use Lsl50\Domain\Rules\BaseState;
use Lsl50\Domain\Rules\GameClosure;
use Lsl50\Domain\Rules\GameFlow;
use Lsl50\Domain\Rules\LineupRules;
use Lsl50\Domain\Rules\PlateAppearance;
use Lsl50\Domain\Rules\PlayResults;
use Lsl50\Domain\Support\Dates;
use Lsl50\Repository\BoxScoreRepository;
use Lsl50\Repository\GameRepository;
use Lsl50\Repository\LineupRepository;
use Lsl50\Repository\PlayEventRepository;
use Lsl50\Repository\RosterRepository;

function scorer_repos(): array
{
  static $repos = null;
  if ($repos !== null) return $repos;

  $pdo = db();
  $rosterRepo = new RosterRepository($pdo);
  $playEventRepo = new PlayEventRepository($pdo);
  $repos = [
    "pdo" => $pdo,
    "game" => new GameRepository($pdo),
    "roster" => $rosterRepo,
    "lineup" => new LineupRepository($pdo),
    "plays" => $playEventRepo,
    "box" => new BoxScoreRepository($pdo, $rosterRepo, $playEventRepo),
  ];
  return $repos;
}

function scorer_game(PDO $pdo, int $seasonId, int $gameId): ?array
{
  return scorer_repos()["game"]->find($seasonId, $gameId);
}

function scorer_rows(PDO $pdo, array $game): array
{
  return scorer_repos()["roster"]->rowsForGame($game);
}

function scorer_borrowed_players(PDO $pdo, int $gameId): array
{
  return scorer_repos()["roster"]->borrowedPlayers($gameId);
}

function scorer_all_player_pool(PDO $pdo, array $game): array
{
  return scorer_repos()["roster"]->borrowablePool($game);
}

function scorer_play_events(PDO $pdo, int $gameId): array
{
  return scorer_repos()["plays"]->forGame($gameId);
}

function scorer_player_options(array $rows, int $teamId = 0): array
{
  return LineupRules::playerOptions($rows, $teamId);
}

function scorer_player_team_map(array $rows): array
{
  return LineupRules::playerTeamMap($rows);
}

function scorer_plate_appearances(array $row): int
{
  return PlateAppearance::fromRow($row);
}

function scorer_lineups(PDO $pdo, int $gameId): array
{
  return scorer_repos()["lineup"]->forGame($gameId);
}

function scorer_sort_rows_by_lineup(array $rows, array $lineups, array $game): array
{
  return LineupRules::sortRowsByLineup($rows, $lineups, $game);
}

function scorer_positions(): array
{
  return LineupRules::positions();
}

function scorer_required_field_positions(): array
{
  return LineupRules::requiredFieldPositions();
}

function scorer_result_types(): array
{
  return GameClosure::resultTypes();
}

function scorer_parse_short_date(string $value): ?string
{
  return Dates::parseShortDate($value);
}

function scorer_format_short_date(?string $value): string
{
  return Dates::formatShortDate($value);
}

function scorer_game_time_label(?string $location): string
{
  return Dates::gameTimeLabel($location);
}

function scorer_game_is_closed(?array $game): bool
{
  return GameClosure::isClosed($game);
}

function scorer_closed_redirect(int $gameId, string $view = "plays"): void
{
  $_SESSION["scorer_message"] = "Este juego ya está cerrado. Para corregirlo, primero cambia la validación oficial a Pendiente / sin cerrar.";
  header("Location: /scorer/?game_id=" . $gameId . "&view=" . $view . "#scorerTabs");
  exit;
}

function scorer_next_half(array $game, int $inning, string $half): array
{
  return GameFlow::nextHalf($game, $inning, $half);
}

function scorer_current_context(array $game, array $playEvents, array $lineups): array
{
  return GameFlow::currentContext($game, $playEvents, $lineups);
}

function base_destination_label(?string $value): string
{
  return PlayResults::baseDestinationLabel($value);
}

function scorer_result_label(string $result, ?string $outDetail = ""): string
{
  return PlayResults::label($result, $outDetail);
}

function scorer_base_state(array $playEvents, ?int $inning = null, ?string $half = null): array
{
  return BaseState::fromEvents($playEvents, $inning, $half);
}

function scorer_rebuild_box_from_play_events(PDO $pdo, array $game, int $seasonId): void
{
  scorer_repos()["box"]->rebuildFromPlayEvents($game, $seasonId);
}
