<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/config.php';

$token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ($_POST['token'] ?? '');
if (!$token || $token !== $ADMIN_TOKEN) {
    http_response_code(401);
    echo json_encode(['error'=>'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['name']) || !isset($input['data'])) {
    http_response_code(400);
    echo json_encode(['error'=>'Invalid payload']);
    exit;
}

$allowed = ['teams','players','stats','schedule','games'];
$name = $input['name'];
if (!in_array($name, $allowed)) {
    http_response_code(400);
    echo json_encode(['error'=>'Invalid name']);
    exit;
}

$path = $DATA_DIR . DIRECTORY_SEPARATOR . $name . '.json';
$tmp  = $path . '.tmp';

// pretty print for human edits
$data = json_encode($input, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
if ($data === false) {
    http_response_code(400);
    echo json_encode(['error'=>'Encode failed']);
    exit;
}

// Save atomically
if (file_put_contents($tmp, $data) === false) {
    http_response_code(500);
    echo json_encode(['error'=>'Write temp failed']);
    exit;
}
if (!rename($tmp, $path)) {
    http_response_code(500);
    echo json_encode(['error'=>'Rename failed']);
    exit;
}

echo json_encode(['ok'=>true, 'saved'=>$name]);
