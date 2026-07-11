<?php
require __DIR__ . "/../config.php"; require_admin(); require __DIR__ . "/_upload.php";
$pdo = db();
$teams = $pdo->query("SELECT id,name FROM teams ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (post('action')==='create') {
    $photo = post('photo_url');
    if (!empty($_FILES['photo_file']['name'])) { [$u,$e]=save_upload($_FILES['photo_file'],'player'); if(!$e){ $photo=$u; } }
    try {
      $stmt=$pdo->prepare("INSERT INTO players (team_id,first_name,last_name,birth_date,number,position,photo_url,bio) VALUES (?,?,?,?,?,?,?,?)");
      $stmt->execute([post('team_id')?:null, post('first_name'), post('last_name'), post('birth_date')?:null, post('number'), post('position'), $photo, post('bio')]);
    } catch (Throwable $e) {
      // fallback sin bio
      $stmt=$pdo->prepare("INSERT INTO players (team_id,first_name,last_name,number,position,photo_url) VALUES (?,?,?,?,?,?)");
      $stmt->execute([post('team_id')?:null, post('first_name'), post('last_name'), post('number'), post('position'), $photo]);
    }
    $id=$pdo->lastInsertId();
    SqlDialect::insertIgnore($pdo, "player_stats", ["player_id"], [(int)$id]);
    flash("Jugador creado");
  } elseif (post('action')==='upload_photo') {
    $playerId = (int)post('id');
    if (!empty($_FILES['photo_file']['name'])) {
      [$photo, $err] = save_upload($_FILES['photo_file'], 'player');
      if ($err) {
        flash("No se pudo subir la foto: " . $err);
      } else {
        $pdo->prepare("UPDATE players SET photo_url=? WHERE id=?")->execute([$photo, $playerId]);
        flash("Foto del jugador actualizada");
      }
    } else {
      flash("Selecciona una foto antes de guardar");
    }
  } elseif (post('action')==='delete') {
    $pdo->prepare("DELETE FROM players WHERE id=?")->execute([post('id')]);
    flash("Jugador eliminado");
  }
  header("Location: /admin/players.php"); exit;
}

$players = $pdo->query("SELECT p.*, t.name team_name, COALESCE(ps.games_played,0) games_played FROM players p LEFT JOIN teams t ON t.id=p.team_id LEFT JOIN player_stats ps ON ps.player_id=p.id ORDER BY t.name, p.last_name, p.first_name")->fetchAll();
$eligiblePlayers = array_values(array_filter($players, 'postseason_eligible'));
$ineligiblePlayers = array_values(array_filter($players, fn($p) => !postseason_eligible($p)));
include __DIR__ . "/../partials/header.php"; ?>

<h1 class="text-2xl font-bold mb-4">Jugadores</h1>
<?php flashes(); ?>

