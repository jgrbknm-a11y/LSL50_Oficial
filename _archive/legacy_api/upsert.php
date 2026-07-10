<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/config.php';
$token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ($_POST['token'] ?? '');
if (!$token || $token !== $ADMIN_TOKEN) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['dataset']) || !isset($input['item'])) { http_response_code(400); echo json_encode(['error'=>'Invalid payload']); exit; }
$dataset = $input['dataset']; $allowed = ['teams','players','schedule','games','stats','media'];
if (!in_array($dataset, $allowed)) { http_response_code(400); echo json_encode(['error'=>'Invalid dataset']); exit; }
$path = $DATA_DIR . DIRECTORY_SEPARATOR . $dataset . '.json';
$data = ['name'=>$dataset,'data'=>[]];
if (file_exists($path)) { $data = json_decode(file_get_contents($path), true) ?: $data; }
$item = $input['item'];
$key = ($dataset === 'schedule' || $dataset === 'games') ? 'game_id' : 'id';
if (!isset($item[$key]) || trim($item[$key])==='') {
  $prefix = ['teams'=>'T-','players'=>'P-','schedule'=>'G-','games'=>'G-','stats'=>'S-'][$dataset] ?? 'X-';
  $item[$key] = $prefix . str_pad(strval(count($data['data'])+1), 3, '0', STR_PAD_LEFT);
}
$updated=false;
for ($i=0; $i<count($data['data']); $i++) {
  if (isset($data['data'][$i][$key]) && $data['data'][$i][$key] === $item[$key]) { $data['data'][$i] = array_merge($data['data'][$i], $item); $updated=true; break; }
}
if (!$updated) { $data['data'][] = $item; }
$tmp = $path . '.tmp';
file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
rename($tmp, $path);
echo json_encode(['ok'=>true,'dataset'=>$dataset,'id'=>$item[$key],'updated'=>$updated]);