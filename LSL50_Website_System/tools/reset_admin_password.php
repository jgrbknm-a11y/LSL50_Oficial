<?php
/**
 * Reset admin password (local dev only).
 *
 *   php LSL50_Website_System/tools/reset_admin_password.php legendssoftball50@gmail.com Admin1234
 */
declare(strict_types=1);

require __DIR__ . "/../config.php";

$email = strtolower(trim($argv[1] ?? ""));
$password = (string)($argv[2] ?? "");

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  fwrite(STDERR, "Uso: php tools/reset_admin_password.php email@dominio.com NuevaPassword\n");
  exit(1);
}
if (strlen($password) < 8) {
  fwrite(STDERR, "La contraseña debe tener al menos 8 caracteres.\n");
  exit(1);
}

$pdo = db();
$hash = password_hash($password, PASSWORD_DEFAULT);
$user = admin_find_by_email($pdo, $email);

if ($user) {
  $pdo->prepare("UPDATE users SET password_hash = ?, role = 'admin' WHERE id = ?")
    ->execute([$hash, (int)$user["id"]]);
  $userId = (int)$user["id"];
  $action = "updated";
} else {
  $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'admin')")
    ->execute([$email, $hash]);
  $userId = (int)$pdo->lastInsertId();
  $action = "created";
}

$ok = admin_attempt_login($pdo, $email, $password);
echo json_encode([
  "ok" => $ok,
  "action" => $action,
  "user_id" => $userId,
  "email" => $email,
  "message" => $ok
    ? "Contraseña aplicada. Ya puedes iniciar sesión en /admin/login.php"
    : "Usuario guardado pero la verificación de login falló.",
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($ok ? 0 : 1);
