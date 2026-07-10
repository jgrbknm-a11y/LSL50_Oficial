<?php
require __DIR__ . "/../config.php";
require_admin();

$pdo = db();
$season = active_season($pdo);
$currentPin = lsl_scorer_pin($pdo);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (post("action") === "update_pin") {
    $newPin = trim((string)post("new_pin"));
    $confirmPin = trim((string)post("confirm_pin"));
    if (!preg_match('/^\d{4,8}$/', $newPin)) {
      flash("El PIN debe tener de 4 a 8 números");
    } elseif ($newPin !== $confirmPin) {
      flash("La confirmación del PIN no coincide");
    } else {
      lsl_set_setting($pdo, "scorer_pin", $newPin);
      flash("PIN del anotador actualizado");
    }
  }
  header("Location: /admin/scorer.php");
  exit;
}

include __DIR__ . "/../partials/header.php";
?>

<h1 class="text-2xl font-bold mb-4">App del Anotador</h1>
<div class="notice">Temporada activa: <strong><?= h($season["name"]) ?></strong></div>
<?php flashes(); ?>

<div class="grid md:grid-cols-2 gap-4">
  <div class="card">
    <h2 class="font-semibold mb-2">Acceso de la tablet</h2>
    <p class="small">Esta app permite anotar juegos y actualizar estadísticas sin abrir el panel administrativo.</p>
    <table class="table">
      <tbody>
        <tr><th>App local</th><td><a href="/scorer/">/scorer/</a></td></tr>
        <tr><th>PIN actual</th><td><strong><?= h(str_repeat("*", max(strlen($currentPin), 4))) ?></strong></td></tr>
      </tbody>
    </table>
    <div class="mt-2">
      <a class="btn-primary" href="/scorer/">Abrir app del anotador</a>
    </div>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Cambiar PIN</h2>
    <form method="post" class="space-y-2">
      <input type="hidden" name="action" value="update_pin"/>
      <div>
        <label class="block mb-1">Nuevo PIN</label>
        <input name="new_pin" type="password" inputmode="numeric" pattern="[0-9]{4,8}" maxlength="8" autocomplete="new-password" class="w-full" required/>
      </div>
      <div>
        <label class="block mb-1">Confirmar PIN</label>
        <input name="confirm_pin" type="password" inputmode="numeric" pattern="[0-9]{4,8}" maxlength="8" autocomplete="new-password" class="w-full" required/>
      </div>
      <button class="btn-primary">Guardar PIN</button>
    </form>
    <div class="small mt-2">Usa solo números. Recomendado: 4 a 6 dígitos para que sea rápido desde la tablet.</div>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h2 class="font-semibold mb-2">Servidor exclusivo para tablet</h2>
  <p class="small">Para una tablet ajena al sistema principal, levanta el servidor exclusivo del anotador. Ese router bloquea el acceso a <strong>/admin</strong>.</p>
  <pre style="white-space:pre-wrap;background:#f1f4f8;border:1px solid var(--line);border-radius:6px;padding:12px">cd /Users/joseramirez/Documents/LSL50_Official_Project
php -S 0.0.0.0:8090 -t LSL50_Website_System LSL50_Website_System/scorer/router.php</pre>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>
