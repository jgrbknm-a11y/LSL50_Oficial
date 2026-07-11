<?php
declare(strict_types=1);

require __DIR__ . "/../config.php";
require_once __DIR__ . "/../src/autoload.php";
require_admin();

use Lsl50\Services\StatsEngine;

$loadError = null;
$standings = [];
$leagueGames = 0;
$departments = [];
$featuredDepartments = [];
$pitcherLeaders = [];
$allBatters = [];
$teamGameCounts = [];
$season = ["id" => 0, "name" => ""];
$seasonId = 0;

try {
  $pdo = db();
  $season = active_season($pdo);
  $seasonId = (int)$season["id"];
  if ($seasonId <= 0) {
    throw new RuntimeException("Temporada activa inválida.");
  }

  StatsEngine::syncPitching($pdo, $seasonId);

  $standings = StatsEngine::standings($pdo, $seasonId, false);
  $leagueGames = StatsEngine::leagueGames($pdo, $seasonId);
  $departments = StatsEngine::offensiveDepartments("admin");
  $featuredDepartments = StatsEngine::featuredOffensiveDepartments();
  $pitcherLeaders = StatsEngine::pitcherWinLeaders($pdo, $seasonId, 10);
  $allBatters = StatsEngine::battingTable($pdo, $seasonId);
  $teamGameCounts = StatsEngine::teamMinPlateAppearances($pdo, $seasonId);
} catch (Throwable $e) {
  $loadError = $e->getMessage();
}

include __DIR__ . "/../partials/header.php";
?>

