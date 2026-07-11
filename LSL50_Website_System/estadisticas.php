<?php
require __DIR__ . "/config.php";
require_once __DIR__ . "/src/autoload.php";

use Lsl50\Services\StatsEngine;

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];
$leagueGames = StatsEngine::leagueGames($pdo, $seasonId);
$departments = StatsEngine::offensiveDepartments();

$avgRows = StatsEngine::offensiveLeaders($pdo, "ps.AVG", "ps.AB > 0", "ps.AVG DESC", 10, true);
$avgFeature = $avgRows[0] ?? null;
$eraRows = StatsEngine::pitchingTable($pdo, $seasonId);
$eraFeature = $eraRows[0] ?? null;

$pageTitle = "Líderes — Liga Softball 50+";
$leagueLogoUrl = lsl_setting($pdo, "league_logo_url", "");

include __DIR__ . "/partials/public/head.php";
include __DIR__ . "/partials/public/header.php";
?>
<main class="lsl-main mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
  <div class="lsl-wrap">
    <h1 class="lsl-page-title text-2xl font-extrabold tracking-tight text-white sm:text-3xl">Líderes de la Temporada</h1>
    <p class="lsl-page-sub mt-1 text-sm text-lsl-muted"><?= h($season["name"]) ?> · <?= (int)$leagueGames ?> juegos anotados</p>

    <div class="lsl-rule-note mt-4 rounded-lg border border-lsl-border bg-lsl-card px-4 py-3 text-xs leading-relaxed text-lsl-muted sm:text-sm">
      Regla 3.1: PA mínimas = Juegos del equipo × 3.1 · AVG, OBP, SLG y OPS requieren calificación MLB.
      Efectividad (ERA): IP mínimas = Juegos programados × 0.8 cuando hay datos de pitcheo registrados.
    </div>

    <?php if ($avgFeature): ?>
    <div class="lsl-feature-hero mt-6 flex gap-4 rounded-xl border border-lsl-border bg-lsl-card p-5">
      <div class="lsl-avatar flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-lsl-bg text-lg font-bold text-lsl-accent ring-1 ring-lsl-border"><?= h(lsl_public_leader_initial($avgFeature["player_name"])) ?></div>
      <div>
        <div class="lsl-feature-kicker text-xs font-bold uppercase tracking-widest text-lsl-accent">Champion Bate (AVG)</div>
        <div class="lsl-feature-name text-xl font-bold text-white"><?= h($avgFeature["player_name"]) ?></div>
        <div class="lsl-rank-team text-sm text-lsl-muted"><?= h($avgFeature["team_name"] ?: "-") ?></div>
        <div class="lsl-feature-stats mt-3 flex flex-wrap gap-4 text-sm">
          <div><b class="text-lsl-muted">AB</b> <span class="font-semibold"><?= (int)$avgFeature["AB"] ?></span></div>
          <div><b class="text-lsl-muted">H</b> <span class="font-semibold"><?= (int)$avgFeature["H"] ?></span></div>
          <div><b class="text-lsl-muted">PA</b> <span class="font-semibold"><?= (int)$avgFeature["PA"] ?></span></div>
          <div><b class="text-lsl-muted">AVG</b> <span class="font-bold text-lsl-accent"><?= h(StatsEngine::fmtLeaderValue($avgFeature["leader_value"], "avg")) ?></span></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($eraFeature && ((float)($eraFeature["ERA"] ?? 0) > 0 || (int)($eraFeature["W"] ?? 0) > 0)): ?>
    <div class="lsl-feature-hero mt-4 flex gap-4 rounded-xl border border-lsl-border bg-lsl-card p-5">
      <div class="lsl-avatar flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-lsl-bg text-lg font-bold text-lsl-blue ring-1 ring-lsl-border"><?= h(lsl_public_leader_initial($eraFeature["player_name"])) ?></div>
      <div>
        <div class="lsl-feature-kicker text-xs font-bold uppercase tracking-widest text-lsl-blue">Líder Efectividad (ERA)</div>
        <div class="lsl-feature-name text-xl font-bold text-white"><?= h($eraFeature["player_name"]) ?></div>
        <div class="lsl-rank-team text-sm text-lsl-muted"><?= h($eraFeature["team_name"] ?: "-") ?></div>
        <div class="lsl-feature-stats mt-3 flex flex-wrap gap-4 text-sm">
          <div><b class="text-lsl-muted">IP</b> <span class="font-semibold"><?= h(number_format((float)($eraFeature["IP"] ?? 0), 1)) ?></span></div>
          <div><b class="text-lsl-muted">W-L</b> <span class="font-semibold"><?= (int)($eraFeature["W"] ?? 0) ?>-<?= (int)($eraFeature["L"] ?? 0) ?></span></div>
          <div><b class="text-lsl-muted">SO</b> <span class="font-semibold"><?= (int)($eraFeature["SO"] ?? 0) ?></span></div>
          <div><b class="text-lsl-muted">ERA</b> <span class="font-bold text-lsl-blue"><?= (float)($eraFeature["ERA"] ?? 0) > 0 ? number_format((float)$eraFeature["ERA"], 2) : "—" ?></span></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="lsl-dept-grid mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <?php foreach ($departments as $dept):
        if (!empty($dept["featured"])) continue;
        $rows = StatsEngine::offensiveLeaders($pdo, $dept["expr"], $dept["where"], $dept["order"], 10, !empty($dept["qualified"]));
      ?>
        <article class="lsl-card lsl-dept-card overflow-hidden rounded-xl border border-lsl-border bg-lsl-card">
          <div class="lsl-dept-head border-b border-lsl-border px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-white"><?= h($dept["title"]) ?> (<?= h($dept["abbr"]) ?>)</div>
          <div class="p-2">
          <?php foreach ($rows as $i => $row): ?>
            <div class="lsl-rank-row flex items-center gap-2 rounded-lg px-2 py-2 transition hover:bg-lsl-bg/50" data-search="<?= h(mb_strtolower($row["player_name"] . " " . ($row["team_name"] ?: ""))) ?>">
              <div class="lsl-rank-num w-6 text-center text-xs font-bold text-lsl-muted"><?= $i + 1 ?></div>
              <div class="lsl-avatar flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-lsl-bg text-xs font-bold ring-1 ring-lsl-border"><?= h(lsl_public_leader_initial($row["player_name"])) ?></div>
              <div class="min-w-0 flex-1">
                <div class="lsl-rank-name truncate text-sm font-semibold text-white"><?= h($row["player_name"]) ?></div>
                <div class="lsl-rank-team truncate text-xs text-lsl-muted"><?= h($row["team_name"] ?: "-") ?></div>
              </div>
              <div class="lsl-rank-val font-mono text-sm font-bold text-lsl-accent"><?= h(StatsEngine::fmtLeaderValue($row["leader_value"], $dept["type"])) ?></div>
            </div>
          <?php endforeach; ?>
          <?php if (!$rows): ?><div class="lsl-empty px-3 py-4 text-sm text-lsl-muted">Sin datos todavía.</div><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . "/partials/public/footer.php"; ?>
