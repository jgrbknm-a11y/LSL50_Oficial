<?php

namespace Lsl50\Repository;

use PDO;
use SqlDialect;

final class PlayEventRepository
{
  public function __construct(private PDO $pdo)
  {
  }

  public function forGame(int $gameId): array
  {
    $stmt = $this->pdo->prepare("SELECT e.*, bt.name batting_team, " . lsl_sql_full_name("b") . " batter_name,
        " . lsl_sql_full_name("r1") . " runner_1b_name,
        " . lsl_sql_full_name("r2") . " runner_2b_name,
        " . lsl_sql_full_name("r3") . " runner_3b_name
      FROM game_play_events e
      JOIN teams bt ON bt.id=e.batting_team_id
      JOIN players b ON b.id=e.batter_id
      LEFT JOIN players r1 ON r1.id=e.runner_1b_id
      LEFT JOIN players r2 ON r2.id=e.runner_2b_id
      LEFT JOIN players r3 ON r3.id=e.runner_3b_id
      WHERE e.game_id=?
      ORDER BY e.inning, CASE e.half WHEN 'top' THEN 0 ELSE 1 END, e.id");
    $stmt->execute([$gameId]);
    return $stmt->fetchAll();
  }

  public function insert(
    int $seasonId,
    int $gameId,
    int $inning,
    string $half,
    int $battingTeamId,
    int $batterId,
    string $result,
    string $batterTo,
    ?int $runner1Id,
    $runner1To,
    ?int $runner2Id,
    $runner2To,
    ?int $runner3Id,
    $runner3To,
    int $outsOnPlay,
    string $outDetail,
    int $rbi,
    int $runsScored,
    string $notes
  ): void {
    $this->pdo->prepare("INSERT INTO game_play_events
        (season_id,game_id,inning,half,batting_team_id,batter_id,result,batter_to,runner_1b_id,runner_1b_to,runner_2b_id,runner_2b_to,runner_3b_id,runner_3b_to,outs_on_play,out_detail,rbi,runs_scored,notes)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
      ->execute([
        $seasonId,
        $gameId,
        $inning,
        $half,
        $battingTeamId,
        $batterId,
        $result,
        $batterTo,
        $runner1Id,
        $runner1To,
        $runner2Id,
        $runner2To,
        $runner3Id,
        $runner3To,
        $outsOnPlay,
        $outDetail,
        $rbi,
        $runsScored,
        $notes,
      ]);
  }

  /**
   * Named-parameter insert used by courtesy runner (CR) events.
   *
   * @param array<string, mixed> $data
   */
  public function insertNamed(array $data): void
  {
    $this->pdo->prepare("INSERT INTO game_play_events
        (season_id,game_id,inning,half,batting_team_id,batter_id,result,batter_to,runner_1b_id,runner_1b_to,runner_2b_id,runner_2b_to,runner_3b_id,runner_3b_to,outs_on_play,rbi,runs_scored,notes)
      VALUES (:season_id,:game_id,:inning,:half,:batting_team_id,:batter_id,:result,:batter_to,:runner_1b_id,:runner_1b_to,:runner_2b_id,:runner_2b_to,:runner_3b_id,:runner_3b_to,:outs_on_play,:rbi,:runs_scored,:notes)")
      ->execute($data);
  }

  public function delete(int $playId, int $gameId): void
  {
    $this->pdo->prepare("DELETE FROM game_play_events WHERE id=? AND game_id=?")
      ->execute([$playId, $gameId]);
  }
}
