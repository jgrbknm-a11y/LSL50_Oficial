<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LSL50 Scorer</title>
  <link rel="stylesheet" href="/scorer/assets/scorer.css">
</head>
<body>
<header>
  <div class="bar">
    <div>
      <div class="brand">LSL50 Scorer</div>
      <div class="small" style="color:#dbe4ef">Temporada: <?= h($season["name"]) ?></div>
    </div>
    <?php if ($loggedIn): ?>
      <form method="post"><input type="hidden" name="action" value="logout"><button class="soft">Salir</button></form>
    <?php endif; ?>
  </div>
</header>
<main>
  <?php if ($notice): ?><div class="<?= str_starts_with($notice, "No se") || str_contains($notice, "incorrecto") ? "warning" : "notice" ?>"><?= h($notice) ?></div><?php endif; ?>

  <?php if (!$loggedIn): ?>
    <?php require __DIR__ . "/login.php"; ?>
  <?php else: ?>
    <?php if ($selectedGame): ?>
      <?php require __DIR__ . "/partials/tabs.php"; ?>
      <?php require __DIR__ . "/partials/closure_banner.php"; ?>
    <?php endif; ?>
    <div class="grid two">
      <?php require __DIR__ . "/game_list.php"; ?>

      <section>
        <?php if ($selectedGame): ?>
          <?php require __DIR__ . "/partials/game_workspace_prep.php"; ?>
          <?php require __DIR__ . "/lineups.php"; ?>
          <?php require __DIR__ . "/stats.php"; ?>
          <?php require __DIR__ . "/review.php"; ?>
          <?php require __DIR__ . "/plays.php"; ?>
        <?php else: ?>
          <?php require __DIR__ . "/empty_game.php"; ?>
        <?php endif; ?>
      </section>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
