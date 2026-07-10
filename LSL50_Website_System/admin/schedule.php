<?php
require __DIR__ . "/../config.php"; require_admin();
$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season['id'];
$requiredTeamCount = 6;

function next_sunday(): string {
  $d = new DateTimeImmutable('today');
  return $d->modify('next sunday')->format('Y-m-d');
}

function schedule_date(string $start, int $week): string {
  return (new DateTimeImmutable($start))->modify("+$week week")->format('Y-m-d');
}

function round_robin_pairs(array $teams): array {
  $list = $teams;
  if (count($list) % 2 === 1) $list[] = ['id'=>null, 'name'=>'BYE'];
  $n = count($list);
  $rounds = [];
  for ($r = 0; $r < $n - 1; $r++) {
    $pairs = [];
    for ($i = 0; $i < $n / 2; $i++) {
      $a = $list[$i];
      $b = $list[$n - 1 - $i];
      if ($a['id'] && $b['id']) {
        $pairs[] = ($r % 2 === 0) ? [$a, $b] : [$b, $a];
      }
    }
    $rounds[] = $pairs;
    $fixed = array_shift($list);
    $last = array_pop($list);
    array_unshift($list, $fixed, $last);
  }
  return $rounds;
}

function insert_schedule_entry(PDO $pdo, array $row): void {
  $stmt = $pdo->prepare("INSERT INTO schedule_entries (season_id, stage, round_no, game_no, game_date, game_time, field, home_team_id, away_team_id, home_label, away_label, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    $row['season_id'], $row['stage'], $row['round_no'], $row['game_no'], $row['game_date'], $row['game_time'], $row['field'],
    $row['home_team_id'] ?? null, $row['away_team_id'] ?? null, $row['home_label'], $row['away_label'], $row['notes'] ?? ''
  ]);
}

