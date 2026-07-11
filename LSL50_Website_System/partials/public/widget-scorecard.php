<?php
/** @var array $game @var array $standingsMap @var array|null $homeTotals @var array|null $awayTotals @var string $seasonName */
$homeTotals = $homeTotals ?? ["hits" => 0, "errors" => 0];
$awayTotals = $awayTotals ?? ["hits" => 0, "errors" => 0];
$homeRecord = "0-0";
$awayRecord = "0-0";
foreach ($standingsMap as $name => $rec) {
  if ($name === $game["home_name"]) $homeRecord = $rec;
  if ($name === $game["away_name"]) $awayRecord = $rec;
}
$homeWin = (int)$game["final_home"] >= (int)$game["final_away"];
$awayWin = (int)$game["final_away"] > (int)$game["final_home"];
?>
<article class="overflow-hidden rounded-xl border border-lsl-border bg-lsl-card shadow-xl shadow-black/30">
  <div class="flex items-center justify-between border-b border-lsl-border bg-lsl-bg/50 px-4 py-2.5 sm:px-5">
    <strong class="text-xs font-bold uppercase tracking-widest text-white">Último Resultado</strong>
    <span class="text-xs text-lsl-muted"><?= h($seasonName) ?> · <?= h(lsl_public_fmt_date_es($game["game_date"])) ?></span>
  </div>
  <div class="px-4 py-5 sm:px-6">
    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-6">
      <div class="text-center sm:text-right">
        <?php if (!empty($game["home_logo"])): ?>
          <img src="<?= h($game["home_logo"]) ?>" alt="" class="mx-auto mb-2 h-12 w-12 rounded-lg object-cover ring-1 ring-lsl-border sm:ml-auto sm:mr-0">
        <?php endif; ?>
        <h3 class="text-sm font-bold text-white sm:text-base"><?= h($game["home_name"]) ?></h3>
        <div class="mt-1 text-xs font-mono text-lsl-muted"><?= h($homeRecord) ?></div>
      </div>
      <div class="flex items-center gap-2 font-black tabular-nums">
        <span class="text-3xl sm:text-4xl<?= $homeWin ? " text-lsl-accent" : " text-zinc-400" ?>"><?= (int)$game["final_home"] ?></span>
        <span class="text-xl text-lsl-muted">-</span>
        <span class="text-3xl sm:text-4xl<?= $awayWin ? " text-lsl-accent" : " text-zinc-400" ?>"><?= (int)$game["final_away"] ?></span>
      </div>
      <div class="text-center sm:text-left">
        <?php if (!empty($game["away_logo"])): ?>
          <img src="<?= h($game["away_logo"]) ?>" alt="" class="mx-auto mb-2 h-12 w-12 rounded-lg object-cover ring-1 ring-lsl-border sm:mr-auto sm:ml-0">
        <?php endif; ?>
        <h3 class="text-sm font-bold text-white sm:text-base"><?= h($game["away_name"]) ?></h3>
        <div class="mt-1 text-xs font-mono text-lsl-muted"><?= h($awayRecord) ?></div>
      </div>
    </div>
    <div class="mt-4 text-center">
      <span class="inline-block rounded-full border border-lsl-border bg-lsl-bg px-3 py-0.5 text-[10px] font-bold uppercase tracking-[0.2em] text-lsl-accent">Final</span>
      <div class="mt-2 text-xs text-lsl-muted"><?= h($game["location"] ?: "Campo Principal") ?></div>
    </div>
    <table class="lsl-line-score mt-5 w-full overflow-hidden rounded-lg border border-lsl-border text-sm">
      <thead class="bg-lsl-bg/60 text-xs uppercase tracking-wider text-lsl-muted">
        <tr><th class="px-3 py-2 text-left">Equipo</th><th class="px-3 py-2 text-center">C</th><th class="px-3 py-2 text-center">H</th><th class="px-3 py-2 text-center">E</th></tr>
      </thead>
      <tbody class="divide-y divide-lsl-border">
        <tr>
          <td class="px-3 py-2 font-medium"><?= h($game["home_name"]) ?></td>
          <td class="px-3 py-2 text-center font-bold text-lsl-accent"><?= (int)$game["final_home"] ?></td>
          <td class="px-3 py-2 text-center"><?= (int)$homeTotals["hits"] ?></td>
          <td class="px-3 py-2 text-center"><?= (int)$homeTotals["errors"] ?></td>
        </tr>
        <tr>
          <td class="px-3 py-2 font-medium"><?= h($game["away_name"]) ?></td>
          <td class="px-3 py-2 text-center font-bold"><?= (int)$game["final_away"] ?></td>
          <td class="px-3 py-2 text-center"><?= (int)$awayTotals["hits"] ?></td>
          <td class="px-3 py-2 text-center"><?= (int)$awayTotals["errors"] ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</article>
