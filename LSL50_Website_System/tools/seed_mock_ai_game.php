<?php
/**
 * Simulación one-shot: Cubs 12 vs Bucaneros 8 + stats + jugada clave + video YouTube.
 *
 * Run: php LSL50_Website_System/tools/seed_mock_ai_game.php
 */
declare(strict_types=1);

require __DIR__ . "/../config.php";
require_once __DIR__ . "/../src/autoload.php";

use Lsl50\Services\StatsEngine;

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];

$cubsId = 3;
$bucanerosId = 1;
$mvpId = 48;          // Carlos Guerrero
$winningPitcherId = 47; // Blas Ramirez
$youtubeVideoId = "7ghSziUQnvs"; // ID real de formato YouTube (transmisión demo)

$pdo->beginTransaction();
try {
  $pdo->prepare("INSERT INTO games (
      season_id, home_team_id, away_team_id, game_date, location,
      final_home, final_away, winning_pitcher_id, status, result_type,
      is_legal_game, completed_innings, youtube_video_id, notes, ended_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'final', 'regulation', 1, 7, ?, ?, CURRENT_TIMESTAMP)")
    ->execute([
      $seasonId,
      $cubsId,
      $bucanerosId,
      date("Y-m-d"),
      "Campo Principal LSL50 · Broward",
      12,
      8,
      $winningPitcherId,
      $youtubeVideoId,
      "Simulación AI Sports Writer — partido de prueba autónomo.",
    ]);

  $gameId = (int)$pdo->lastInsertId();

  $statRows = [
    // Cubs (home) — ofensiva explosiva
    [$seasonId, $gameId, $cubsId, 48, 4, 4, 1, 0, 3, 5, 2, 1, 0, 0, 0, 0, 0], // MVP Guerrero
    [$seasonId, $gameId, $cubsId, 47, 3, 2, 0, 0, 2, 2, 0, 0, 0, 0, 0, 0, 0], // Winning pitcher also hit
    [$seasonId, $gameId, $cubsId, 50, 4, 3, 1, 0, 2, 3, 1, 0, 1, 0, 0, 0, 0], // Erik Ravelo
    [$seasonId, $gameId, $cubsId, 52, 3, 2, 0, 0, 1, 1, 0, 1, 0, 0, 0, 0, 1],
    [$seasonId, $gameId, $cubsId, 55, 3, 1, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0],
    // Bucaneros (away)
    [$seasonId, $gameId, $bucanerosId, 67, 4, 3, 1, 0, 2, 3, 1, 0, 0, 0, 0, 0, 1], // Carlos Paez
    [$seasonId, $gameId, $bucanerosId, 69, 4, 2, 1, 0, 1, 2, 0, 1, 0, 0, 0, 0, 0],
    [$seasonId, $gameId, $bucanerosId, 71, 3, 2, 0, 0, 1, 1, 1, 0, 0, 0, 0, 0, 0],
    [$seasonId, $gameId, $bucanerosId, 73, 3, 1, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 1],
    [$seasonId, $gameId, $bucanerosId, 66, 3, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
  ];

  $insStat = $pdo->prepare("INSERT INTO game_player_stats (
      season_id, game_id, team_id, player_id, AB, H, dbl, tpl, R, RBI, HR, BB, SO, SB, HBP, SH, SF, E
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

  foreach ($statRows as $row) {
    $insStat->execute($row);
  }

  $plays = [
    [$seasonId, $gameId, 3, "top", $bucanerosId, 67, "HR", 2, 2, "Jonrón de 2 carreras — Carlos Paez"],
    [$seasonId, $gameId, 5, "bottom", $cubsId, 50, "2B", 1, 1, "Doble productor — Erik Ravelo empata la tensión"],
    [$seasonId, $gameId, 6, "bottom", $cubsId, 48, "HR", 3, 3, "JONRÓN DECISIVO de 3 carreras — Carlos Guerrero (MVP)"],
    [$seasonId, $gameId, 7, "top", $bucanerosId, 71, "SO", 0, 0, "Ponche clave de Blas Ramirez cierra la sexta amenaza"],
  ];

  $insPlay = $pdo->prepare("INSERT INTO game_play_events (
      season_id, game_id, inning, half, batting_team_id, batter_id, result, rbi, runs_scored, out_detail
    ) VALUES (?,?,?,?,?,?,?,?,?,?)");

  foreach ($plays as $play) {
    $insPlay->execute($play);
  }

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  fwrite(STDERR, "Error seed: " . $e->getMessage() . PHP_EOL);
  exit(1);
}

StatsEngine::recalcFromGame($pdo, $seasonId, $gameId);

echo json_encode([
  "ok" => true,
  "game_id" => $gameId,
  "matchup" => "Bucaneros 8 @ Cubs 12",
  "mvp_player_id" => $mvpId,
  "winning_pitcher_id" => $winningPitcherId,
  "youtube_video_id" => $youtubeVideoId,
  "youtube_url" => "https://www.youtube.com/watch?v=" . $youtubeVideoId,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