function generate_schedule(PDO $pdo, int $seasonId, string $startDate, array $times, string $field, int $requiredTeamCount = 6): void {
  $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name")->fetchAll();
  if (count($teams) !== $requiredTeamCount) throw new RuntimeException("Esta temporada requiere exactamente $requiredTeamCount equipos. Actualmente hay " . count($teams) . ".");
  if (count($times) < 3) throw new RuntimeException("Debes definir los 3 horarios: 09:30, 11:30 y 01:30.");

  $pdo->beginTransaction();
  try {
    $pdo->prepare("DELETE FROM schedule_entries WHERE season_id=?")->execute([$seasonId]);
    $rounds = round_robin_pairs($teams);
    $gameNo = 1;
    $week = 0;
    for ($leg = 1; $leg <= 2; $leg++) {
      foreach ($rounds as $roundIndex => $pairs) {
        $date = schedule_date($startDate, $week);
        foreach ($pairs as $i => $pair) {
          [$home, $away] = $leg === 1 ? $pair : [$pair[1], $pair[0]];
          insert_schedule_entry($pdo, [
            'season_id'=>$seasonId, 'stage'=>'Regular', 'round_no'=>$week + 1, 'game_no'=>$gameNo++,
            'game_date'=>$date, 'game_time'=>$times[$i % count($times)], 'field'=>$field,
            'home_team_id'=>$home['id'], 'away_team_id'=>$away['id'],
            'home_label'=>$home['name'], 'away_label'=>$away['name'],
            'notes'=>"Vuelta $leg"
          ]);
        }
        $week++;
      }
    }

    $sf = [
      ['label'=>'Semifinal A', 'home'=>'1ro Regular', 'away'=>'4to Regular'],
      ['label'=>'Semifinal B', 'home'=>'2do Regular', 'away'=>'3ro Regular'],
    ];
    for ($g = 1; $g <= 3; $g++) {
      $date = schedule_date($startDate, $week++);
      foreach ($sf as $i => $series) {
        insert_schedule_entry($pdo, [
          'season_id'=>$seasonId, 'stage'=>'Semifinal', 'round_no'=>$g, 'game_no'=>$gameNo++,
          'game_date'=>$date, 'game_time'=>$times[$i % count($times)], 'field'=>$field,
          'home_label'=>$g === 2 ? $series['away'] : $series['home'],
          'away_label'=>$g === 2 ? $series['home'] : $series['away'],
          'notes'=>$series['label'] . " - Juego $g" . ($g === 3 ? " si necesario" : "")
        ]);
      }
    }

    $finalTime = $times[min(2, count($times)-1)];
    insert_schedule_entry($pdo, [
      'season_id'=>$seasonId, 'stage'=>'Final', 'round_no'=>1, 'game_no'=>$gameNo++,
      'game_date'=>schedule_date($startDate, $week++), 'game_time'=>$finalTime, 'field'=>$field,
      'home_label'=>'Ganador Semifinal A',
      'away_label'=>'Ganador Semifinal B',
      'notes'=>'Final - Juego único'
    ]);
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (post('action') === 'generate') {
    $times = array_values(array_filter(array_map('trim', explode(',', post('times', '09:30,11:30,01:30')))));
    try {
      generate_schedule($pdo, $seasonId, post('start_date') ?: next_sunday(), $times, post('field') ?: 'Campo Principal', $requiredTeamCount);
      flash('Calendario generado: 6 equipos, regular a dos vueltas, semifinales y final.');
    } catch (Throwable $e) {
      flash('No se pudo generar el calendario: ' . $e->getMessage());
    }
    header("Location: /admin/schedule.php"); exit;
  }
  if (post('action') === 'pdf') {
    $teamCountForPdf = (int)$pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
    if ($teamCountForPdf !== $requiredTeamCount) {
      flash("No se generó el PDF oficial: primero deben estar cargados los $requiredTeamCount equipos.");
      header("Location: /admin/schedule.php"); exit;
    }
    $python = '/Users/joseramirez/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/bin/python3';
    $script = __DIR__ . '/../tools/generate_schedule_pdf.py';
    $cmd = escapeshellcmd($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg((string)$seasonId);
    exec($cmd, $out, $code);
    flash($code === 0 ? 'PDF generado correctamente.' : 'No se pudo generar el PDF.');
    header("Location: /admin/schedule.php"); exit;
  }
}

$entries = $pdo->prepare("SELECT * FROM schedule_entries WHERE season_id=? ORDER BY game_date, game_time, id");
$entries->execute([$seasonId]);
$entries = $entries->fetchAll();
$teamCount = (int)$pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$regularCount = 0;
foreach ($entries as $entry) if ($entry['stage'] === 'Regular') $regularCount++;
$expectedRegularCount = $requiredTeamCount * ($requiredTeamCount - 1);
$pdfPath = "/output/pdf/lsl50_schedule_season_" . $seasonId . ".pdf";
$pdfFile = __DIR__ . "/.." . $pdfPath;
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
$pdfUrl = $scheme . '://' . $host . $pdfPath;
$shareSubject = "Calendario oficial LSL50 - " . $season['name'];
$shareMessage = "Saludos, aqui esta el calendario oficial LSL50 de " . $season['name'] . ": " . $pdfUrl;
$mailtoUrl = "mailto:?subject=" . rawurlencode($shareSubject) . "&body=" . rawurlencode($shareMessage);
$whatsappUrl = "https://wa.me/?text=" . rawurlencode($shareMessage);

include __DIR__ . "/../partials/header.php";
?>

<h1 class="text-2xl font-bold mb-4">Calendario Automático</h1>
<div class="notice">Temporada activa: <strong><?= h($season['name']) ?></strong></div>
<?php if ($teamCount !== $requiredTeamCount): ?>
  <div class="warning">Esta temporada está configurada para <strong><?= (int)$requiredTeamCount ?> equipos</strong>. Actualmente hay <strong><?= (int)$teamCount ?></strong> equipos cargados. Agrega los equipos faltantes antes de generar el calendario oficial.</div>
<?php endif; ?>
<?php if ($entries && $regularCount !== $expectedRegularCount): ?>
  <div class="warning">El calendario mostrado no corresponde al formato oficial de 6 equipos. Regular esperada: <?= (int)$expectedRegularCount ?> juegos. Regular actual: <?= (int)$regularCount ?> juegos.</div>
<?php endif; ?>
<?php flashes(); ?>

<div class="grid md:grid-cols-2 gap-4">
  <div class="card">
    <h2 class="font-semibold mb-2">Generar calendario</h2>
    <form method="post" class="space-y-2">
      <input type="hidden" name="action" value="generate">
      <div><label class="block mb-1">Fecha de inicio</label><input type="date" name="start_date" value="<?= h(next_sunday()) ?>" class="w-full"></div>
      <div><label class="block mb-1">Horarios</label><input name="times" value="09:30,11:30,13:30" class="w-full"></div>
      <div><label class="block mb-1">Campo</label><input name="field" value="Campo Principal" class="w-full"></div>
      <button class="btn-primary">Generar regular + postemporada + final</button>
    </form>
    <div class="small mt-2">Formato oficial: 6 equipos, 3 juegos por fecha, regular a dos vueltas. Semifinales: 1ro vs 4to y 2do vs 3ro. Final: juego único entre ganadores.</div>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Exportar PDF</h2>
    <form method="post" class="space-y-2">
      <input type="hidden" name="action" value="pdf">
      <button class="btn-primary">Generar PDF del calendario</button>
    </form>
    <?php if (file_exists($pdfFile)): ?>
      <div class="space-y-2" style="margin-top:12px">
        <p><a class="btn" href="<?= h($pdfPath) ?>">Abrir PDF generado</a></p>
        <p><a class="btn" href="<?= h($mailtoUrl) ?>">Enviar por correo</a></p>
        <p><a class="btn" href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener">Enviar por WhatsApp</a></p>
        <p><button type="button" class="btn" id="share-schedule" data-title="<?= h($shareSubject) ?>" data-text="<?= h($shareMessage) ?>" data-url="<?= h($pdfUrl) ?>">Compartir desde este dispositivo</button></p>
      </div>
    <?php endif; ?>
    <div class="small">El PDF queda guardado en <code>output/pdf</code>. Los botones preparan el mensaje con el enlace del calendario.</div>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h2 class="font-semibold mb-2">Calendario generado</h2>
  <table class="table">
    <thead><tr><th>Fecha</th><th>Hora</th><th>Etapa</th><th>Juego</th><th>Campo</th><th>Notas</th></tr></thead>
    <tbody>
      <?php foreach ($entries as $e): ?>
        <tr>
          <td><?= h($e['game_date']) ?></td>
          <td><?= h($e['game_time']) ?></td>
          <td><?= h($e['stage']) ?></td>
          <td><?= $e['stage'] === 'OFF' ? h($e['notes']) : h($e['home_label']) . ' vs ' . h($e['away_label']) ?></td>
          <td><?= h($e['field']) ?></td>
          <td><?= h($e['notes']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
  const shareButton = document.getElementById('share-schedule');
  if (shareButton) {
    if (!navigator.share) {
      shareButton.style.display = 'none';
    } else {
      shareButton.addEventListener('click', async () => {
        try {
          await navigator.share({
            title: shareButton.dataset.title,
            text: shareButton.dataset.text,
            url: shareButton.dataset.url
          });
        } catch (error) {
          if (error && error.name !== 'AbortError') {
            alert('No se pudo abrir la opcion de compartir en este dispositivo.');
          }
        }
      });
    }
  }
</script>

<?php include __DIR__ . "/../partials/footer.php"; ?>
