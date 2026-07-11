<?php

namespace Lsl50\Services;

use PDO;

/** Recálculo centralizado de standings y líderes. */
final class StatsEngine
{
  public static function recalcAll(PDO $pdo, int $seasonId, array $playerIds = []): void
  {
    $seasonId = (int)$seasonId;
    foreach ($playerIds as $playerId) {
      if ((int)$playerId > 0) {
        lsl_recalc_player_stats($pdo, (int)$playerId, $seasonId);
      }
    }
    lsl_recalc_team_stats($pdo, $seasonId);
  }

  public static function recalcFromGame(PDO $pdo, int $seasonId, int $gameId): void
  {
    $seasonId = (int)$seasonId;
    $stmt = $pdo->prepare("SELECT DISTINCT player_id FROM game_player_stats WHERE game_id=?");
    $stmt->execute([$gameId]);
    $ids = array_map(fn($r) => (int)$r["player_id"], $stmt->fetchAll());
    self::recalcAll($pdo, $seasonId, $ids);
  }
}
