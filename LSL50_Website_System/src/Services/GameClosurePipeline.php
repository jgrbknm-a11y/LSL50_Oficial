<?php

namespace Lsl50\Services;

use PDO;

/**
 * Pipeline post-cierre: recalc stats + crónica IA automática.
 */
final class GameClosurePipeline
{
  public static function afterGameClosed(PDO $pdo, int $seasonId, int $gameId, string $resultType): array
  {
    StatsEngine::recalcFromGame($pdo, $seasonId, $gameId);

    $out = ["recalc" => true, "ai" => null];
    if (in_array($resultType, ["pending", "rain_suspended"], true)) {
      return $out;
    }

    $autoGen = lsl_setting($pdo, "ai_auto_generate_on_close", "1") === "1";
    if (!$autoGen) {
      return $out;
    }

    $autoPublish = lsl_setting($pdo, "ai_publish_mode", "review") === "auto";
    try {
      require_once dirname(__DIR__, 2) . "/admin/services/ai_news_generator.php";
      $out["ai"] = lsl_ai_generate_for_game($pdo, $seasonId, $gameId, $autoPublish);
    } catch (Throwable $e) {
      $out["ai"] = ["ok" => false, "error" => $e->getMessage()];
    }
    return $out;
  }
}
