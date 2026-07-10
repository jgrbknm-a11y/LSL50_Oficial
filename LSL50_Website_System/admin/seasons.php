<?php
require __DIR__ . "/../config.php"; require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (post('action') === 'close_start') {
    $nextName = trim(post('next_name')) ?: "Nueva Temporada";
    $nextStart = post('next_start') ?: date("Y-m-d");
    $keepRoster = post('keep_roster') === '1';
    try {
      archive_and_start_season($pdo, $nextName, $nextStart, $keepRoster);
      flash("Temporada archivada. Nueva temporada iniciada con estadísticas en cero.");
    } catch (Throwable $e) {
      flash("No se pudo cerrar la temporada: " . $e->getMessage());
    }
    header("Location: /admin/seasons.php"); exit;
  }
}

$active = active_season($pdo);
$seasons = $pdo->query("SELECT * FROM seasons ORDER BY id DESC")->fetchAll();
$archives = $pdo->query("SELECT id, season_id, season_name, created_at FROM season_archives ORDER BY id DESC")->fetchAll();
$counts = [
  "players" => (int)$pdo->query("SELECT COUNT(*) FROM players")->fetchColumn(),
  "games" => (int)$pdo->query("SELECT COUNT(*) FROM games")->fetchColumn(),
  "player_stats" => (int)$pdo->query("SELECT COUNT(*) FROM player_stats")->fetchColumn(),
];

include __DIR__ . "/../partials/header.php";
?>

<h1 class="text-2xl font-bold mb-4">Temporadas</h1>
<?php flashes(); ?>

<div class="notice">Temporada activa: <strong><?= h($active['name']) ?></strong></div>

<div class="grid md:grid-cols-2 gap-4">
  <div class="card">
    <h2 class="font-semibold mb-2">Cerrar temporada activa</h2>
    <div class="warning">
      Al cerrar una temporada, el sistema guarda un historial separado con equipos, jugadores, juegos y estadísticas.
      Luego reinicia juegos, anotaciones, posiciones y estadísticas para comenzar en cero.
    </div>
    <form method="post" class="space-y-2">
      <input type="hidden" name="action" value="close_start"/>
      <div><label class="block mb-1">Nombre de la próxima temporada</label><input name="next_name" class="w-full" placeholder="Primavera 2027" required></div>
      <div><label class="block mb-1">Fecha de inicio</label><input type="date" name="next_start" value="<?= h(date('Y-m-d')) ?>" class="w-full"></div>
      <div>
        <label><input type="checkbox" name="keep_roster" value="1" checked> Mantener/importar roster actual para la nueva temporada</label>
      </div>
      <button class="btn-primary" onclick="return confirm('Cerrar temporada activa y reiniciar estadísticas?')">Cerrar e iniciar nueva temporada</button>
    </form>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Estado actual</h2>
    <table class="table">
      <tbody>
        <tr><th>Jugadores activos</th><td><?= (int)$counts['players'] ?></td></tr>
        <tr><th>Juegos temporada activa</th><td><?= (int)$counts['games'] ?></td></tr>
        <tr><th>Filas de estadísticas</th><td><?= (int)$counts['player_stats'] ?></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="grid md:grid-cols-2 gap-4" style="margin-top:16px">
  <div class="card">
    <h2 class="font-semibold mb-2">Historial de temporadas</h2>
    <table class="table">
      <thead><tr><th>Temporada</th><th>Estado</th><th>Inicio</th><th>Archivo</th></tr></thead>
      <tbody>
        <?php foreach ($seasons as $s): ?>
          <tr>
            <td><?= h($s['name']) ?></td>
            <td><?= h($s['status']) ?></td>
            <td><?= h($s['starts_at'] ?? '') ?></td>
            <td><?= h($s['archived_at'] ?? '-') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Snapshots guardados</h2>
    <table class="table">
      <thead><tr><th>ID</th><th>Temporada</th><th>Guardado</th></tr></thead>
      <tbody>
        <?php foreach ($archives as $a): ?>
          <tr><td><?= (int)$a['id'] ?></td><td><?= h($a['season_name']) ?></td><td><?= h($a['created_at']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>
