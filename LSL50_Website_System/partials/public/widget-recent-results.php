<?php
/** @var array $recentResults */
?>
<div class="overflow-hidden rounded-xl border border-lsl-border bg-lsl-card">
  <div class="border-b border-lsl-border px-4 py-3 sm:px-5">
    <h2 class="lsl-section-title text-sm font-bold uppercase tracking-widest text-white">Últimos Resultados</h2>
  </div>
  <div class="lsl-recent-list divide-y divide-lsl-border">
    <?php foreach ($recentResults as $game): ?>
      <a class="lsl-recent-item flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-sm transition hover:bg-lsl-bg/40 sm:px-5" href="/juego?id=<?= (int)$game["id"] ?>">
        <span class="lsl-meta text-xs text-lsl-muted"><?= h(lsl_public_fmt_date_es($game["game_date"])) ?></span>
        <span class="lsl-badge-final rounded px-1.5 py-0.5 text-[10px] font-bold uppercase text-lsl-green ring-1 ring-lsl-green/30">Final</span>
        <span class="lsl-recent-line text-zinc-300">
          <?= h($game["away_name"]) ?> <strong class="font-mono text-white"><?= (int)$game["final_away"] ?></strong>
          ·
          <?= h($game["home_name"]) ?> <strong class="font-mono text-white"><?= (int)$game["final_home"] ?></strong>
        </span>
      </a>
    <?php endforeach; ?>
    <?php if (!$recentResults): ?>
      <div class="lsl-empty px-4 py-8 text-center text-sm text-lsl-muted">Sin resultados registrados.</div>
    <?php endif; ?>
  </div>
</div>
