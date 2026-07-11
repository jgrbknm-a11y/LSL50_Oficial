<?php
/** @var array<string, list<array>> $calendarByMonth @var int $monthsLimit */
$monthsLimit = $monthsLimit ?? 3;
$monthNames = ["01"=>"enero","02"=>"febrero","03"=>"marzo","04"=>"abril","05"=>"mayo","06"=>"junio","07"=>"julio","08"=>"agosto","09"=>"septiembre","10"=>"octubre","11"=>"noviembre","12"=>"diciembre"];
$shown = 0;
?>
<div class="lsl-calendar-stack space-y-6">
  <?php foreach ($calendarByMonth as $monthKey => $events): ?>
    <?php if ($shown >= $monthsLimit) break; $shown++; ?>
    <?php [$y, $m] = explode("-", $monthKey); ?>
    <h3 class="lsl-month-title text-sm font-bold uppercase tracking-widest text-lsl-accent"><?= h($monthNames[$m] ?? $m) ?> <?= h($y) ?></h3>
    <div class="lsl-calendar-list space-y-2">
      <?php foreach ($events as $event): ?>
        <a class="lsl-cal-card flex gap-3 rounded-xl border border-lsl-border bg-lsl-card p-3 transition hover:border-lsl-accent/40 hover:bg-lsl-bg/40" href="<?= (int)$event["id"] > 0 ? '/juego?id=' . (int)$event["id"] : '/calendario' ?>">
          <div class="lsl-cal-date flex w-14 shrink-0 flex-col items-center justify-center rounded-lg bg-lsl-bg px-2 py-1 ring-1 ring-lsl-border">
            <span class="text-[10px] font-bold uppercase text-lsl-muted"><?= h(strtoupper((new DateTimeImmutable($event["game_date"]))->format("D"))) ?></span>
            <b class="text-xl font-black text-white"><?= h((new DateTimeImmutable($event["game_date"]))->format("j")) ?></b>
          </div>
          <div class="lsl-cal-match min-w-0 flex-1">
            <div class="lsl-cal-team flex items-center gap-2 text-sm font-semibold text-white">
              <?php if (!empty($event["home_logo"])): ?><img src="<?= h($event["home_logo"]) ?>" alt="" class="h-5 w-5 rounded object-cover"><?php endif; ?>
              <span class="truncate"><?= h($event["home_name"]) ?></span>
            </div>
            <div class="lsl-cal-score my-1 flex items-center gap-2 text-sm">
              <?php if ($event["is_final"]): ?>
                <strong class="font-mono text-lsl-accent"><?= (int)$event["final_home"] ?> - <?= (int)$event["final_away"] ?></strong>
                <em class="lsl-badge-final rounded px-1.5 py-0.5 text-[10px] font-bold uppercase not-italic text-lsl-green ring-1 ring-lsl-green/30">Final</em>
              <?php else: ?>
                <em class="lsl-badge-sched rounded px-1.5 py-0.5 text-[10px] font-bold uppercase not-italic text-lsl-blue ring-1 ring-lsl-blue/30">Programado</em>
                <span class="text-lsl-muted"><?= h(date("g:i A", strtotime($event["game_time"]))) ?></span>
              <?php endif; ?>
            </div>
            <div class="lsl-cal-team flex items-center gap-2 text-sm text-zinc-300">
              <?php if (!empty($event["away_logo"])): ?><img src="<?= h($event["away_logo"]) ?>" alt="" class="h-5 w-5 rounded object-cover"><?php endif; ?>
              <span class="truncate"><?= h($event["away_name"]) ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($shown === 0): ?>
    <div class="lsl-empty rounded-lg border border-dashed border-lsl-border px-4 py-8 text-center text-sm text-lsl-muted">No hay juegos en el calendario todavía.</div>
  <?php endif; ?>
</div>
