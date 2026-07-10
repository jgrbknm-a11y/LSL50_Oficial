<?php
require __DIR__ . "/../config.php";
require_admin();

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];

function fmt_leader_value($value, string $type): string {
  if ($type === "avg") return number_format((float)$value, 3);
  return (string)(int)$value;
}

function fmt_rate($value): string {
  $formatted = number_format((float)$value, 3);
  return str_starts_with($formatted, "0") ? substr($formatted, 1) : $formatted;
}

function offensive_leaders(PDO $pdo, string $expression, string $where, string $order, int $limit = 10, bool $qualifiedRate = false): array {
  $qualification = "";
  if ($qualifiedRate) {
    $qualification = " AND (ps.AB + ps.BB + ps.HBP + ps.SH + ps.SF) >= (
      SELECT CAST((COUNT(*) * 3.1) + 0.999999 AS INTEGER)
      FROM games g
      WHERE (g.home_team_id=t.id OR g.away_team_id=t.id)
        AND EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id=g.id)
    )";
  }
  $sql = "SELECT p.id, p.number, p.first_name || ' ' || p.last_name player_name, t.name team_name,
      ps.games_played, ps.AB, ps.H, ps.dbl, ps.tpl, ps.HR, ps.RBI, ps.BB, ps.HBP, ps.SH, ps.SF, ps.E, (ps.AB + ps.BB + ps.HBP + ps.SH + ps.SF) PA, $expression AS leader_value
    FROM player_stats ps
    JOIN players p ON p.id=ps.player_id
    LEFT JOIN teams t ON t.id=p.team_id
    WHERE $where $qualification
    ORDER BY $order, ps.AB DESC, p.last_name, p.first_name
    LIMIT $limit";
  return $pdo->query($sql)->fetchAll();
}

function leader_initial(string $name): string {
  $name = trim($name);
  return $name !== "" ? mb_strtoupper(mb_substr($name, 0, 1)) : "-";
}

