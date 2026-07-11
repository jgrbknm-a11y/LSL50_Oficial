<?php

namespace Lsl50\Repository;

use PDO;
use SqlDialect;

final class RosterRepository
{
  public function __construct(private PDO $pdo)
  {
  }

  public function rowsForGame(array $game): array
  {
    $rowsByPlayer = [];
    $saved = $this->pdo->prepare("SELECT gps.*, " . lsl_sql_full_name("p") . " player_name, p.number, t.name team_name
      FROM game_player_stats gps
      JOIN players p ON p.id=gps.player_id
      JOIN teams t ON t.id=gps.team_id
      WHERE gps.game_id=?
      ORDER BY t.name, p.last_name, p.first_name");
    $saved->execute([(int)$game["id"]]);
    foreach ($saved->fetchAll() as $row) $rowsByPlayer[(int)$row["player_id"]] = $row;

    $roster = $this->pdo->prepare("SELECT p.id player_id, p.team_id, p.number, " . lsl_sql_full_name("p") . " player_name, t.name team_name
      FROM players p
      JOIN teams t ON t.id=p.team_id
      WHERE p.team_id IN (?, ?)
      ORDER BY CASE WHEN p.team_id=? THEN 0 ELSE 1 END, " . SqlDialect::orderByUniformNumber("p") . ", p.last_name, p.first_name");
    $roster->execute([(int)$game["home_team_id"], (int)$game["away_team_id"], (int)$game["home_team_id"]]);

    $rows = [];
    foreach ($roster->fetchAll() as $player) {
      $existing = $rowsByPlayer[(int)$player["player_id"]] ?? [];
      $rows[] = array_merge([
        "player_id" => $player["player_id"],
        "team_id" => $player["team_id"],
        "number" => $player["number"],
        "player_name" => $player["player_name"],
        "team_name" => $player["team_name"],
        "AB" => 0, "H" => 0, "dbl" => 0, "tpl" => 0, "R" => 0, "RBI" => 0, "HR" => 0, "BB" => 0, "SO" => 0, "SB" => 0, "HBP" => 0, "SH" => 0, "SF" => 0, "E" => 0,
      ], $existing);
      unset($rowsByPlayer[(int)$player["player_id"]]);
    }

    $borrowed = $this->pdo->prepare("SELECT gbp.player_id, gbp.borrowed_team_id team_id, gbp.original_team_id,
        p.number, " . lsl_sql_full_name("p") . " player_name,
        bt.name team_name, ot.name original_team_name, gbp.reason
      FROM game_borrowed_players gbp
      JOIN players p ON p.id=gbp.player_id
      JOIN teams bt ON bt.id=gbp.borrowed_team_id
      LEFT JOIN teams ot ON ot.id=gbp.original_team_id
      WHERE gbp.game_id=? AND gbp.active=1
      ORDER BY bt.name, p.last_name, p.first_name");
    $borrowed->execute([(int)$game["id"]]);
    foreach ($borrowed->fetchAll() as $player) {
      $playerId = (int)$player["player_id"];
      $existing = $rowsByPlayer[$playerId] ?? [];
      $rows[] = array_merge([
        "player_id" => $playerId,
        "team_id" => $player["team_id"],
        "number" => $player["number"],
        "player_name" => $player["player_name"],
        "team_name" => $player["team_name"],
        "original_team_name" => $player["original_team_name"] ?? "",
        "borrowed_label" => "Prestado de " . ($player["original_team_name"] ?? "otro equipo"),
        "AB" => 0, "H" => 0, "dbl" => 0, "tpl" => 0, "R" => 0, "RBI" => 0, "HR" => 0, "BB" => 0, "SO" => 0, "SB" => 0, "HBP" => 0, "SH" => 0, "SF" => 0, "E" => 0,
      ], $existing);
      unset($rowsByPlayer[$playerId]);
    }
    foreach ($rowsByPlayer as $row) $rows[] = $row;
    return $rows;
  }

  public function borrowedPlayers(int $gameId): array
  {
    $stmt = $this->pdo->prepare("SELECT gbp.*, " . lsl_sql_full_name("p") . " player_name, p.number,
        ot.name original_team_name, bt.name borrowed_team_name
      FROM game_borrowed_players gbp
      JOIN players p ON p.id=gbp.player_id
      LEFT JOIN teams ot ON ot.id=gbp.original_team_id
      JOIN teams bt ON bt.id=gbp.borrowed_team_id
      WHERE gbp.game_id=? AND gbp.active=1
      ORDER BY bt.name, p.last_name, p.first_name");
    $stmt->execute([$gameId]);
    return $stmt->fetchAll();
  }

  public function borrowablePool(array $game): array
  {
    $stmt = $this->pdo->prepare("SELECT p.id player_id, p.team_id, p.number, " . lsl_sql_full_name("p") . " player_name, t.name team_name
      FROM players p
      LEFT JOIN teams t ON t.id=p.team_id
      WHERE p.team_id IS NOT NULL AND p.team_id NOT IN (?, ?)
      ORDER BY t.name, p.last_name, p.first_name");
    $stmt->execute([(int)$game["home_team_id"], (int)$game["away_team_id"]]);
    return $stmt->fetchAll();
  }

  public function findPlayer(int $playerId): ?array
  {
    $stmt = $this->pdo->prepare("SELECT id, team_id FROM players WHERE id=?");
    $stmt->execute([$playerId]);
    $player = $stmt->fetch();
    return $player ?: null;
  }

  public function playerTeamsForGame(array $game): array
  {
    $gameId = (int)$game["id"];
    $players = $this->pdo->prepare("SELECT id, team_id FROM players WHERE team_id IN (?, ?)");
    $players->execute([(int)$game["home_team_id"], (int)$game["away_team_id"]]);
    $playerTeams = [];
    foreach ($players->fetchAll() as $player) {
      $playerTeams[(int)$player["id"]] = (int)$player["team_id"];
    }
    $borrowed = $this->pdo->prepare("SELECT player_id, borrowed_team_id FROM game_borrowed_players WHERE game_id=? AND active=1");
    $borrowed->execute([$gameId]);
    foreach ($borrowed->fetchAll() as $player) {
      $playerTeams[(int)$player["player_id"]] = (int)$player["borrowed_team_id"];
    }
    return $playerTeams;
  }

  public function addBorrowed(
    int $seasonId,
    int $gameId,
    int $playerId,
    int $originalTeamId,
    int $borrowedTeamId,
    string $reason,
    string $approvedBy
  ): void {
    SqlDialect::upsertBorrowedPlayer(
      $this->pdo,
      $seasonId,
      $gameId,
      $playerId,
      $originalTeamId,
      $borrowedTeamId,
      $reason,
      $approvedBy
    );
  }

  public function deactivateBorrowed(int $borrowedId, int $gameId): void
  {
    $this->pdo->prepare("UPDATE game_borrowed_players SET active=0 WHERE id=? AND game_id=?")
      ->execute([$borrowedId, $gameId]);
  }
}
