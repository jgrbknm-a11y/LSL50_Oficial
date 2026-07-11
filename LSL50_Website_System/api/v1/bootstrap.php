<?php
/** API v1 bootstrap — JSON helpers + CORS */
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-LSL50-Key");

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "OPTIONS") {
  http_response_code(204);
  exit;
}

require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../src/autoload.php";

function api_v1_json(array $payload, int $code = 200): void
{
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function api_v1_season(PDO $pdo): array
{
  $season = active_season($pdo);
  return ["id" => (int)$season["id"], "name" => (string)($season["name"] ?? "")];
}

function api_v1_query_bool(string $key, bool $default = false): bool
{
  if (!isset($_GET[$key])) {
    return $default;
  }
  $raw = strtolower(trim((string)$_GET[$key]));
  if (in_array($raw, ["1", "true", "yes", "on"], true)) {
    return true;
  }
  if (in_array($raw, ["0", "false", "no", "off"], true)) {
    return false;
  }
  return $default;
}

function api_v1_query_int(string $key, int $default, int $min, int $max): int
{
  if (!isset($_GET[$key])) {
    return $default;
  }
  if (!is_numeric($_GET[$key])) {
    return $default;
  }
  $value = (int)$_GET[$key];
  return max($min, min($max, $value));
}

function api_v1_query_scope(string $key, string $default = "legacy"): string
{
  if (!isset($_GET[$key])) {
    return $default;
  }
  $raw = strtolower(trim((string)$_GET[$key]));
  return in_array($raw, ["legacy", "full"], true) ? $raw : $default;
}
