<?php

namespace Lsl50\Repository;

use PDO;
use SqlDialect;
use Throwable;

final class LineupRepository
{
  public function __construct(private PDO $pdo)
  {
  }

  public function forGame(int $gameId): array
  {
    $stmt = $this->pdo->prepare("SELECT gl.*, " . lsl_sql_full_name("p") . " player_name, p.number, t.name team_name,
        ot.name borrowed_from_team_name
      FROM game_lineups gl
      JOIN players p ON p.id=gl.player_id
      JOIN teams t ON t.id=gl.team_id
      LEFT JOIN game_borrowed_players gbp ON gbp.game_id=gl.game_id AND gbp.player_id=gl.player_id AND gbp.borrowed_team_id=gl.team_id AND gbp.active=1
      LEFT JOIN teams ot ON ot.id=gbp.original_team_id
      WHERE gl.game_id=? AND gl.active=1
      ORDER BY gl.team_id, gl.batting_order");
    $stmt->execute([$gameId]);
    $lineups = [];
    foreach ($stmt->fetchAll() as $row) {
      $lineups[(int)$row["team_id"]][(int)$row["batting_order"]] = $row;
    }
    return $lineups;
  }

  /**
   * @param array<int|string, array{player_id?: mixed, field_position?: mixed}> $lineupRows
   */
  public function saveTeamLineup(int $seasonId, int $gameId, int $teamId, array $lineupRows): int
  {
    $saved = 0;
    $this->pdo->beginTransaction();
    try {
      foreach ($lineupRows as $order => $row) {
        $battingOrder = max(1, min(15, (int)$order));
        $playerId = (int)($row["player_id"] ?? 0);
        $position = strtoupper(trim((string)($row["field_position"] ?? "")));
        if (!$playerId) {
          $this->pdo->prepare("DELETE FROM game_lineups WHERE game_id=? AND team_id=? AND batting_order=?")
            ->execute([$gameId, $teamId, $battingOrder]);
          continue;
        }
        SqlDialect::upsertLineup($this->pdo, $seasonId, $gameId, $teamId, $battingOrder, $playerId, $position);
        $saved++;
      }
      $this->pdo->commit();
    } catch (Throwable $e) {
      $this->pdo->rollBack();
      throw $e;
    }
    return $saved;
  }
}
