<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/config.php';
$allowed = ['teams','players','stats','schedule','games','media'];
$name = $_GET['name'] ?? null;
if (!$name || !in_array($name, $allowed)) { http_response_code(400); echo json_encode(['error'=>'Invalid or missing name']); exit; }
$path = $DATA_DIR . DIRECTORY_SEPARATOR . $name . '.json';
if (!file_exists($path)) { echo json_encode(['ok'=>true,'name'=>$name,'data'=>[]]); exit; }
$raw = file_get_contents($path);
echo $raw;