<?php
/**
 * PHP built-in server router for the main website + admin panel.
 *
 * Usage:
 *   php -S 127.0.0.1:8080 -t LSL50_Website_System LSL50_Website_System/router.php
 */

$uri = urldecode(parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/");
$uri = rtrim($uri, "/") ?: "/";

$blocked =
  str_starts_with($uri, "/data")
  || str_starts_with($uri, "/.env")
  || preg_match('#/(?:\.env(?:\..*)?|composer\.(?:json|lock)|artisan)$#', $uri);

if ($blocked) {
  http_response_code(403);
  header("Content-Type: text/plain; charset=utf-8");
  echo "Forbidden";
  return true;
}

$docRoot = rtrim($_SERVER["DOCUMENT_ROOT"] ?? __DIR__, "/");
$file = $docRoot . $uri;

if ($uri !== "/" && is_file($file)) {
  return false;
}

$routes = [
  "/" => "index.php",
  "/equipos" => "equipos.php",
  "/calendario" => "calendario.php",
  "/posiciones" => "posiciones.php",
  "/estadisticas" => "estadisticas.php",
  "/bateo-general" => "bateo-general.php",
  "/pitcheo-general" => "pitcheo-general.php",
  "/noticias" => "noticias.php",
  "/juego" => "juego.php",
  "/admin" => "admin/index.php",
];

if (isset($routes[$uri])) {
  require __DIR__ . "/" . $routes[$uri];
  return true;
}

if (preg_match('#^/noticias/([a-z0-9-]+)$#', $uri, $m)) {
  $_GET["slug"] = $m[1];
  require __DIR__ . "/noticia.php";
  return true;
}

if ($uri === "/news.php" || str_starts_with($uri, "/news.php")) {
  $id = (int)($_GET["id"] ?? 0);
  if ($id > 0) {
    header("Location: /noticias/noticia-{$id}", true, 301);
    exit;
  }
}

return false;
