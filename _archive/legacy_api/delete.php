<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/config.php';
$token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ($_POST['token'] ?? '');
if (!$token || $token !== $ADMIN_TOKEN) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['dataset']) || !isset($input['id'])) { http_response_code(400); echo json_encode(['error'=>'Invalid payload']); exit; }
$dataset = $input['dataset']; $allowed = ['teams','players','schedule','games','media'];
if (!in_array($dataset, $allowed)) { http_response_code(400); echo json_encode(['error'=>'Invalid dataset']); exit; }
$path = $DATA_DIR . DIRECTORY_SEPARATOR . $dataset . '.json';
if (!file_exists($path)) { echo json_encode(['ok'=>true,'deleted'=>0]); exit; }
$data = json_decode(file_get_contents($path), true);
$key = ($dataset === 'schedule' || $dataset === 'games') ? 'game_id' : 'id';
$before = count($data['data']);
$data['data'] = array_values(array_filter($data['data'], function($row) use ($key, $input){ return ($row[$key] ?? null) !== $input['id']; }));
$after = count($data['data']);
$tmp = $path . '.tmp';
file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
rename($tmp, $path);
echo json_encode(['ok'=>true,'deleted'=>$before-$after]);