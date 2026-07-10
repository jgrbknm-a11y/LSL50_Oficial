<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/config.php';

$token = $_POST['token'] ?? ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '');
if (!$token || $token !== $ADMIN_TOKEN) {
  http_response_code(401);
  echo json_encode(['error'=>'Unauthorized']);
  exit;
}

$type = $_POST['type'] ?? 'tournament'; // tournament | team | player
$title = $_POST['title'] ?? '';

if (!isset($_FILES['file'])) {
  http_response_code(400);
  echo json_encode(['error'=>'No file uploaded']);
  exit;
}

$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(['error'=>'Upload error','code'=>$f['error']]);
  exit;
}

$allowed = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/webp'=>'.webp'];
$mime = mime_content_type($f['tmp_name']);
if (!isset($allowed[$mime])) {
  http_response_code(400);
  echo json_encode(['error'=>'Unsupported type','mime'=>$mime]);
  exit;
}

if ($f['size'] > 7*1024*1024) { // 7MB
  http_response_code(400);
  echo json_encode(['error'=>'File too large']);
  exit;
}

$ext = $allowed[$mime];
$uid = bin2hex(random_bytes(8));
$basename = date('Ymd_His') . '_' . $uid . $ext;

$uploadsDir = realpath(__DIR__ . '/../assets/uploads');
if ($uploadsDir === false) {
  http_response_code(500);
  echo json_encode(['error'=>'Uploads dir not found']);
  exit;
}
$dest = $uploadsDir . DIRECTORY_SEPARATOR . $basename;
if (!move_uploaded_file($f['tmp_name'], $dest)) {
  http_response_code(500);
  echo json_encode(['error'=>'Move failed']);
  exit;
}

// Append to media.json
$mediaPath = realpath(__DIR__ . '/../assets/data') . DIRECTORY_SEPARATOR . 'media.json';
$data = ['name'=>'media','data'=>[]];
if (file_exists($mediaPath)) {
  $data = json_decode(file_get_contents($mediaPath), true) ?: $data;
}
$item = [
  'id' => 'M-' . strtoupper(substr($uid,0,6)),
  'type' => $type,
  'title' => $title,
  'src' => '/assets/uploads/' . $basename,
  'created_at' => date('c')
];
$data['data'][] = $item;
file_put_contents($mediaPath, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

echo json_encode(['ok'=>true,'item'=>$item]);
