<?php
/**
 * Copy all rows from local SQLite into MySQL (schema must exist).
 *
 * Usage:
 *   php LSL50_Website_System/tools/bootstrap_mysql.php
 *   php LSL50_Website_System/tools/migrate_sqlite_to_mysql.php
 */
declare(strict_types=1);

$websiteRoot = dirname(__DIR__);
$projectRoot = dirname($websiteRoot);
$sqliteFile = $websiteRoot . "/data/lsl50_local.sqlite";

require_once $websiteRoot . "/src/Support/Env.php";
lsl_load_env_files([
  $projectRoot . "/.env",
  $websiteRoot . "/data/.env",
  $websiteRoot . "/.env",
]);

if (!is_file($sqliteFile)) {
  fwrite(STDERR, "SQLite file not found: {$sqliteFile}\n");
  exit(1);
}

$host = lsl_env("DB_HOST", "127.0.0.1") ?: "127.0.0.1";
$port = lsl_env("DB_PORT", "3306") ?: "3306";
$name = lsl_env("DB_NAME", "lsl50") ?: "lsl50";
$user = lsl_env("DB_USER", "lsl50") ?: "lsl50";
$pass = lsl_env("DB_PASS", "lsl50") ?? "lsl50";

try {
  $source = new PDO("sqlite:" . $sqliteFile, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $target = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  fwrite(STDERR, "Connection failed: " . $e->getMessage() . "\n");
  exit(1);
}

$tables = [
  "seasons",
  "season_archives",
  "users",
  "teams",
  "players",
  "games",
  "schedule_entries",
  "game_player_stats",
  "game_play_events",
  "game_lineups",
  "game_borrowed_players",
  "player_stats",
  "team_stats",
  "media",
  "ai_game_notes",
  "weekly_awards",
  "app_settings",
];

$target->exec("SET FOREIGN_KEY_CHECKS = 0");
foreach (array_reverse($tables) as $table) {
  try {
    $target->exec("DELETE FROM `{$table}`");
  } catch (Throwable $e) {
    // ignore
  }
}

$total = 0;
foreach ($tables as $table) {
  try {
    $rows = $source->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    echo "Skip {$table}: " . $e->getMessage() . "\n";
    continue;
  }
  if (!$rows) {
    echo "{$table}: 0 rows\n";
    continue;
  }

  $columns = array_keys($rows[0]);
  $quoted = array_map(fn($c) => $c === "key" ? "`key`" : "`{$c}`", $columns);
  $placeholders = implode(", ", array_fill(0, count($columns), "?"));
  $sql = "INSERT INTO `{$table}` (" . implode(", ", $quoted) . ") VALUES ({$placeholders})";
  $stmt = $target->prepare($sql);

  $count = 0;
  foreach ($rows as $row) {
    $stmt->execute(array_values($row));
    $count++;
  }
  $total += $count;
  echo "{$table}: {$count} rows\n";
}

$target->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "Migration complete — {$total} rows copied to MySQL `{$name}`.\n";
