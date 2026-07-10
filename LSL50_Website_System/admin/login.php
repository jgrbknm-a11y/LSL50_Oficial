<?php
require __DIR__ . "/../config.php";

$pdo = db();
admin_ensure_seeded($pdo);

if (admin_logged_in()) {
  header("Location: /admin/index.php");
  exit;
}

$error = "";
$next = (string)getv("next", "/admin/index.php");
if ($next === "" || !str_starts_with($next, "/admin")) {
  $next = "/admin/index.php";
}

$userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$needsBootstrap = $userCount === 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = (string)post("email");
  $password = (string)post("password");
  $next = (string)post("next", "/admin/index.php");
  if ($next === "" || !str_starts_with($next, "/admin")) {
    $next = "/admin/index.php";
  }

  if ($needsBootstrap) {
    $error = "No hay administrador configurado. Crea el archivo .env en la raíz del proyecto (copia .env.example), define LSL50_ADMIN_EMAIL y LSL50_ADMIN_PASSWORD (mín. 8 caracteres), y recarga esta página.";
  } elseif (admin_attempt_login($pdo, $email, $password)) {
    header("Location: " . $next);
    exit;
  } else {
    $error = "Correo o contraseña incorrectos.";
  }
  $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
  $needsBootstrap = $userCount === 0;
}

$season = active_season($pdo);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LSL50 Admin — Acceso</title>
  <style>
    :root{--bg:#f4f6f8;--ink:#142033;--muted:#667085;--line:#d7dde5;--navy:#061b3b;--gold:#d7a72f;--card:#fff}
    *{box-sizing:border-box} body{margin:0;min-height:100vh;background:linear-gradient(160deg,#061b3b 0%,#0d2a52 45%,#f4f6f8 45%);color:var(--ink);font-family:Arial,Helvetica,sans-serif;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:100%;max-width:420px;background:var(--card);border:1px solid var(--line);border-radius:10px;padding:28px;box-shadow:0 12px 40px rgba(6,27,59,.18)}
    .brand{font-size:28px;font-weight:900;font-style:italic;color:var(--navy);margin:0 0 4px}
    .sub{color:var(--muted);font-size:14px;margin:0 0 22px}
    label{display:block;font-size:13px;color:var(--muted);font-weight:700;margin-bottom:4px}
    input{width:100%;border:1px solid var(--line);border-radius:6px;padding:10px 12px;font:inherit;margin-bottom:14px}
    button{width:100%;border:0;border-radius:6px;padding:12px;font-weight:800;cursor:pointer;background:var(--navy);color:white}
    .warning{background:#fff7e6;border:1px solid #f3c267;color:#7a4b00;border-radius:6px;padding:10px;margin-bottom:14px;font-size:14px}
    .notice{background:#ecfdf3;border:1px solid #abefc6;color:#067647;border-radius:6px;padding:10px;margin-bottom:14px;font-size:14px}
    .small{font-size:12px;color:var(--muted);margin-top:16px;line-height:1.4}
  </style>
</head>
<body>
  <div class="card">
    <div class="brand">LSL50</div>
    <p class="sub">Panel de administración · <?= h($season["name"] ?? "") ?></p>

    <?php if ($needsBootstrap): ?>
      <div class="warning">
        Falta el administrador inicial. Copia <code>.env.example</code> a <code>.env</code> en la raíz del proyecto,
        define <code>LSL50_ADMIN_EMAIL</code> y <code>LSL50_ADMIN_PASSWORD</code>, y vuelve a entrar.
        La contraseña se guarda hasheada en la base; no queda en archivos públicos.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="warning"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (!$needsBootstrap): ?>
      <form method="post" autocomplete="on">
        <input type="hidden" name="next" value="<?= h($next) ?>">
        <label>Correo</label>
        <input name="email" type="email" required autofocus autocomplete="username" value="<?= h((string)post("email")) ?>">
        <label>Contraseña</label>
        <input name="password" type="password" required autocomplete="current-password">
        <button type="submit">Entrar</button>
      </form>
    <?php endif; ?>

    <p class="small">
      El acceso del anotador en tablet (<code>/scorer/</code>) usa su propio PIN y no comparte esta sesión.
    </p>
  </div>
</body>
</html>
