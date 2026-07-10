<?php
require __DIR__ . "/../config.php";

if (post("action") === "logout" || getv("action") === "logout") {
  admin_logout();
  header("Location: /admin/login.php");
  exit;
}

require_admin();

$pdo = db();
$userId = admin_session_user_id();

if ($_SERVER["REQUEST_METHOD"] === "POST" && post("action") === "change_password") {
  $newPassword = (string)post("new_password");
  $confirm = (string)post("confirm_password");
  if ($newPassword !== $confirm) {
    flash("La confirmación de la nueva contraseña no coincide.");
  } else {
    $err = admin_change_password($pdo, $userId, (string)post("current_password"), $newPassword);
    flash($err !== "" ? $err : "Contraseña actualizada.");
  }
  header("Location: /admin/account.php");
  exit;
}

include __DIR__ . "/../partials/header.php";
?>

<h1 class="text-2xl font-bold mb-4">Cuenta de administración</h1>
<?php flashes(); ?>

<div class="grid md:grid-cols-2 gap-4">
  <div class="card">
    <h2 class="font-semibold mb-2">Sesión</h2>
    <table class="table">
      <tbody>
        <tr><th>Correo</th><td><?= h(admin_current_email()) ?></td></tr>
        <tr><th>Rol</th><td>admin</td></tr>
      </tbody>
    </table>
    <form method="post" class="mt-2">
      <input type="hidden" name="action" value="logout">
      <button class="btn" type="submit">Cerrar sesión</button>
    </form>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Cambiar contraseña</h2>
    <form method="post" class="space-y-2">
      <input type="hidden" name="action" value="change_password">
      <div>
        <label class="block mb-1">Contraseña actual</label>
        <input name="current_password" type="password" class="w-full" required autocomplete="current-password">
      </div>
      <div>
        <label class="block mb-1">Nueva contraseña (mín. 8)</label>
        <input name="new_password" type="password" class="w-full" required minlength="8" autocomplete="new-password">
      </div>
      <div>
        <label class="block mb-1">Confirmar nueva</label>
        <input name="confirm_password" type="password" class="w-full" required minlength="8" autocomplete="new-password">
      </div>
      <button class="btn-primary" type="submit">Guardar</button>
    </form>
  </div>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>
