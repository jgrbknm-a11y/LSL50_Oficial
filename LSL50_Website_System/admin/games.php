<?php
require __DIR__ . "/../config.php"; require_admin();
$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season['id'];
$teams = $pdo->query("SELECT id,name FROM teams ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (post('action')==='create_next_schedule') {
    $nextDateStmt = $pdo->prepare("SELECT MIN(game_date) FROM schedule_entries s
      WHERE s.season_id=? AND s.stage='Regular' AND s.home_team_id IS NOT NULL AND s.away_team_id IS NOT NULL
        AND NOT EXISTS (
          SELECT 1 FROM games g
          WHERE COALESCE(g.season_id, ?) = ?
            AND g.game_date=s.game_date
            AND g.home_team_id=s.home_team_id
            AND g.away_team_id=s.away_team_id
        )");
    $nextDateStmt->execute([$seasonId, $seasonId, $seasonId]);
    $nextDate = $nextDateStmt->fetchColumn();
    if (!$nextDate) {
      flash("No hay juegos pendientes del calendario para crear.");
      header("Location: /admin/games.php"); exit;
    }
    $entries = $pdo->prepare("SELECT * FROM schedule_entries
      WHERE season_id=? AND stage='Regular' AND game_date=?
      ORDER BY game_time, id");
    $entries->execute([$seasonId, $nextDate]);
    $created = 0;
    foreach ($entries->fetchAll() as $entry) {
      $exists = $pdo->prepare("SELECT id FROM games WHERE COALESCE(season_id, ?) = ? AND game_date=? AND home_team_id=? AND away_team_id=?");
      $exists->execute([$seasonId, $seasonId, $entry["game_date"], $entry["home_team_id"], $entry["away_team_id"]]);
      if ($exists->fetchColumn()) continue;
      $pdo->prepare("INSERT INTO games (season_id,home_team_id,away_team_id,game_date,location,final_home,final_away,notes)
        VALUES (?,?,?,?,?,?,?,?)")->execute([
          $seasonId,
          $entry["home_team_id"],
          $entry["away_team_id"],
          $entry["game_date"],
          trim(($entry["field"] ?: "Campo Principal") . " - " . $entry["game_time"]),
          0,
          0,
          "Creado desde calendario oficial"
        ]);
      $created++;
    }
    flash($created ? "Jornada creada desde el calendario oficial: $created juegos." : "Esa jornada ya estaba creada.");
    header("Location: /admin/games.php"); exit;
  }
  if (post('action')==='create_game') {
    if (!post('game_date')) {
      flash("Debes seleccionar la fecha del juego.");
      header("Location: /admin/games.php"); exit;
    }
    if ((int)post('home_team_id') === (int)post('away_team_id')) {
      flash("Home y Away no pueden ser el mismo equipo.");
      header("Location: /admin/games.php"); exit;
    }
    $pdo->prepare("INSERT INTO games (season_id,home_team_id,away_team_id,game_date,location,final_home,final_away,notes)
      VALUES (?,?,?,?,?,?,?,?)")->execute([$seasonId, post('home_team_id'), post('away_team_id'), post('game_date'), post('location'), post('final_home')?:0, post('final_away')?:0, post('notes')]);
    flash("Juego creado"); header("Location: /admin/games.php"); exit;
  }
  if (post('action')==='save_box') {
    $game_id = (int)post('game_id');
    $postedRows = $_POST["rows"] ?? null;
    $payload = $postedRows ? ["rows" => $postedRows] : (json_decode(post('payload'), true) ?: []);
    try {
      lsl_save_game_box($pdo, $seasonId, $game_id, $payload['rows'] ?? [], (int)post("winning_pitcher_id"));
      flash("Cuaderno guardado: estadísticas, marcador final y posiciones actualizadas");
    } catch (RuntimeException $e) {
      flash("No se pudo guardar: " . $e->getMessage());
    }
    header("Location: /admin/games.php?tab=scorer&game_id=".$game_id); exit;
  }
}

$games = $pdo->query("SELECT g.*, ht.name home_name, at.name away_name
  FROM games g JOIN teams ht ON ht.id=g.home_team_id JOIN teams at ON at.id=g.away_team_id
  WHERE COALESCE(g.season_id, $seasonId) = $seasonId
  ORDER BY g.game_date DESC, g.id DESC LIMIT 50")->fetchAll();

$players_all = $pdo->query("SELECT p.id, p.first_name || ' ' || p.last_name name, p.team_id, t.name team_name, p.number
  FROM players p LEFT JOIN teams t ON t.id=p.team_id ORDER BY t.name, p.last_name")->fetchAll();

$nextSchedule = $pdo->prepare("SELECT MIN(game_date) next_date FROM schedule_entries WHERE season_id=? AND stage='Regular'");
$nextSchedule->execute([$seasonId]);
$defaultGameDate = $nextSchedule->fetchColumn() ?: date("Y-m-d");
$defaultHomeId = (int)($teams[0]["id"] ?? 0);
$defaultAwayId = (int)($teams[1]["id"] ?? $defaultHomeId);

include __DIR__ . "/../partials/header.php"; ?>

<h1 class="text-2xl font-bold mb-4">Juegos & Cuaderno de Anotaciones</h1>
<div class="notice">Temporada activa: <strong><?= h($season['name']) ?></strong></div>
<?php flashes(); ?>

<div class="grid md:grid-cols-2 gap-4">
  <div class="card">
    <h2 class="font-semibold mb-2">Crear juego</h2>
    <form method="post" style="margin-bottom:12px">
      <input type="hidden" name="action" value="create_next_schedule"/>
      <button class="btn-primary">Crear próxima jornada desde calendario oficial</button>
    </form>
    <div class="small mb-2">Para crear un juego manual, selecciona equipos diferentes y una fecha.</div>
    <form method="post" class="space-y-2">
      <input type="hidden" name="action" value="create_game"/>
      <div class="grid grid-cols-2 gap-2">
        <div><label class="block mb-1">Home</label>
          <select name="home_team_id" class="w-full" required><?php foreach ($teams as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$t["id"] === $defaultHomeId ? "selected" : "" ?>><?= h($t['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div><label class="block mb-1">Away</label>
          <select name="away_team_id" class="w-full" required><?php foreach ($teams as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$t["id"] === $defaultAwayId ? "selected" : "" ?>><?= h($t['name']) ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-2">
        <div><label class="block mb-1">Fecha</label><input type="date" name="game_date" value="<?= h($defaultGameDate) ?>" required class="w-full"/></div>
        <div><label class="block mb-1">Lugar</label><input name="location" class="w-full"/></div>
        <div><label class="block mb-1">Final (H-A)</label><div class="flex gap-1"><input name="final_home" placeholder="H" class="w-full"/><input name="final_away" placeholder="A" class="w-full"/></div></div>
      </div>
      <div><label class="block mb-1">Notas</label><textarea name="notes" class="w-full"></textarea></div>
      <button class="btn-primary">Guardar</button>
    </form>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Últimos juegos</h2>
    <table class="table">
      <thead><tr><th>Fecha</th><th>Juego</th><th>Final</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($games as $g): ?>
        <tr>
          <td><?= h($g['game_date']) ?></td>
          <td><?= h($g['home_name']) ?> vs <?= h($g['away_name']) ?></td>
          <td><?= (int)$g['final_home'] ?>-<?= (int)$g['final_away'] ?></td>
          <td><a class="btn" href="/admin/games.php?tab=scorer&game_id=<?= (int)$g['id'] ?>">Anotar</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (getv('tab')==='scorer'):
  $gid = (int)getv('game_id');
  $gameStmt = $pdo->prepare("SELECT g.*, ht.name home_name, at.name away_name
    FROM games g JOIN teams ht ON ht.id=g.home_team_id JOIN teams at ON at.id=g.away_team_id
    WHERE g.id=? AND COALESCE(g.season_id, $seasonId) = $seasonId");
  $gameStmt->execute([$gid]);
  $game = $gameStmt->fetch();
  if ($game): ?>
  <hr/>
  <h2 class="text-xl font-semibold mb-2">Anotación: <?= h($game['home_name']) ?> vs <?= h($game['away_name']) ?> (<?= h($game['game_date']) ?>)</h2>
  <?php
    $rowsByPlayer = [];
    $savedStmt = $pdo->prepare("SELECT gps.*, p.first_name || ' ' || p.last_name player_name, p.number, t.name team_name
      FROM game_player_stats gps
      JOIN players p ON p.id=gps.player_id
      JOIN teams t ON t.id=gps.team_id
      WHERE gps.game_id=?
      ORDER BY t.name, p.last_name, p.first_name");
    $savedStmt->execute([$gid]);
    foreach ($savedStmt->fetchAll() as $row) {
      $rowsByPlayer[(int)$row["player_id"]] = $row;
    }

    $rosterStmt = $pdo->prepare("SELECT p.id player_id, p.team_id, p.number, p.first_name || ' ' || p.last_name player_name, t.name team_name
      FROM players p JOIN teams t ON t.id=p.team_id
      WHERE p.team_id IN (?, ?)
      ORDER BY CASE WHEN p.team_id=? THEN 0 ELSE 1 END, CAST(NULLIF(p.number, '') AS INTEGER), p.last_name, p.first_name");
    $rosterStmt->execute([(int)$game["home_team_id"], (int)$game["away_team_id"], (int)$game["home_team_id"]]);
    $scorerRows = [];
    foreach ($rosterStmt->fetchAll() as $playerRow) {
      $saved = $rowsByPlayer[(int)$playerRow["player_id"]] ?? [];
      $scorerRows[] = array_merge([
        "player_id" => $playerRow["player_id"],
        "team_id" => $playerRow["team_id"],
        "number" => $playerRow["number"],
        "player_name" => $playerRow["player_name"],
        "team_name" => $playerRow["team_name"],
        "AB" => 0, "H" => 0, "dbl" => 0, "tpl" => 0, "R" => 0, "RBI" => 0, "HR" => 0, "BB" => 0, "SO" => 0, "SB" => 0, "HBP" => 0, "SH" => 0, "SF" => 0, "E" => 0,
      ], $saved);
      unset($rowsByPlayer[(int)$playerRow["player_id"]]);
    }
    foreach ($rowsByPlayer as $saved) $scorerRows[] = $saved;

    $homeTotal = 0; $awayTotal = 0;
    foreach ($scorerRows as $row) {
      if ((int)$row["team_id"] === (int)$game["home_team_id"]) $homeTotal += (int)$row["R"];
      if ((int)$row["team_id"] === (int)$game["away_team_id"]) $awayTotal += (int)$row["R"];
    }
    $statKeys = ["AB", "H", "dbl", "tpl", "R", "RBI", "HR", "BB", "SO", "SB", "HBP", "SH", "SF", "E"];
    $extraPlayers = array_values(array_filter($players_all, fn($p) => !in_array((int)$p["id"], array_map(fn($r) => (int)$r["player_id"], $scorerRows), true)));
    $pitcherOptions = array_values(array_filter($scorerRows, fn($row) => (int)($row["team_id"] ?? 0) === (int)$game["home_team_id"] || (int)($row["team_id"] ?? 0) === (int)$game["away_team_id"]));
  ?>
  <form method="post" id="boxForm">
    <input type="hidden" name="action" value="save_box"/>
    <input type="hidden" name="game_id" value="<?= (int)$gid ?>"/>
    <input type="hidden" name="payload" id="payload"/>
    <div class="notice">
      El cuaderno carga automáticamente el roster de ambos equipos. Al guardar, las carreras anotadas actualizan el marcador:
      <strong><?= h($game["home_name"]) ?> <span id="homeScorePreview"><?= (int)$homeTotal ?></span> - <?= h($game["away_name"]) ?> <span id="awayScorePreview"><?= (int)$awayTotal ?></span></strong>.
      Un juego legal para semifinales/final cuenta cuando el jugador tiene al menos 1 PA: AB + BB + HBP + SH + SF.
    </div>
    <div class="card" style="margin-bottom:12px">
      <label class="block mb-1">Pitcher ganador</label>
      <select name="winning_pitcher_id" class="w-full">
        <option value="">Sin pitcher ganador / empate</option>
        <?php foreach ($pitcherOptions as $p): ?>
          <option value="<?= (int)$p["player_id"] ?>" <?= (int)($game["winning_pitcher_id"] ?? 0) === (int)$p["player_id"] ? "selected" : "" ?>>
            <?= h($p["team_name"] . " - " . $p["player_name"]) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="small mt-2">Este dato alimenta el liderato de pitchers con más juegos ganados. Si el marcador queda empatado, no se asigna victoria.</div>
    </div>
    <div class="overflow-x-auto">
      <table class="table" id="boxTable">
        <thead><tr><th>#</th><th>Jugador</th><th>Equipo</th><th>AB</th><th>H</th><th>2B</th><th>3B</th><th>R</th><th>RBI</th><th>HR</th><th>BB</th><th>SO</th><th>SB</th><th>HBP</th><th>SH</th><th>SF</th><th>E</th><th></th></tr></thead>
        <tbody>
          <?php
            $rowIndex = 0;
            $lastTeam = null;
            foreach ($scorerRows as $row):
              if ($lastTeam !== (int)$row["team_id"]):
                $lastTeam = (int)$row["team_id"]; ?>
                <tr><td colspan="<?= 4 + count($statKeys) ?>" class="font-semibold"><?= h($row["team_name"]) ?></td></tr>
              <?php endif; ?>
              <tr data-scorer-row>
                <td><?= h($row["number"] ?: "-") ?></td>
                <td>
                  <?= h($row["player_name"]) ?>
                  <input type="hidden" name="rows[<?= $rowIndex ?>][player_id]" value="<?= (int)$row["player_id"] ?>"/>
                  <input type="hidden" name="rows[<?= $rowIndex ?>][team_id]" value="<?= (int)$row["team_id"] ?>"/>
                </td>
                <td data-team-id="<?= (int)$row["team_id"] ?>"><?= h($row["team_name"]) ?></td>
                <?php foreach ($statKeys as $key): ?>
                  <td><input class="stat-input" type="number" min="0" name="rows[<?= $rowIndex ?>][<?= h($key) ?>]" value="<?= (int)($row[$key] ?? 0) ?>"/></td>
                <?php endforeach; ?>
                <td><button type="button" class="btn" onclick="this.closest('tr').remove(); updateScorePreview();">X</button></td>
              </tr>
          <?php $rowIndex++; endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="flex gap-2 my-2">
      <select id="selPlayer" class="w-64">
        <?php foreach ($extraPlayers as $p): ?>
          <option value='<?= h(json_encode(["id"=>$p["id"],"name"=>$p["name"],"team_id"=>$p["team_id"],"team_name"=>$p["team_name"],"number"=>$p["number"] ?? ""], JSON_UNESCAPED_UNICODE)) ?>'>
            <?= h($p['team_name'] . " - " . $p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="btn" onclick="addRow()">Agregar jugador</button>
    </div>
    <button class="btn-primary">Guardar caja</button>
  </form>
  <script>
    let rowIndex = <?= (int)$rowIndex ?>;
    const homeTeamId = <?= (int)$game["home_team_id"] ?>;
    const awayTeamId = <?= (int)$game["away_team_id"] ?>;
    const statKeys = <?= json_encode($statKeys) ?>;
    function makeInputName(stat) { return `rows[${rowIndex}][${stat}]`; }
    function updateScorePreview(){
      let home = 0; let away = 0;
      document.querySelectorAll('#boxTable tr[data-scorer-row]').forEach(tr => {
        const teamId = parseInt(tr.querySelector('[data-team-id]').dataset.teamId || 0);
        const runsInput = tr.querySelector('input[name$="[R]"]');
        const runs = parseInt(runsInput?.value || 0);
        if (teamId === homeTeamId) home += runs;
        if (teamId === awayTeamId) away += runs;
      });
      document.getElementById('homeScorePreview').textContent = home;
      document.getElementById('awayScorePreview').textContent = away;
    }
    function addRow(){
      const sel = document.getElementById('selPlayer'); const data = JSON.parse(sel.value);
      const tr = document.createElement('tr');
      tr.dataset.scorerRow = '1';
      const statInputs = statKeys.map(stat => `<td><input class="stat-input" type="number" min="0" name="${makeInputName(stat)}" value="0"/></td>`).join('');
      tr.innerHTML = `
        <td>${data.number || '-'}</td>
        <td>${data.name}<input type="hidden" name="rows[${rowIndex}][player_id]" value="${data.id}"/><input type="hidden" name="rows[${rowIndex}][team_id]" value="${data.team_id}"/></td>
        <td data-team-id="${data.team_id}">${data.team_name||'-'}</td>
        ${statInputs}
        <td><button type="button" class="btn" onclick="this.closest('tr').remove(); updateScorePreview();">X</button></td>
      `;
      document.querySelector('#boxTable tbody').appendChild(tr);
      tr.querySelectorAll('.stat-input').forEach(input => input.addEventListener('input', updateScorePreview));
      rowIndex++;
    }
    document.querySelectorAll('.stat-input').forEach(input => input.addEventListener('input', updateScorePreview));
    updateScorePreview();
  </script>
<?php endif; endif; ?>

<?php include __DIR__ . "/../partials/footer.php"; ?>
