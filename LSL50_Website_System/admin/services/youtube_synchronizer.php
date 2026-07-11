<?php
/**
 * CLI — Sincronizador YouTube LSL50
 *
 *   php admin/services/youtube_synchronizer.php
 *   php admin/services/youtube_synchronizer.php --dry-run
 *   php admin/services/youtube_synchronizer.php --limit=30
 *   php admin/services/youtube_synchronizer.php --list-only
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/config.php";
require_once dirname(__DIR__, 2) . "/src/autoload.php";

use Lsl50\Services\YoutubeSyncService;

if (PHP_SAPI !== "cli" || realpath($argv[0] ?? "") !== __FILE__) {
  return;
}

$dryRun = false;
$listOnly = false;
$limit = 25;

foreach ($argv as $arg) {
  if ($arg === "--dry-run") {
    $dryRun = true;
  }
  if ($arg === "--list-only") {
    $listOnly = true;
  }
  if (str_starts_with($arg, "--limit=")) {
    $limit = max(1, min(50, (int)substr($arg, 8)));
  }
}

try {
  $pdo = db();
  YoutubeSyncService::syncCredentialsFromEnv($pdo);
  $season = active_season($pdo);
  $seasonId = (int)$season["id"];

  if (!YoutubeSyncService::isConfigured($pdo)) {
    throw new RuntimeException(
      "Configura YOUTUBE_API_KEY y YOUTUBE_CHANNEL_ID en .env (raíz del proyecto) o en app_settings."
    );
  }

  if ($listOnly) {
    $fetch = YoutubeSyncService::fetchRecentVideos($pdo, $limit);
    echo json_encode([
      "ok" => $fetch["ok"],
      "mode" => "list-only",
      "channel_id" => $fetch["channel_id"] ?? null,
      "videos" => $fetch["videos"] ?? [],
      "error" => $fetch["error"] ?? null,
      "credentials" => YoutubeSyncService::resolveCredentials($pdo)["source"],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($fetch["ok"] ? 0 : 1);
  }

  $result = YoutubeSyncService::synchronize($pdo, $seasonId, $dryRun, $limit);
  $result["season"] = ["id" => $seasonId, "name" => (string)$season["name"]];
  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
  exit($result["ok"] ? 0 : 1);
} catch (Throwable $e) {
  fwrite(STDERR, json_encode([
    "ok" => false,
    "error" => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE) . PHP_EOL);
  exit(1);
}
