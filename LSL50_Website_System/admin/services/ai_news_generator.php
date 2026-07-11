<?php
/**
 * CLI — AI Sports Writer LSL50
 *
 *   php admin/services/ai_news_generator.php --game-id=5
 *   php admin/services/ai_news_generator.php --game-id=5 --publish
 *   php admin/services/ai_news_generator.php --game-id=9 --publish --openai
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/config.php";
require_once dirname(__DIR__, 2) . "/src/autoload.php";

use Lsl50\Services\AiNewsGenerator;

/**
 * Carga .env del proyecto y sincroniza OPENAI_API_KEY → app_settings.
 * Nunca imprime el secreto.
 */
function lsl_ai_cli_bootstrap_openai(PDO $pdo): array
{
  $projectRoot = dirname(__DIR__, 3);
  $websiteRoot = dirname(__DIR__, 2);

  lsl_load_env_files([
    $projectRoot . "/.env",
    $websiteRoot . "/data/.env",
    $websiteRoot . "/.env",
  ]);

  $key = AiNewsGenerator::syncOpenAiKeyFromEnv($pdo);
  return [
    "configured" => $key !== "",
    "source" => $key !== "" ? (trim(lsl_setting($pdo, "openai_api_key", "")) !== "" ? "app_settings" : "env") : "none",
  ];
}

if (PHP_SAPI === "cli" && realpath($argv[0] ?? "") === __FILE__) {
  $gameId = 0;
  $publish = false;
  $forceOpenAi = false;

  foreach ($argv as $arg) {
    if (str_starts_with($arg, "--game-id=")) {
      $gameId = (int)substr($arg, 10);
    }
    if ($arg === "--publish") {
      $publish = true;
    }
    if ($arg === "--openai" || $arg === "--require-openai") {
      $forceOpenAi = true;
    }
  }

  if ($gameId <= 0) {
    fwrite(STDERR, "Uso: php admin/services/ai_news_generator.php --game-id=N [--publish] [--openai]\n");
    exit(1);
  }

  $pdo = db();
  $openAiBootstrap = lsl_ai_cli_bootstrap_openai($pdo);
  $season = active_season($pdo);

  $result = AiNewsGenerator::generateForGame($pdo, (int)$season["id"], $gameId, [
    "auto_publish" => $publish,
    "require_openai" => $forceOpenAi || $openAiBootstrap["configured"],
  ]);

  $result["openai_key_source"] = $openAiBootstrap["source"];
  $result["openai_configured"] = $openAiBootstrap["configured"];

  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
  exit(($result["ok"] ?? false) ? 0 : 1);
}
