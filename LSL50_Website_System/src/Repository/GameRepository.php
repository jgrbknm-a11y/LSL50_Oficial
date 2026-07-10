<?php

namespace Lsl50\Repository;

use PDO;

final class GameRepository
{
  public function __construct(private PDO $pdo)
  {
  }

  public function find(int $seasonId, int $gameId): ?array
  {
    $stmt = $this->pdo->prepare("SELECT g.*, ht.name home_name, at.name away_name
      FROM games g
      JOIN teams ht ON ht.id=g.home_team_id
      JOIN teams at ON at.id=g.away_team_id
      WHERE g.id=? AND COALESCE(g.season_id, $seasonId) = $seasonId");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    return $game ?: null;
  }

  public function listForSeason(int $seasonId, ?string $gameDate = null): array
  {
    if ($gameDate) {
      $stmt = $this->pdo->prepare("SELECT g.*, ht.name home_name, at.name away_name
        FROM games g
        JOIN teams ht ON ht.id=g.home_team_id
        JOIN teams at ON at.id=g.away_team_id
        WHERE COALESCE(g.season_id, $seasonId) = $seasonId AND g.game_date=?
        ORDER BY g.game_date DESC, g.location, g.id");
      $stmt->execute([$gameDate]);
      return $stmt->fetchAll();
    }

    return $this->pdo->query("SELECT g.*, ht.name home_name, at.name away_name
      FROM games g
      JOIN teams ht ON ht.id=g.home_team_id
      JOIN teams at ON at.id=g.away_team_id
      WHERE COALESCE(g.season_id, $seasonId) = $seasonId
      ORDER BY g.game_date DESC, g.location, g.id")->fetchAll();
  }

  public function updateFinalScores(int $gameId, int $homeRuns, int $awayRuns): void
  {
    $this->pdo->prepare("UPDATE games SET final_home=?, final_away=? WHERE id=?")
      ->execute([$homeRuns, $awayRuns, $gameId]);
  }

  public function updateOfficialStatus(
    int $gameId,
    string $status,
    string $resultType,
    int $homeScore,
    int $awayScore,
    ?int $forfeitWinner,
    ?int $forfeitLoser,
    int $isLegal,
    int $completedInnings,
    string $officialResultNote,
    ?string $endedAt
  ): void {
    $this->pdo->prepare("UPDATE games SET status=?, result_type=?, final_home=?, final_away=?, forfeit_winner_team_id=?, forfeit_loser_team_id=?, is_legal_game=?, completed_innings=?, official_result_note=?, ended_at=? WHERE id=?")
      ->execute([
        $status,
        $resultType,
        $homeScore,
        $awayScore,
        $forfeitWinner,
        $forfeitLoser,
        $isLegal,
        $completedInnings,
        $officialResultNote,
        $endedAt,
        $gameId,
      ]);
  }
}
