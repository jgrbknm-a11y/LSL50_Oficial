<?php

/**
 * Admin panel authentication (separate session keys from the scorer tablet PIN).
 * Credentials: bcrypt hash in SQLite `users`; bootstrap password only from env.
 */
function admin_session_user_id(): int
{
  return (int)($_SESSION["lsl50_admin_user_id"] ?? 0);
}

function admin_logged_in(): bool
{
  return admin_session_user_id() > 0;
}

function admin_current_email(): string
{
  return (string)($_SESSION["lsl50_admin_email"] ?? "");
}

function admin_find_by_email(PDO $pdo, string $email): ?array
{
  $stmt = $pdo->prepare("SELECT id, email, password_hash, role FROM users WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $row = $stmt->fetch();
  return $row ?: null;
}

function admin_ensure_seeded(PDO $pdo): void
{
  static $done = false;
  if ($done) {
    return;
  }
  $done = true;

  $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
  if ($count > 0) {
    return;
  }

  $email = lsl_env("LSL50_ADMIN_EMAIL", "admin@lsl50.local");
  $password = lsl_env("LSL50_ADMIN_PASSWORD");
  if ($password === null || $password === "") {
    return;
  }
  if (strlen($password) < 8) {
    return;
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);
  $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'admin')")
    ->execute([$email, $hash]);
}

function admin_attempt_login(PDO $pdo, string $email, string $password): bool
{
  admin_ensure_seeded($pdo);
  $email = strtolower(trim($email));
  if ($email === "" || $password === "") {
    return false;
  }
  $user = admin_find_by_email($pdo, $email);
  if (!$user || !password_verify($password, (string)$user["password_hash"])) {
    return false;
  }
  if (password_needs_rehash((string)$user["password_hash"], PASSWORD_DEFAULT)) {
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
      ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user["id"]]);
  }
  session_regenerate_id(true);
  $_SESSION["lsl50_admin_user_id"] = (int)$user["id"];
  $_SESSION["lsl50_admin_email"] = (string)$user["email"];
  unset($_SESSION["local_admin"]);
  return true;
}

function admin_logout(): void
{
  unset($_SESSION["lsl50_admin_user_id"], $_SESSION["lsl50_admin_email"], $_SESSION["local_admin"], $_SESSION["lsl50_admin_csrf"]);
}

function admin_csrf_token(): string
{
  if (empty($_SESSION["lsl50_admin_csrf"]) || !is_string($_SESSION["lsl50_admin_csrf"])) {
    $_SESSION["lsl50_admin_csrf"] = bin2hex(random_bytes(32));
  }
  return $_SESSION["lsl50_admin_csrf"];
}

function admin_verify_csrf(?string $token): bool
{
  return is_string($token) && $token !== "" && hash_equals(admin_csrf_token(), $token);
}

function admin_change_password(PDO $pdo, int $userId, string $currentPassword, string $newPassword): string
{
  $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE id = ? LIMIT 1");
  $stmt->execute([$userId]);
  $user = $stmt->fetch();
  if (!$user) {
    return "Usuario no encontrado.";
  }
  if (!password_verify($currentPassword, (string)$user["password_hash"])) {
    return "La contraseña actual no es correcta.";
  }
  if (strlen($newPassword) < 8) {
    return "La nueva contraseña debe tener al menos 8 caracteres.";
  }
  $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
    ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
  return "";
}

function require_admin(): void
{
  admin_ensure_seeded(db());
  if (admin_logged_in()) {
    return;
  }
  $next = $_SERVER["REQUEST_URI"] ?? "/admin/";
  header("Location: /admin/login.php?next=" . rawurlencode($next));
  exit;
}
