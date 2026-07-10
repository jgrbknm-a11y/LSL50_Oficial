<?php
require __DIR__ . "/../config.php"; require_admin(); require __DIR__ . "/_upload.php";
$pdo = db();

function create_player_for_team(PDO $pdo, int $teamId, array $player): void {
  $first = trim($player['first_name'] ?? '');
  $last = trim($player['last_name'] ?? '');
  if ($first === '' && $last === '') return;
  if ($first === '' || $last === '') return;
  $stmt = $pdo->prepare("INSERT INTO players (team_id,first_name,last_name,birth_date,number,position) VALUES (?,?,?,?,?,?)");
  $stmt->execute([
    $teamId,
    $first,
    $last,
    trim($player['birth_date'] ?? '') ?: null,
    trim($player['number'] ?? ''),
    trim($player['position'] ?? ''),
  ]);
  $id = (int)$pdo->lastInsertId();
  $pdo->prepare("INSERT OR IGNORE INTO player_stats (player_id) VALUES (?)")->execute([$id]);
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (post('action')==='create') {
    $slug = post('slug') ?: strtolower(preg_replace('/\s+/', '-', trim(post('name'))));
    $logo = post('logo_url'); $cover = null;
    if (!empty($_FILES['logo_file']['name'])) { [$u,$e]=save_upload($_FILES['logo_file'],'logo'); if(!$e){ $logo=$u; } }
    if (!empty($_FILES['cover_file']['name'])){ [$u2,$e2]=save_upload($_FILES['cover_file'],'cover'); if(!$e2){ $cover=$u2; } }
    // Inserta de forma compatible (si la tabla no tiene columnas nuevas, usa las básicas)
    try {
      $stmt=$pdo->prepare("INSERT INTO teams (name, slug, city, logo_url, cover_url, description) VALUES (?,?,?,?,?,?)");
      $stmt->execute([post('name'), $slug, post('city'), $logo, $cover, post('description')]);
      $teamId = (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
      // fallback a columnas básicas
      $stmt=$pdo->prepare("INSERT INTO teams (name, city, logo_url) VALUES (?,?,?)");
      $stmt->execute([post('name'), post('city'), $logo]);
      $teamId = (int)$pdo->lastInsertId();
    }
    $createdPlayers = 0;
    foreach (($_POST['players'] ?? []) as $player) {
      $before = (int)$pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
      create_player_for_team($pdo, $teamId, $player);
      $after = (int)$pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
      if ($after > $before) $createdPlayers++;
    }
    flash($createdPlayers ? "Equipo creado con $createdPlayers jugadores" : "Equipo creado");
  } elseif (post('action')==='delete') {
    $pdo->prepare("DELETE FROM teams WHERE id=?")->execute([post('id')]);
    flash("Equipo eliminado");
  }
  header("Location: /admin/teams.php"); exit;
}

$teams = $pdo->query("SELECT t.*, COUNT(p.id) player_count FROM teams t LEFT JOIN players p ON p.team_id=t.id GROUP BY t.id ORDER BY t.created_at DESC")->fetchAll();
include __DIR__ . "/../partials/header.php"; ?>

<h1 class="text-2xl font-bold mb-4">Equipos</h1>
<?php flashes(); ?>

<div class="grid md:grid-cols-2 gap-4">
  <div class="card">
    <h2 class="font-semibold mb-2">Crear equipo</h2>
    <form method="post" enctype="multipart/form-data" class="space-y-2">
      <input type="hidden" name="action" value="create"/>
      <div><label class="block mb-1">Nombre</label><input name="name" required class="w-full"/></div>
      <div class="grid grid-cols-2 gap-2">
        <div><label class="block mb-1">Ciudad</label><input name="city" class="w-full"/></div>
        <div><label class="block mb-1">Slug (URL)</label><input name="slug" placeholder="tamarac-titans" class="w-full"/></div>
      </div>
      <div><label class="block mb-1">Logo (opcional)</label><input type="file" name="logo_file" accept="image/*" class="w-full"/></div>
      <div><label class="block mb-1">Portada (opcional)</label><input type="file" name="cover_file" accept="image/*" class="w-full"/></div>
      <div><label class="block mb-1">Descripción</label><textarea name="description" class="w-full" rows="3" placeholder="Historia, colores, manager..."></textarea></div>
      <hr/>
      <h3 class="font-semibold mb-2">Jugadores del equipo</h3>
      <div class="small mb-2">Puedes cargar el roster inicial aquí mismo. La fecha de nacimiento ayuda a validar la edad visualmente.</div>
      <div class="overflow-x-auto">
        <table class="table" id="rosterTable">
          <thead><tr><th>#</th><th>Nombre</th><th>Apellido</th><th>Fecha nac.</th><th>Pos.</th><th></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <button type="button" class="btn" onclick="addRosterRow()">Agregar jugador</button>
      <button class="btn-primary">Guardar</button>
    </form>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Listado</h2>
    <table class="table">
      <thead><tr><th>Equipo</th><th>Ciudad</th><th>Jugadores</th><th>Logo</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($teams as $t): ?>
        <tr>
          <td><?= h($t['name']) ?></td>
          <td><?= h($t['city'] ?? '') ?></td>
          <td><?= (int)$t['player_count'] ?></td>
          <td><?= !empty($t['logo_url']) ? '<img src="'.h($t['logo_url']).'" style="height:28px;border-radius:6px"/>' : '' ?></td>
          <td>
            <form method="post" style="display:inline-block">
              <input type="hidden" name="action" value="delete"/>
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>"/>
              <button class="btn" onclick="return confirm('Eliminar equipo?')">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
let rosterIndex = 0;
function addRosterRow(){
  const tbody = document.querySelector('#rosterTable tbody');
  const i = rosterIndex++;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input name="players[${i}][number]" class="w-full"></td>
    <td><input name="players[${i}][first_name]" class="w-full"></td>
    <td><input name="players[${i}][last_name]" class="w-full"></td>
    <td><input type="date" name="players[${i}][birth_date]" class="w-full"></td>
    <td><input name="players[${i}][position]" class="w-full"></td>
    <td><button type="button" class="btn" onclick="this.closest('tr').remove()">Quitar</button></td>
  `;
  tbody.appendChild(tr);
}
addRosterRow();
addRosterRow();
addRosterRow();
</script>

<?php include __DIR__ . "/../partials/footer.php"; ?>
