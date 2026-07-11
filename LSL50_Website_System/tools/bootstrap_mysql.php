<?php
/**
 * Bootstrap MySQL schema for LSL50 (creates DB if needed, applies docs/schema-mysql.sql).
 *
 * Usage:
 *   docker compose up -d mysql
 *   php LSL50_Website_System/tools/bootstrap_mysql.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once dirname(__DIR__) . "/src/Support/Env.php";

lsl_load_env_files([
  $root . "/.env",
  dirname(__DIR__) . "/data/.env",
  dirname(__DIR__) . "/.env",
]);

$host = lsl_env("DB_HOST", "127.0.0.1") ?: "127.0.0.1";
$port = lsl_env("DB_PORT", "3306") ?: "3306";
$name = lsl_env("DB_NAME", "lsl50") ?: "lsl50";
$user = lsl_env("DB_USER", "lsl50") ?: "lsl50";
$pass = lsl_env("DB_PASS", "lsl50") ?? "lsl50";
$rootUser = lsl_env("DB_ROOT_USER", "root") ?: "root";
$rootPass = lsl_env("DB_ROOT_PASS", "root") ?? "root";

$schemaFile = $root . "/docs/schema-mysql.sql";
if (!is_file($schemaFile)) {
  fwrite(STDERR, "Schema not found: {$schemaFile}\n");
  exit(1);
}

try {
  $admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $rootUser, $rootPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
  $admin->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $admin->exec("CREATE USER IF NOT EXISTS '{$user}'@'%' IDENTIFIED BY '{$pass}'");
  $admin->exec("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$user}'@'%'");
  $admin->exec("FLUSH PRIVILEGES");
  echo "Database `{$name}` ready.\n";
} catch (Throwable $e) {
  fwrite(STDERR, "Admin connection failed (try docker compose up -d mysql): " . $e->getMessage() . "\n");
  exit(1);
}

try {
  $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
} catch (Throwable $e) {
  fwrite(STDERR, "App connection failed: " . $e->getMessage() . "\n");
  exit(1);
}

require_once dirname(__DIR__) . "/src/Support/SqlDialect.php";
SqlDialect::execSqlFile($pdo, $schemaFile);

putenv("DB_DRIVER=mysql");
$_ENV["DB_DRIVER"] = "mysql";
$_SERVER["DB_DRIVER"] = "mysql";

require_once dirname(__DIR__) . "/config.php";
$pdo = db();
$teams = (int)$pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$seasons = (int)$pdo->query("SELECT COUNT(*) FROM seasons")->fetchColumn();

echo "Bootstrap OK — driver=mysql, teams={$teams}, seasons={$seasons}\n";
echo "Set in .env:\n";
echo "DB_DRIVER=mysql\n";
echo "DB_HOST={$host}\n";
echo "DB_PORT={$port}\n";
echo "DB_NAME={$name}\n";
echo "DB_USER={$user}\n";
echo "DB_PASS={$pass}\n";
