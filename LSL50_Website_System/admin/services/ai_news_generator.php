<?php
/**
 * CLI — AI Sports Writer LSL50
 *
 *   php admin/services/ai_news_generator.php --game-id=5
 *   php admin/services/ai_news_generator.php --game-id=5 --publish
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/config.php";
require_once dirname(__DIR__, 2) . "/src/autoload.php";

use Lsl50\Services\AiNewsGenerator;

if (PHP_SAPI === "cli" && realpath($argv[0] ?? "") === __FILE__) {
  $gameId = 0;
  $publish = false;
  foreach ($argv as $arg) {
    if (str_starts_with($arg, "--game-id=")) {
      $gameId = (int)substr($arg, 10);
    }
    if ($arg === "--publish") {
      $publish = true;
    }
  }
  if ($gameId <= 0) {
    fwrite(STDERR, "Uso: php admin/services/ai_news_generator.php --game-id=N [--publish]\n");
    exit(1);
  }
  $pdo = db();
  $season = active_season($pdo);
  $result = AiNewsGenerator::generateForGame($pdo, (int)$season["id"], $gameId, ["auto_publish" => $publish]);
  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
  exit($result["ok"] ? 0 : 1);
}