$standings = $pdo->query("SELECT t.name, COALESCE(ts.wins,0) wins, COALESCE(ts.losses,0) losses, COALESCE(ts.ties,0) ties,
    COALESCE(ts.runs_for,0) runs_for, COALESCE(ts.runs_against,0) runs_against,
    (COALESCE(ts.runs_for,0) - COALESCE(ts.runs_against,0)) run_diff
  FROM teams t
  LEFT JOIN team_stats ts ON ts.team_id=t.id
  ORDER BY COALESCE(ts.wins,0) DESC, COALESCE(ts.ties,0) DESC, run_diff DESC, COALESCE(ts.runs_for,0) DESC, t.name")->fetchAll();

$departments = [
  ["title" => "Promedio", "abbr" => "AVG", "expr" => "ps.AVG", "where" => "ps.AB > 0", "order" => "ps.AVG DESC", "type" => "avg", "qualified" => true],
  ["title" => "Hits", "abbr" => "H", "expr" => "ps.H", "where" => "ps.H > 0", "order" => "ps.H DESC", "type" => "int"],
  ["title" => "Dobles", "abbr" => "2B", "expr" => "ps.dbl", "where" => "ps.dbl > 0", "order" => "ps.dbl DESC", "type" => "int"],
  ["title" => "Triples", "abbr" => "3B", "expr" => "ps.tpl", "where" => "ps.tpl > 0", "order" => "ps.tpl DESC", "type" => "int"],
  ["title" => "Jonrones", "abbr" => "HR", "expr" => "ps.HR", "where" => "ps.HR > 0", "order" => "ps.HR DESC", "type" => "int"],
  ["title" => "Impulsadas", "abbr" => "RBI", "expr" => "ps.RBI", "where" => "ps.RBI > 0", "order" => "ps.RBI DESC", "type" => "int"],
  ["title" => "Anotadas", "abbr" => "R", "expr" => "ps.R", "where" => "ps.R > 0", "order" => "ps.R DESC", "type" => "int"],
  ["title" => "Bases por bolas", "abbr" => "BB", "expr" => "ps.BB", "where" => "ps.BB > 0", "order" => "ps.BB DESC", "type" => "int"],
  ["title" => "Golpeados", "abbr" => "HBP", "expr" => "ps.HBP", "where" => "ps.HBP > 0", "order" => "ps.HBP DESC", "type" => "int"],
  ["title" => "Toques sacrificio", "abbr" => "SH", "expr" => "ps.SH", "where" => "ps.SH > 0", "order" => "ps.SH DESC", "type" => "int"],
  ["title" => "Elevados sacrificio", "abbr" => "SF", "expr" => "ps.SF", "where" => "ps.SF > 0", "order" => "ps.SF DESC", "type" => "int"],
  ["title" => "Ponches recibidos", "abbr" => "SO", "expr" => "ps.SO", "where" => "ps.SO > 0", "order" => "ps.SO DESC", "type" => "int"],
  ["title" => "Bases robadas", "abbr" => "SB", "expr" => "ps.SB", "where" => "ps.SB > 0", "order" => "ps.SB DESC", "type" => "int"],
  ["title" => "Errores defensivos", "abbr" => "E", "expr" => "ps.E", "where" => "ps.E > 0", "order" => "ps.E DESC", "type" => "int"],
  ["title" => "Embazado", "abbr" => "OBP", "expr" => "ps.OBP", "where" => "ps.AB + ps.BB + ps.HBP + ps.SF > 0", "order" => "ps.OBP DESC", "type" => "avg", "qualified" => true],
  ["title" => "Slugging", "abbr" => "SLG", "expr" => "ps.SLG", "where" => "ps.AB > 0", "order" => "ps.SLG DESC", "type" => "avg", "qualified" => true],
  ["title" => "OPS", "abbr" => "OPS", "expr" => "(ps.OBP + ps.SLG)", "where" => "ps.AB > 0", "order" => "(ps.OBP + ps.SLG) DESC", "type" => "avg", "qualified" => true],
  ["title" => "Bases totales", "abbr" => "TB", "expr" => "ps.TB", "where" => "ps.TB > 0", "order" => "ps.TB DESC", "type" => "int"],
  ["title" => "Turnos", "abbr" => "AB", "expr" => "ps.AB", "where" => "ps.AB > 0", "order" => "ps.AB DESC", "type" => "int"],
  ["title" => "Juegos legales", "abbr" => "GP", "expr" => "ps.games_played", "where" => "ps.games_played > 0", "order" => "ps.games_played DESC", "type" => "int"],
];

$featuredDepartments = [
  ["title" => "Mejor promedio", "abbr" => "AVG", "expr" => "ps.AVG", "where" => "ps.AB > 0", "order" => "ps.AVG DESC", "type" => "avg", "qualified" => true],
  ["title" => "Más hits", "abbr" => "H", "expr" => "ps.H", "where" => "ps.H > 0", "order" => "ps.H DESC", "type" => "int"],
  ["title" => "Más impulsadas", "abbr" => "RBI", "expr" => "ps.RBI", "where" => "ps.RBI > 0", "order" => "ps.RBI DESC", "type" => "int"],
  ["title" => "Más anotadas", "abbr" => "R", "expr" => "ps.R", "where" => "ps.R > 0", "order" => "ps.R DESC", "type" => "int"],
];

$pitcherLeaders = $pdo->query("SELECT p.id, p.number, p.first_name || ' ' || p.last_name player_name, t.name team_name, COUNT(*) wins
  FROM games g
  JOIN players p ON p.id=g.winning_pitcher_id
  LEFT JOIN teams t ON t.id=p.team_id
  WHERE COALESCE(g.season_id, $seasonId) = $seasonId
    AND g.winning_pitcher_id IS NOT NULL
    AND g.final_home != g.final_away
  GROUP BY p.id, p.number, p.first_name, p.last_name, t.name
  ORDER BY wins DESC, p.last_name, p.first_name
  LIMIT 10")->fetchAll();

$allBatters = $pdo->query("SELECT p.id, p.number, p.first_name || ' ' || p.last_name player_name, t.name team_name,
    ps.games_played, ps.AB, ps.R, ps.H, ps.dbl, ps.tpl, ps.HR, ps.TB, ps.RBI, ps.BB, ps.SO, ps.SB, ps.HBP, ps.SH, ps.SF, ps.E,
    ps.AVG, ps.OBP, ps.SLG, (ps.OBP + ps.SLG) OPS, (ps.AB + ps.BB + ps.HBP + ps.SH + ps.SF) PA,
    (
      SELECT CAST((COUNT(*) * 3.1) + 0.999999 AS INTEGER)
      FROM games g
      WHERE (g.home_team_id=t.id OR g.away_team_id=t.id)
        AND EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id=g.id)
    ) min_pa
  FROM player_stats ps
  JOIN players p ON p.id=ps.player_id
  LEFT JOIN teams t ON t.id=p.team_id
  WHERE ps.AB > 0 OR ps.BB > 0 OR ps.HBP > 0 OR ps.SH > 0 OR ps.SF > 0 OR ps.R > 0 OR ps.H > 0 OR ps.RBI > 0
  ORDER BY ps.H DESC, ps.RBI DESC, ps.AVG DESC, p.last_name, p.first_name")->fetchAll();

$leagueGames = (int)$pdo->query("SELECT COUNT(DISTINCT id) FROM games g
  WHERE COALESCE(g.season_id, $seasonId) = $seasonId
    AND EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id=g.id)")->fetchColumn();
$teamGameCounts = $pdo->query("SELECT t.name, COUNT(DISTINCT g.id) scored_games,
    CAST((COUNT(DISTINCT g.id) * 3.1) + 0.999999 AS INTEGER) min_pa
  FROM teams t
  LEFT JOIN games g ON (g.home_team_id=t.id OR g.away_team_id=t.id)
    AND COALESCE(g.season_id, $seasonId) = $seasonId
    AND EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id=g.id)
  GROUP BY t.id, t.name
  ORDER BY t.name")->fetchAll();

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

<div class="leaders-hero">
  <div>
    <div class="leaders-kicker">Temporada <?= h($season["name"]) ?></div>
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
            <td><?= h($row["name"]) ?></td>
            <td><strong><?= (int)$row["wins"] ?>-<?= (int)$row["losses"] ?><?= (int)$row["ties"] ? "-" . (int)$row["ties"] : "" ?></strong></td>
            <td><?= (int)$row["runs_for"] ?></td>
            <td><?= (int)$row["runs_against"] ?></td>
            <td><?= (int)$row["run_diff"] ?></td>
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
          <?php $rows = offensive_leaders($pdo, $dept["expr"], $dept["where"], $dept["order"], 1, !empty($dept["qualified"])); $leader = $rows[0] ?? null; ?>
          <tr>
            <td><?= h($dept["title"]) ?></td>
            <td><?= $leader ? h(($leader["number"] ? "#" . $leader["number"] . " " : "") . $leader["player_name"] . " - " . ($leader["team_name"] ?: "-")) : '<span class="small">Sin datos</span>' ?></td>
            <td><strong><?= $leader ? h(fmt_leader_value($leader["leader_value"], $dept["type"])) : "-" ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="small mt-2">Los líderes se actualizan automáticamente. AVG/OBP/SLG/OPS usan regla MLB: mínimo de 3.1 apariciones al plato por juego del equipo.</div>
  </div>
</div>

<?php
  $avgLeaderRows = offensive_leaders($pdo, "ps.AVG", "ps.AB > 0", "ps.AVG DESC", 1, true);
  $avgFeature = $avgLeaderRows[0] ?? null;
?>
<div class="card leader-card-feature mb-4">
  <?php if ($avgFeature): ?>
    <div class="feature-inner">
      <div class="feature-initial"><?= h(leader_initial($avgFeature["player_name"])) ?></div>
      <div>
        <div class="small" style="color:#d7a72f;font-weight:900">Champion Bate (AVG)</div>
        <div class="feature-name"><?= h($avgFeature["player_name"]) ?></div>
        <div class="feature-meta"><?= h($avgFeature["team_name"] ?: "-") ?></div>
      </div>
    </div>
    <div class="feature-stats">
      <div><b>AB</b><span><?= (int)$avgFeature["AB"] ?></span></div>
      <div><b>H</b><span><?= (int)$avgFeature["H"] ?></span></div>
      <div><b>PA</b><span><?= (int)$avgFeature["PA"] ?></span></div>
      <div><b>AVG</b><span><?= h(fmt_rate($avgFeature["leader_value"])) ?></span></div>
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
          <?php $isQualified = (int)$row["PA"] >= (int)$row["min_pa"]; ?>
          <tr data-search="<?= h(mb_strtolower($row["player_name"] . " " . ($row["team_name"] ?: ""))) ?>">
            <td class="player-cell"><?= h($row["player_name"]) ?><span class="team-muted"><?= h($row["team_name"] ?: "-") ?></span></td>
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
            <td class="num-blue"><?= h(fmt_rate($row["OBP"])) ?></td>
            <td class="num-purple"><?= h(fmt_rate($row["SLG"])) ?></td>
            <td class="num-purple"><?= h(fmt_rate($row["OPS"])) ?></td>
            <td><strong><?= h(fmt_rate($row["AVG"])) ?></strong></td>
            <td class="<?= $isQualified ? "qualified" : "not-qualified" ?>"><?= $isQualified ? "OK" : (int)$row["PA"] . "/" . (int)$row["min_pa"] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$allBatters): ?>
          <tr><td colspan="19" class="small">Todavía no hay estadísticas de bateo registradas.</td></tr>
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
          <td><?= h($row["number"] ?: "-") ?></td>
          <td><?= h($row["player_name"]) ?></td>
          <td><?= h($row["team_name"] ?: "-") ?></td>
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
        <span><?= h($row["name"]) ?></span>
        <span><?= (int)$row["min_pa"] ?> PA / <?= (int)$row["scored_games"] ?> J</span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="dept-grid">
  <?php foreach ($departments as $dept): ?>
    <?php $rows = offensive_leaders($pdo, $dept["expr"], $dept["where"], $dept["order"], 10, !empty($dept["qualified"])); ?>
    <?php
      $headClass = match ($dept["abbr"]) {
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
        <span><?= h($dept["title"]) ?> (<?= h($dept["abbr"]) ?>)</span>
        <?php if (!empty($dept["qualified"])): ?><small>3.1 PA</small><?php endif; ?>
      </div>
      <div>
        <?php foreach ($rows as $idx => $row): ?>
          <div class="rank-row" data-search="<?= h(mb_strtolower($row["player_name"] . " " . ($row["team_name"] ?: ""))) ?>">
            <div class="rank"><?= $idx + 1 ?></div>
            <div class="avatar"><?= h(leader_initial($row["player_name"])) ?></div>
            <div>
              <div class="rank-name"><?= h($row["player_name"]) ?></div>
              <div class="rank-team"><?= h($row["team_name"] ?: "-") ?></div>
              <?php if (!empty($dept["qualified"])): ?>
                <span class="pa-chip">PA: <?= (int)$row["PA"] ?></span>
              <?php endif; ?>
            </div>
            <div class="rank-value"><?= h($dept["type"] === "avg" ? fmt_rate($row["leader_value"]) : fmt_leader_value($row["leader_value"], $dept["type"])) ?></div>
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

<?php include __DIR__ . "/../partials/footer.php"; ?>
