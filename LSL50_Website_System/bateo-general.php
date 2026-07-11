<?php
require __DIR__ . "/config.php";
require_once __DIR__ . "/src/autoload.php";

use Lsl50\Services\StatsEngine;

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];
$allBatters = StatsEngine::battingTable($pdo, $seasonId);

$pageTitle = "Bateo General — Liga Softball 50+";
$leagueLogoUrl = lsl_setting($pdo, "league_logo_url", "");

include __DIR__ . "/partials/public/head.php";
include __DIR__ . "/partials/public/header.php";
?>
<main class="lsl-main mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
  <div class="lsl-wrap">
    <h1 class="lsl-page-title text-2xl font-extrabold tracking-tight text-white sm:text-3xl">Bateo General</h1>
    <p class="lsl-page-sub mt-1 text-sm text-lsl-muted"><?= h($season["name"]) ?> · Estadísticas oficiales del cuaderno</p>

    <input id="battingSearch" class="lsl-search mt-5 w-full max-w-md rounded-lg border border-lsl-border bg-lsl-card px-4 py-2.5 text-sm text-white placeholder:text-lsl-muted focus:border-lsl-accent focus:outline-none focus:ring-1 focus:ring-lsl-accent" type="search" placeholder="Buscar jugador o equipo..." autocomplete="off">

    <div class="lsl-card lsl-pad lsl-table-wrap mt-4 overflow-hidden rounded-xl border border-lsl-border bg-lsl-card">
      <table class="lsl-table lsl-stats-table w-full text-sm" id="battingTable">
        <thead class="bg-lsl-bg/60 text-xs uppercase tracking-wider text-lsl-muted">
          <tr>
            <th class="sticky-col px-3 py-2 text-left">Jugador</th>
            <th class="px-2 py-2">G</th><th class="px-2 py-2">AB</th><th class="px-2 py-2">R</th><th class="px-2 py-2">H</th><th class="px-2 py-2">2B</th><th class="px-2 py-2">3B</th><th class="px-2 py-2">HR</th><th class="px-2 py-2">TB</th>
            <th class="px-2 py-2">BB</th><th class="px-2 py-2">HBP</th><th class="px-2 py-2">SH</th><th class="px-2 py-2">SF</th><th class="px-2 py-2">SO</th><th class="px-2 py-2">SB</th><th class="px-2 py-2">RBI</th><th class="px-2 py-2">E</th>
            <th class="px-2 py-2">PA</th><th class="px-2 py-2">OBP</th><th class="px-2 py-2">SLG</th><th class="px-2 py-2">OPS</th><th class="px-2 py-2">ISO</th><th class="px-2 py-2">AVG</th><th class="px-2 py-2">Regla</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-lsl-border">
          <?php foreach ($allBatters as $row): ?>
            <tr class="hover:bg-lsl-bg/30" data-search="<?= h(mb_strtolower($row["player_name"] . " " . ($row["team_name"] ?: ""))) ?>">
              <td class="sticky-col px-3 py-2">
                <strong class="text-white"><?= h($row["player_name"]) ?></strong>
                <div class="lsl-rank-team text-xs text-lsl-muted"><?= h($row["team_name"] ?: "-") ?></div>
              </td>
              <td class="px-2 py-2 text-center"><?= (int)$row["games_played"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["AB"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["R"] ?></td>
              <td class="px-2 py-2 text-center lsl-num-hot font-semibold text-lsl-accent"><?= (int)$row["H"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["dbl"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["tpl"] ?></td>
              <td class="px-2 py-2 text-center lsl-num-hot font-semibold"><?= (int)$row["HR"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["TB"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["BB"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["HBP"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["SH"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["SF"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["SO"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["SB"] ?></td>
              <td class="px-2 py-2 text-center"><strong><?= (int)$row["RBI"] ?></strong></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["E"] ?></td>
              <td class="px-2 py-2 text-center"><?= (int)$row["PA"] ?></td>
              <td class="px-2 py-2 text-center lsl-num-blue font-mono"><?= h(lsl_public_fmt_avg($row["OBP"])) ?></td>
              <td class="px-2 py-2 text-center lsl-num-purple font-mono"><?= h(lsl_public_fmt_avg($row["SLG"])) ?></td>
              <td class="px-2 py-2 text-center lsl-num-purple font-mono"><?= h(lsl_public_fmt_avg($row["OPS"])) ?></td>
              <td class="px-2 py-2 text-center font-mono text-zinc-400"><?= h(lsl_public_fmt_avg($row["ISO"])) ?></td>
              <td class="px-2 py-2 text-center"><strong class="font-mono"><?= h(lsl_public_fmt_avg($row["AVG"])) ?></strong></td>
              <td class="px-2 py-2 text-center text-xs <?= !empty($row["qualified"]) ? "lsl-qualified text-lsl-green" : "lsl-not-qualified text-lsl-muted" ?>"><?= h($row["qual_label"]) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$allBatters): ?>
            <tr><td colspan="24" class="lsl-empty px-4 py-8 text-center text-lsl-muted">Todavía no hay estadísticas de bateo.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<script>
  const battingSearch = document.getElementById("battingSearch");
  if (battingSearch) {
    battingSearch.addEventListener("input", () => {
      const q = battingSearch.value.trim().toLowerCase();
      document.querySelectorAll("#battingTable tbody tr[data-search]").forEach((row) => {
        row.style.display = row.dataset.search.includes(q) ? "" : "none";
      });
    });
  }
</script>
<?php include __DIR__ . "/partials/public/footer.php"; ?>
