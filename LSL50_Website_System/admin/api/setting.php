<?php
declare(strict_types=1);

require __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../src/autoload.php";
require_admin();

use Lsl50\Api\Admin\SettingsResource;

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode([
    "ok" => false,
    "error" => ["code" => "method_not_allowed", "message" => "Solo POST.", "status" => 405],
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$csrfHeader = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
if (!admin_verify_csrf(is_string($csrfHeader) ? $csrfHeader : "")) {
  http_response_code(403);
  echo json_encode([
    "ok" => false,
    "error" => ["code" => "csrf_invalid", "message" => "Token de seguridad inválido.", "status" => 403],
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$raw = file_get_contents("php://input");
$input = json_decode($raw ?: "{}", true);
if (!is_array($input)) {
  http_response_code(400);
  echo json_encode([
    "ok" => false,
    "error" => ["code" => "invalid_json", "message" => "JSON inválido.", "status" => 400],
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo = db();
  $payload = SettingsResource::updateBool($pdo, $input);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  $debug = lsl_env("LSL50_DEBUG", "") === "1";
  $payload = SettingsResource::errorPayload($e, $debug);
  http_response_code((int)($payload["error"]["status"] ?? 500));
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}