<style>
  .leaders-hero{display:grid;grid-template-columns:1.3fr .7fr;gap:18px;align-items:center;background:#fff;border:1px solid var(--line);border-radius:8px;padding:20px;margin-bottom:18px}
  .leaders-kicker{color:#2563eb;font-weight:900;text-transform:uppercase;letter-spacing:.04em;font-size:12px}.leaders-title{font-size:38px;line-height:1.05;margin:6px 0 10px}.leaders-sub{font-size:18px;color:var(--muted);margin:0}
  .rule-pills{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:8px 12px;font-weight:900;background:#fff3c4;color:#92400e}.pill.dark{background:#e8edf3;color:#142033}
  .search-wrap{background:#081420;padding:18px;border-radius:8px;margin-bottom:18px}.leader-search{width:100%;font-size:18px;border-radius:8px;padding:14px 16px}
  .leader-table-shell{overflow:auto;border:1px solid var(--line);border-radius:8px;background:#fff}.leader-table{min-width:1080px}.leader-table th{background:#111c31;color:#fff;border-bottom:0;text-align:center;white-space:nowrap}.leader-table td{text-align:center;white-space:nowrap}.leader-table td.player-cell{text-align:left;font-weight:900;position:sticky;left:0;background:#fff;z-index:1;min-width:220px}.leader-table th.player-head{position:sticky;left:0;z-index:2;text-align:left}
  .team-muted{display:block;color:var(--muted);font-size:12px;font-weight:800;margin-top:2px;text-transform:uppercase}.num-hot{color:#ea580c;font-weight:950}.num-blue{color:#2563eb;font-weight:950}.num-purple{color:#7c3aed;font-weight:950}.qualified{color:#067647;font-weight:900}.not-qualified{color:#ea580c;font-weight:900}
  .dept-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.dept-card{padding:0;overflow:hidden}.dept-head{padding:14px 16px;color:#fff;font-weight:950;font-size:21px;display:flex;align-items:center;justify-content:space-between}.dept-head.avg{background:#d89200}.dept-head.h{background:#1d4ed8}.dept-head.two{background:#4f46e5}.dept-head.three{background:#7c3aed}.dept-head.hr{background:#ea580c}.dept-head.rbi{background:#16803c}.dept-head.r{background:#0f766e}.dept-head.bb{background:#475569}.dept-head.so{background:#991b1b}.dept-head.sb{background:#0369a1}.dept-head.rate{background:#4b2a1e}.dept-head.tb{background:#1f2937}
  .rank-row{display:grid;grid-template-columns:42px 56px minmax(0,1fr) auto;gap:10px;align-items:center;padding:13px 16px;border-bottom:1px solid var(--line)}.rank-row:last-child{border-bottom:0}.rank{font-size:22px;color:#98a2b3;font-weight:950}.avatar{width:46px;height:46px;border-radius:50%;background:#e5e7eb;color:#98a2b3;display:grid;place-items:center;font-weight:950}.rank-name{font-weight:950;font-size:18px}.rank-team{color:var(--muted);font-size:13px;font-weight:900;text-transform:uppercase}.rank-value{font-size:28px;font-weight:950}.pa-chip{display:inline-block;margin-top:5px;border-radius:6px;background:#f2f4f7;color:#667085;padding:3px 7px;font-size:12px;font-weight:800}
  .leader-card-feature{background:#07111f;color:#fff;border-color:#26364f;padding:20px}.feature-inner{display:grid;grid-template-columns:82px minmax(0,1fr);gap:16px;align-items:center}.feature-initial{width:74px;height:74px;border-radius:50%;display:grid;place-items:center;background:#fff3c4;color:#b45309;font-size:34px;font-weight:950}.feature-name{font-size:28px;font-weight:950}.feature-meta{color:#c8d3e1;font-weight:900;text-transform:uppercase}.feature-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:16px;text-align:center}.feature-stats b{display:block;color:#d7a72f;font-size:12px}.feature-stats span{font-size:26px;font-weight:950}
  @media (max-width:760px){.leaders-hero{grid-template-columns:1fr}.rule-pills{justify-content:flex-start}.leaders-title{font-size:32px}.dept-grid{grid-template-columns:1fr}.feature-stats{grid-template-columns:repeat(2,1fr)}}
</style>

<?php if ($loadError): ?>
  <div class="warning">No fue posible cargar los líderes: <?= h($loadError) ?></div>
<?php else: ?>

<div class="leaders-hero">
  <div>
    <div class="leaders-kicker">Temporada <?= h((string)$season["name"]) ?></div>
    <h1 class="leaders-title">Líderes LSL50</h1>
    <p class="leaders-sub">Bateo general, rankings por departamento y pitchers ganadores usando las estadísticas oficiales del cuaderno.</p>
  </div>
  <div class="rule-pills">
    <span class="pill">Regla 3.1 PA</span>
    <span class="pill dark"><?= (int)$leagueGames ?> juego<?= $leagueGames === 1 ? "" : "s" ?> anotado<?= $leagueGames === 1 ? "" : "s" ?></span>
  </div>
</div>

<div class="warning">AVG, OBP, SLG y OPS usan regla MLB: mínimo de <strong>3.1 apariciones al plato por juego del equipo</strong>. PA se calcula con <strong>AB + BB + HBP + SH + SF</strong>.</div>

<div class="search-wrap">
  <input id="leaderSearch" class="leader-search" type="search" placeholder="Buscar jugador o equipo...">
</div>

<div class="grid md:grid-cols-2 gap-4 mb-4">
  <div class="card">
    <h2 class="font-semibold mb-2">Resumen de posiciones</h2>
    <table class="table">
      <thead><tr><th>Equipo</th><th>Récord</th><th>RF</th><th>RA</th><th>DIF</th></tr></thead>
      <tbody>
        <?php foreach ($standings as $row): ?>
          <tr>
            <td><?= h((string)$row["name"]) ?></td>
            <td><strong><?= h((string)($row["record"] ?? "")) ?></strong></td>
            <td><?= (int)($row["runs_for"] ?? 0) ?></td>
            <td><?= (int)($row["runs_against"] ?? 0) ?></td>
            <td><?= (int)($row["run_diff"] ?? 0) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Líderes principales</h2>
    <table class="table">
      <thead><tr><th>Departamento</th><th>Jugador</th><th>Valor</th></tr></thead>
      <tbody>
        <?php foreach ($featuredDepartments as $dept): ?>
          <?php
            $rows = StatsEngine::offensiveLeaders(
              $pdo,
              (string)$dept["expr"],
              (string)$dept["where"],
              (string)$dept["order"],
              1,
              !empty($dept["qualified"])
            );
            $leader = $rows[0] ?? null;
            $deptType = (string)($dept["type"] ?? "int");
          ?>
          <tr>
            <td><?= h((string)$dept["title"]) ?></td>
            <td><?= $leader ? h(($leader["number"] ? "#" . $leader["number"] . " " : "") . $leader["player_name"] . " - " . ($leader["team_name"] ?: "-")) : '<span class="small">Sin datos</span>' ?></td>
            <td><strong><?= $leader ? h(StatsEngine::fmtLeaderValue($leader["leader_value"], $deptType)) : "-" ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="small mt-2">Los líderes se actualizan automáticamente. AVG/OBP/SLG/OPS usan regla MLB: mínimo de 3.1 apariciones al plato por juego del equipo.</div>
  </div>
</div>

<?php
  $avgLeaderRows = StatsEngine::offensiveLeaders($pdo, "ps.AVG", "ps.AB > 0", "ps.AVG DESC", 1, true);
  $avgFeature = $avgLeaderRows[0] ?? null;
?>
<div class="card leader-card-feature mb-4">
  <?php if ($avgFeature): ?>
    <div class="feature-inner">
      <div class="feature-initial"><?= h(lsl_public_leader_initial((string)$avgFeature["player_name"])) ?></div>
      <div>
        <div class="small" style="color:#d7a72f;font-weight:900">Champion Bate (AVG)</div>
        <div class="feature-name"><?= h((string)$avgFeature["player_name"]) ?></div>
        <div class="feature-meta"><?= h((string)($avgFeature["team_name"] ?: "-")) ?></div>
      </div>
    </div>
    <div class="feature-stats">
      <div><b>AB</b><span><?= (int)$avgFeature["AB"] ?></span></div>
      <div><b>H</b><span><?= (int)$avgFeature["H"] ?></span></div>
      <div><b>PA</b><span><?= (int)$avgFeature["PA"] ?></span></div>
      <div><b>AVG</b><span><?= h(lsl_public_fmt_avg((float)$avgFeature["leader_value"])) ?></span></div>
    </div>
  <?php else: ?>
    <div class="feature-inner">
      <div class="feature-initial">-</div>
      <div>
        <div class="small" style="color:#d7a72f;font-weight:900">Champion Bate (AVG)</div>
        <div class="feature-name">Sin jugador clasificado todavía</div>
        <div class="feature-meta">La regla 3.1 requiere más PA antes de publicar campeón de promedio.</div>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="card mb-4">
  <h2 class="font-semibold mb-2">Bateo General</h2>
  <div class="small mb-2">Tabla completa estilo liga. Se puede desplazar horizontalmente en teléfono.</div>
  <div class="leader-table-shell">
    <table class="table leader-table" id="battingTable">
      <thead>
        <tr>
          <th class="player-head">Jugador</th>
          <th>G</th><th>AB</th><th>R</th><th>H</th><th>2B</th><th>3B</th><th>HR</th><th>TB</th><th>BB</th><th>HBP</th><th>SH</th><th>SF</th><th>SO</th><th>SB</th><th>RBI</th><th>E</th><th>PA</th><th>OBP</th><th>SLG</th><th>OPS</th><th>BA</th><th>Regla</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($allBatters as $row): ?>
          <tr data-search="<?= h(mb_strtolower($row["player_name"] . " " . ($row["team_name"] ?: ""))) ?>">
            <td class="player-cell"><?= h((string)$row["player_name"]) ?><span class="team-muted"><?= h((string)($row["team_name"] ?: "-")) ?></span></td>
            <td><?= (int)$row["games_played"] ?></td>
            <td><?= (int)$row["AB"] ?></td>
            <td><?= (int)$row["R"] ?></td>
            <td class="num-hot"><?= (int)$row["H"] ?></td>
            <td><?= (int)$row["dbl"] ?></td>
            <td><?= (int)$row["tpl"] ?></td>
            <td class="num-hot"><?= (int)$row["HR"] ?></td>
            <td><?= (int)$row["TB"] ?></td>
            <td><?= (int)$row["BB"] ?></td>
            <td><?= (int)$row["HBP"] ?></td>
            <td><?= (int)$row["SH"] ?></td>
            <td><?= (int)$row["SF"] ?></td>
            <td><?= (int)$row["SO"] ?></td>
            <td><?= (int)$row["SB"] ?></td>
            <td><strong><?= (int)$row["RBI"] ?></strong></td>
            <td><?= (int)$row["E"] ?></td>
            <td><?= (int)$row["PA"] ?></td>
            <td class="num-blue"><?= h(lsl_public_fmt_avg((float)$row["OBP"])) ?></td>
            <td class="num-purple"><?= h(lsl_public_fmt_avg((float)$row["SLG"])) ?></td>
            <td class="num-purple"><?= h(lsl_public_fmt_avg((float)$row["OPS"])) ?></td>
            <td><strong><?= h(lsl_public_fmt_avg((float)$row["AVG"])) ?></strong></td>
            <td class="<?= !empty($row["qualified"]) ? "qualified" : "not-qualified" ?>"><?= h((string)($row["qual_label"] ?? "N/A")) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$allBatters): ?>
          <tr><td colspan="23" class="small">Todavía no hay estadísticas de bateo registradas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-4">
  <h2 class="font-semibold mb-2">Pitchers con más ganados</h2>
  <table class="table">
    <thead><tr><th>#</th><th>Pitcher</th><th>Equipo</th><th>G</th></tr></thead>
    <tbody>
      <?php foreach ($pitcherLeaders as $row): ?>
        <tr>
          <td><?= h((string)($row["number"] ?: "-")) ?></td>
          <td><?= h((string)$row["player_name"]) ?></td>
          <td><?= h((string)($row["team_name"] ?: "-")) ?></td>
          <td><strong><?= (int)$row["wins"] ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pitcherLeaders): ?>
        <tr><td colspan="4" class="small">Todavía no hay pitchers ganadores registrados en los juegos.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card mb-4">
  <h2 class="font-semibold mb-2">PA mínimas por equipo</h2>
  <div class="grid md:grid-cols-2 gap-2">
    <?php foreach ($teamGameCounts as $row): ?>
      <div class="pill dark" style="justify-content:space-between;border-radius:8px">
        <span><?= h((string)$row["name"]) ?></span>
        <span><?= (int)$row["min_pa"] ?> PA / <?= (int)$row["scored_games"] ?> J</span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="dept-grid">
  <?php foreach ($departments as $dept): ?>
    <?php
      $rows = StatsEngine::offensiveLeaders(
        $pdo,
        (string)$dept["expr"],
        (string)$dept["where"],
        (string)$dept["order"],
        10,
        !empty($dept["qualified"])
      );
      $deptType = (string)($dept["type"] ?? "int");
      $headClass = match ((string)$dept["abbr"]) {
        "AVG" => "avg",
        "H" => "h",
        "2B" => "two",
        "3B" => "three",
        "HR" => "hr",
        "RBI" => "rbi",
        "R" => "r",
        "BB", "HBP", "SH", "SF" => "bb",
        "SO" => "so",
        "SB" => "sb",
        "OBP", "SLG", "OPS" => "rate",
        "TB" => "tb",
        default => "tb",
      };
    ?>
    <div class="card dept-card">
      <div class="dept-head <?= h($headClass) ?>">
        <span><?= h((string)$dept["title"]) ?> (<?= h((string)$dept["abbr"]) ?>)</span>
        <?php if (!empty($dept["qualified"])): ?><small>3.1 PA</small><?php endif; ?>
      </div>
      <div>
        <?php foreach ($rows as $idx => $row): ?>
          <div class="rank-row" data-search="<?= h(mb_strtolower($row["player_name"] . " " . ($row["team_name"] ?: ""))) ?>">
            <div class="rank"><?= $idx + 1 ?></div>
            <div class="avatar"><?= h(lsl_public_leader_initial((string)$row["player_name"])) ?></div>
            <div>
              <div class="rank-name"><?= h((string)$row["player_name"]) ?></div>
              <div class="rank-team"><?= h((string)($row["team_name"] ?: "-")) ?></div>
              <?php if (!empty($dept["qualified"])): ?>
                <span class="pa-chip">PA: <?= (int)$row["PA"] ?></span>
              <?php endif; ?>
            </div>
            <div class="rank-value"><?= h($deptType === "avg" ? lsl_public_fmt_avg((float)$row["leader_value"]) : StatsEngine::fmtLeaderValue($row["leader_value"], $deptType)) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <div class="rank-row">
            <div class="rank">-</div>
            <div class="avatar">-</div>
            <div class="small"><?= !empty($dept["qualified"]) ? "Sin jugadores clasificados por regla MLB todavía." : "Sin datos todavía." ?></div>
            <div></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<script>
  const leaderSearch = document.getElementById("leaderSearch");
  if (leaderSearch) {
    leaderSearch.addEventListener("input", () => {
      const q = leaderSearch.value.trim().toLowerCase();
      document.querySelectorAll("[data-search]").forEach((row) => {
        row.style.display = row.dataset.search.includes(q) ? "" : "none";
      });
    });
  }
</script>

<?php endif; ?>

<?php include __DIR__ . "/../partials/footer.php"; ?>
