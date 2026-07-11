<?php
/** @var string $leagueLogoUrl */
?>
<header class="sticky top-0 z-50 border-b border-lsl-border bg-lsl-bg/95 backdrop-blur-md">
  <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
    <a href="/" class="flex items-center gap-3 no-underline">
      <?php if (!empty($leagueLogoUrl)): ?>
        <img src="<?= h($leagueLogoUrl) ?>" alt="LSL50" class="h-10 w-10 rounded-lg object-cover ring-1 ring-lsl-border">
      <?php else: ?>
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-lsl-card text-xs font-extrabold tracking-wider text-lsl-accent ring-1 ring-lsl-border">LSL50</span>
      <?php endif; ?>
      <div class="leading-tight">
        <strong class="block text-sm font-bold text-white sm:text-base">Liga Softball 50+</strong>
        <span class="text-xs text-lsl-muted">Legends · Broward, Florida</span>
      </div>
    </a>
    <nav class="flex w-full flex-wrap items-center gap-1 sm:w-auto sm:justify-end" aria-label="Principal">
      <?php
      $navItems = [
        ["/", "Inicio"],
        ["/equipos", "Equipos"],
        ["/calendario", "Calendario"],
        ["/posiciones", "Posiciones"],
        ["/estadisticas", "Líderes"],
        ["/bateo-general", "Bateo"],
        ["/pitcheo-general", "Pitcheo"],
        ["/noticias", "Noticias"],
      ];
      foreach ($navItems as [$href, $label]):
        $active = lsl_public_active_nav($href);
      ?>
        <a href="<?= h($href) ?>" class="rounded-md px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wide transition sm:text-sm<?= $active ? " bg-lsl-card text-lsl-accent ring-1 ring-lsl-border" : " text-zinc-300 hover:bg-lsl-card hover:text-white" ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
      <a href="/admin/" class="ml-1 rounded-md border border-lsl-border bg-lsl-card px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wide text-zinc-300 transition hover:text-white sm:text-sm">Admin</a>
    </nav>
  </div>
</header>
