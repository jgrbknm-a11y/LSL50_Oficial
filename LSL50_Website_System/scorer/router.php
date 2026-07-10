<?php
/**
 * PHP built-in server router for the tablet-only Scorer App.
 *
 * Usage:
 *   php -S 0.0.0.0:8090 -t LSL50_Website_System LSL50_Website_System/scorer/router.php
 *
 * Allows: /scorer/, /public/uploads/, /output/pdf/
 * Blocks: /admin and everything else.
 */

$uri = urldecode(parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/");

if ($uri === "/" || $uri === "") {
  header("Location: /scorer/");
  return true;
}

$allowed =
  str_starts_with($uri, "/scorer")
  || str_starts_with($uri, "/public/uploads/")
  || str_starts_with($uri, "/output/pdf/");

if (!$allowed || str_starts_with($uri, "/data") || str_starts_with($uri, "/.env")) {
  http_response_code(403);
  header("Content-Type: text/plain; charset=utf-8");
  echo "Forbidden";
  return true;
}

$docRoot = rtrim($_SERVER["DOCUMENT_ROOT"] ?? dirname(__DIR__), "/");
$file = $docRoot . $uri;

if ($uri !== "/" && is_file($file)) {
  return false; // let the built-in server serve the static file
}

if (str_starts_with($uri, "/scorer")) {
  require __DIR__ . "/index.php";
  return true;
}

http_response_code(404);
header("Content-Type: text/plain; charset=utf-8");
echo "Not Found";
return true;
