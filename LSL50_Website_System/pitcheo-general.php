<?php
require __DIR__ . "/config.php";
require_once __DIR__ . "/src/autoload.php";

use Lsl50\Services\StatsEngine;

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];
$rows = StatsEngine::pitchingTable($pdo, $seasonId);

$pageTitle = "Pitcheo General — Liga Softball 50+";
$leagueLogoUrl = lsl_setting($pdo, "league_logo_url", "");

include __DIR__ . "/partials/public/head.php";
include __DIR__ . "/partials/public/header.php";
?>
<main class="lsl-main mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
  <div class="lsl-wrap">
    <h1 class="lsl-page-title text-2xl font-extrabold tracking-tight text-white sm:text-3xl">Pitcheo General</h1>
    <p class="lsl-page-sub mt-1 text-sm text-lsl-muted"><?= h($season["name"]) ?> · Victorias sincronizadas desde cierres oficiales</p>

    <div class="lsl-rule-note mt-4 rounded-lg border border-lsl-border bg-lsl-card px-4 py-3 text-xs leading-relaxed text-lsl-muted sm:text-sm">
      Las victorias (W) se actualizan automáticamente al registrar el pitcher ganador en el cuaderno.
      IP, ERA, WHIP, K/9 y BB/9 se recalculan cuando hay datos de pitcheo registrados.
    </div>

    <input id="pitchingSearch" class="lsl-search mt-5 w-full max-w-md rounded-lg border border-lsl-border bg-lsl-card px-4 py-2.5 text-sm text-white placeholder:text-lsl-muted focus:border-lsl-accent focus:outline-none focus:ring-1 focus:ring-lsl-accent" type="search" placeholder="Buscar pitcher o equipo..." autocomplete="off">

    <div class="lsl-card lsl-pad lsl-table-wrap mt-4 overflow-hidden rounded-xl border border-lsl-border bg-lsl-card">
      <table class="lsl-table lsl-stats-table w-full text-sm" id="pitchingTable">
        <thead class="bg-lsl-bg/60 text-xs uppercase tracking-wider text-lsl-muted">
          <tr>
            <th class="sticky-col px-3 py-2 text-left">Pitcher</th>
            <th class="px-2 py-2">GP</th><th class="px-2 py-2">IP</th><th class="px-2 py-2">W</th><th class="px-2 py-2">L</th><th class="px-2 py-2">SO</th><th class="px-2 py-2">BB</th><th class="px-2 py-2">H</th><th class="px-2 py-2">ER</th><th class="px-2 py-2">ERA</th><th class="px-2 py-2">WHIP</th><th class="px-2 py-2">K/9</th><th class="px-2 py-2">BB/9</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-lsl-border">
          <?php foreach ($rows as $row): ?>
            <tr class="hover:bg-lsl-bg/30" data-search="<?= h(mb_strtolower($row["player_name"] . " " . ($row["team_name"] ?: ""))) ?>">
              <td class="sticky-col px-3 py-2">
                <strong class="text-white"><?= h($row["player_name"]) ?></strong>
                <div class="lsl-rank-team text-xs text-lsl-muted"><?= h($row["team_name"] ?: "-") ?></div>
              </td>
              <td class="px-2 py-2 text-center"><?= (int)($row["GP"] ?? $row["games_pitched"] ?? 0) ?></td>
              <td class="px-2 py-2 text-center font-mono"><?= number_format((float)($row["IP"] ?? 0), 1) ?></td>
              <td class="px-2 py-2 text-center lsl-num-hot font-bold text-lsl-green"><?= (int)($row["W"] ?? 0) ?></td>
              <td class="px-2 py-2 text-center text-lsl-red"><?= (int)($row["L"] ?? 0) ?></td>
              <td class="px-2 py-2 text-center"><?= (int)($row["SO"] ?? 0) ?></td>
              <td class="px-2 py-2 text-center"><?= (int)($row["BB"] ?? 0) ?></td>
              <td class="px-2 py-2 text-center"><?= (int)($row["H"] ?? 0) ?></td>
              <td class="px-2 py-2 text-center"><?= (int)($row["ER"] ?? 0) ?></td>
              <td class="px-2 py-2 text-center font-mono text-lsl-blue"><?= (float)($row["ERA"] ?? 0) > 0 ? number_format((float)$row["ERA"], 2) : "—" ?></td>
              <td class="px-2 py-2 text-center font-mono"><?= (float)($row["WHIP"] ?? 0) > 0 ? number_format((float)$row["WHIP"], 2) : "—" ?></td>
              <td class="px-2 py-2 text-center font-mono text-xs"><?= (float)($row["K9"] ?? 0) > 0 ? number_format((float)$row["K9"], 1) : "—" ?></td>
              <td class="px-2 py-2 text-center font-mono text-xs"><?= (float)($row["BB9"] ?? 0) > 0 ? number_format((float)$row["BB9"], 1) : "—" ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="13" class="lsl-empty px-4 py-8 text-center text-lsl-muted">Sin datos de pitcheo todavía.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<script>
  const pitchingSearch = document.getElementById("pitchingSearch");
  if (pitchingSearch) {
    pitchingSearch.addEventListener("input", () => {
      const q = pitchingSearch.value.trim().toLowerCase();
      document.querySelectorAll("#pitchingTable tbody tr[data-search]").forEach((row) => {
        row.style.display = row.dataset.search.includes(q) ? "" : "none";
      });
    });
  }
</script>
<?php include __DIR__ . "/partials/public/footer.php"; ?>
