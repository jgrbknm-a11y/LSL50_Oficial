<?php
require __DIR__ . "/../config.php"; require_admin();
$pdo = db();
$players = $pdo->query("SELECT id, " . lsl_sql_full_name_bare() . " full_name FROM players ORDER BY last_name")->fetchAll();
$teams = $pdo->query("SELECT id, name FROM teams ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (post('action')==='create') {
    $pdo->prepare("INSERT INTO weekly_awards (week_start,week_end,player_id,team_id,award_type,description,media_url) VALUES (?,?,?,?,?,?,?)")
      ->execute([post('week_start'), post('week_end'), post('player_id')?:null, post('team_id')?:null, post('award_type'), post('description'), post('media_url')]);
    flash("Reconocimiento agregado");
  } elseif (post('action')==='delete') {
    $pdo->prepare("DELETE FROM weekly_awards WHERE id=?")->execute([post('id')]);
    flash("Reconocimiento eliminado");
  }
  header("Location: /admin/awards.php"); exit;
}

$items = $pdo->query("SELECT wa.*, (SELECT " . lsl_sql_full_name_bare() . " FROM players WHERE id=wa.player_id) player_name, (SELECT name FROM teams WHERE id=wa.team_id) team_name FROM weekly_awards wa ORDER BY week_start DESC")->fetchAll();
include __DIR__ . "/../partials/header.php"; ?>

<h1 class="text-2xl font-bold mb-4">Reconocimientos</h1>
<?php flashes(); ?>

<div class="grid md:grid-cols-2 gap-4">
  <div class="card">
    <h2 class="font-semibold mb-2">Agregar</h2>
    <form method="post" class="space-y-2">
      <input type="hidden" name="action" value="create"/>
      <div class="grid grid-cols-2 gap-2">
        <div><label class="block mb-1">Semana inicio</label><input type="date" name="week_start" required class="w-full"/></div>
        <div><label class="block mb-1">Semana fin</label><input type="date" name="week_end" required class="w-full"/></div>
      </div>
      <div><label class="block mb-1">Tipo de premio</label><input name="award_type" placeholder="Jugador de la Semana" required class="w-full"/></div>
      <div><label class="block mb-1">Jugador (opcional)</label>
        <select name="player_id" class="w-full"><option value="">—</option>
          <?php foreach ($players as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['full_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="block mb-1">Equipo (opcional)</label>
        <select name="team_id" class="w-full"><option value="">—</option>
          <?php foreach ($teams as $t): ?><option value="<?= (int)$t['id'] ?>"><?= h($t['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="block mb-1">Descripción</label><textarea name="description" class="w-full"></textarea></div>
      <div><label class="block mb-1">Media URL (opcional)</label><input name="media_url" class="w-full"/></div>
      <button class="btn-primary">Guardar</button>
    </form>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Listado</h2>
    <table class="table">
      <thead><tr><th>Semana</th><th>Tipo</th><th>Jugador/Equipo</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $i): ?>
        <tr>
          <td><?= h($i['week_start']) ?> → <?= h($i['week_end']) ?></td>
          <td><?= h($i['award_type']) ?></td>
          <td><?= h($i['player_name'] ?: $i['team_name'] ?: '-') ?></td>
          <td>
            <form method="post" style="display:inline-block">
              <input type="hidden" name="action" value="delete"/>
              <input type="hidden" name="id" value="<?= (int)$i['id'] ?>"/>
              <button class="btn" onclick="return confirm('Eliminar?')">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>
