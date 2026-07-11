<?php
/** @var array $standings @var bool $compact @var string|null $linkFull */
$compact = $compact ?? false;
$linkFull = $linkFull ?? "/posiciones";
?>
<div class="overflow-hidden rounded-xl border border-lsl-border bg-lsl-card shadow-lg shadow-black/20">
  <div class="flex items-center justify-between border-b border-lsl-border px-4 py-3 sm:px-5">
    <h2 class="text-sm font-bold uppercase tracking-widest text-white sm:text-base">Tabla de Posiciones</h2>
    <?php if ($linkFull): ?>
      <a class="text-xs font-semibold text-lsl-accent transition hover:text-yellow-300 sm:text-sm" href="<?= h($linkFull) ?>">Ver tabla completa →</a>
    <?php endif; ?>
  </div>
  <div class="lsl-table-wrap overflow-x-auto">
    <table class="lsl-table w-full min-w-[520px] text-sm<?= $compact ? " lsl-table-compact" : "" ?>">
      <thead class="bg-lsl-bg/60 text-xs uppercase tracking-wider text-lsl-muted">
        <tr>
          <th class="px-3 py-2 text-left">#</th>
          <th class="px-3 py-2 text-left">Equipo</th>
          <?php if ($compact): ?>
            <th class="px-3 py-2 text-center">G</th>
            <th class="px-3 py-2 text-center">P</th>
            <th class="px-3 py-2 text-center">PCT</th>
          <?php else: ?>
            <th class="px-3 py-2 text-center">JJ</th>
            <th class="px-3 py-2 text-center">G</th>
            <th class="px-3 py-2 text-center">P</th>
            <th class="px-3 py-2 text-center">AVE</th>
            <th class="px-3 py-2 text-center">DIF</th>
            <th class="px-3 py-2 text-center">CF</th>
            <th class="px-3 py-2 text-center">CC</th>
            <th class="px-3 py-2 text-center">Racha</th>
            <th class="px-3 py-2 text-center">U10</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-lsl-border">
        <?php foreach ($standings as $row): ?>
          <tr class="transition hover:bg-lsl-bg/40">
            <td class="px-3 py-2.5 font-semibold text-lsl-muted"><?= (int)$row["pos"] ?></td>
            <td class="px-3 py-2.5">
              <div class="lsl-team-cell flex items-center gap-2">
                <?php if (!empty($row["logo_url"])): ?>
                  <img src="<?= h($row["logo_url"]) ?>" alt="" class="h-6 w-6 rounded object-cover ring-1 ring-lsl-border">
                <?php endif; ?>
                <strong class="truncate text-white" title="<?= h($row["name"]) ?>"><?= h($row["name"]) ?></strong>
              </div>
            </td>
            <?php if ($compact): ?>
              <td class="px-3 py-2.5 text-center font-semibold text-lsl-green"><?= (int)$row["wins"] ?></td>
              <td class="px-3 py-2.5 text-center text-lsl-red"><?= (int)$row["losses"] ?></td>
              <td class="px-3 py-2.5 text-center font-mono text-lsl-accent"><?= h($row["pct_fmt"]) ?></td>
            <?php else: ?>
              <td class="px-3 py-2.5 text-center"><?= (int)$row["gp"] ?></td>
              <td class="px-3 py-2.5 text-center font-semibold text-lsl-green"><?= (int)$row["wins"] ?></td>
              <td class="px-3 py-2.5 text-center text-lsl-red"><?= (int)$row["losses"] ?></td>
              <td class="px-3 py-2.5 text-center font-mono text-lsl-accent"><?= h($row["pct_fmt"]) ?></td>
              <td class="px-3 py-2.5 text-center"><?= h($row["gb"]) ?></td>
              <td class="px-3 py-2.5 text-center"><?= (int)$row["runs_for"] ?></td>
              <td class="px-3 py-2.5 text-center"><?= (int)$row["runs_against"] ?></td>
              <td class="px-3 py-2.5 text-center font-semibold"><?= h($row["streak"]) ?></td>
              <td class="px-3 py-2.5 text-center text-xs text-lsl-muted"><?= h($row["l10"]) ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