<div class="grid md:grid-cols-2 gap-4">
  <div class="card">
    <h2 class="font-semibold mb-2">Crear jugador</h2>
    <form method="post" enctype="multipart/form-data" class="space-y-2">
      <input type="hidden" name="action" value="create"/>
      <div><label class="block mb-1">Equipo</label>
        <select name="team_id" class="w-full">
          <option value="">— Sin equipo —</option>
          <?php foreach ($teams as $t): ?><option value="<?= (int)$t['id'] ?>"><?= h($t['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <div><label class="block mb-1">Nombre</label><input name="first_name" required class="w-full"/></div>
        <div><label class="block mb-1">Apellido</label><input name="last_name" required class="w-full"/></div>
      </div>
      <div><label class="block mb-1">Fecha de nacimiento</label><input type="date" name="birth_date" required class="w-full"/></div>
      <div class="grid grid-cols-3 gap-2">
        <div><label class="block mb-1">#</label><input name="number" class="w-full"/></div>
        <div><label class="block mb-1">Posición</label><input name="position" class="w-full"/></div>
        <div><label class="block mb-1">Foto</label><input type="file" name="photo_file" accept="image/*" class="w-full"/></div>
      </div>
      <div><label class="block mb-1">Bio (opcional)</label><textarea name="bio" class="w-full" rows="3" placeholder="Perfil breve del jugador"></textarea></div>
      <button class="btn-primary">Guardar</button>
    </form>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Roster completo</h2>
    <table class="table">
      <thead><tr><th>Foto</th><th>#</th><th>Jugador</th><th>Equipo</th><th>Edad</th><th>Juegos</th><th>Semifinal</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($players as $p): ?>
        <?php $age = age_status($p['birth_date'] ?? null); $eligible = postseason_eligible($p); ?>
        <tr>
          <td>
            <?php if (!empty($p['photo_url'])): ?>
              <img src="<?= h($p['photo_url']) ?>" alt="<?= h($p['first_name'].' '.$p['last_name']) ?>" style="width:42px;height:42px;object-fit:cover;border-radius:6px;border:1px solid var(--line)"/>
            <?php else: ?>
              <span class="small">Sin foto</span>
            <?php endif; ?>
            <div><a class="small" href="#foto-jugador" onclick="document.getElementById('photo_player_id').value='<?= (int)$p['id'] ?>'; document.getElementById('photo_player_label').textContent='<?= h($p['first_name'].' '.$p['last_name']) ?>';">Subir foto</a></div>
          </td>
          <td><?= h($p['number']) ?></td>
          <td><?= h($p['first_name'].' '.$p['last_name']) ?></td>
          <td><?= h($p['team_name'] ?? '-') ?></td>
          <td><span class="badge <?= $age['ok'] ? 'badge-ok' : 'badge-bad' ?>"><?= h($age['label']) ?></span></td>
          <td><?= (int)$p['games_played'] ?>/3 legales</td>
          <td><span class="badge <?= $eligible ? 'badge-ok' : 'badge-bad' ?>"><?= $eligible ? 'Elegible' : 'Fuera' ?></span></td>
          <td>
            <form method="post" style="display:inline-block">
              <input type="hidden" name="action" value="delete"/>
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>"/>
              <button class="btn" onclick="return confirm('Eliminar jugador?')">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" id="foto-jugador" style="margin-top:16px">
  <h2 class="font-semibold mb-2">Subir foto de jugador</h2>
  <form method="post" enctype="multipart/form-data" class="space-y-2">
    <input type="hidden" name="action" value="upload_photo"/>
    <div class="grid md:grid-cols-2 gap-2">
      <div>
        <label class="block mb-1">Jugador</label>
        <select name="id" id="photo_player_id" class="w-full" required>
          <option value="">Seleccionar jugador</option>
          <?php foreach ($players as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= h(($p['team_name'] ? $p['team_name'].' - ' : '').$p['first_name'].' '.$p['last_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="small mt-2">Seleccionado: <strong id="photo_player_label">ninguno</strong></div>
      </div>
      <div>
        <label class="block mb-1">Foto</label>
        <input type="file" name="photo_file" accept="image/*" class="w-full" required/>
      </div>
    </div>
    <button class="btn-primary">Subir foto</button>
  </form>
</div>

<div class="grid md:grid-cols-2 gap-4" style="margin-top:16px">
  <div class="card">
    <h2 class="font-semibold mb-2">Roster elegible para semifinales</h2>
    <table class="table">
      <thead><tr><th>#</th><th>Jugador</th><th>Equipo</th><th>Juegos</th></tr></thead>
      <tbody>
        <?php foreach ($eligiblePlayers as $p): ?>
          <tr><td><?= h($p['number']) ?></td><td><?= h($p['first_name'].' '.$p['last_name']) ?></td><td><?= h($p['team_name'] ?? '-') ?></td><td><?= (int)$p['games_played'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="small mt-2">Solo aparecen jugadores con mínimo 3 juegos legales. Un juego legal requiere al menos 1 PA: AB + BB + HBP + SH + SF.</div>
  </div>
  <div class="card">
    <h2 class="font-semibold mb-2">Fuera del roster de semifinales</h2>
    <table class="table">
      <thead><tr><th>#</th><th>Jugador</th><th>Razón</th></tr></thead>
      <tbody>
        <?php foreach ($ineligiblePlayers as $p): ?>
          <?php $reasons=[]; if((int)$p['games_played']<3) $reasons[]='Menos de 3 juegos legales'; ?>
          <tr><td><?= h($p['number']) ?></td><td><?= h($p['first_name'].' '.$p['last_name']) ?></td><td><span class="badge badge-bad"><?= h(implode(', ', $reasons)) ?></span></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>
