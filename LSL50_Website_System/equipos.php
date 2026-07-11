<?php
require __DIR__ . "/config.php";
require_once __DIR__ . "/src/autoload.php";

use Lsl50\Services\PublicStatsService;

$pdo = db();
$teams = PublicStatsService::teamsWithManagers($pdo);

$pageTitle = "Equipos — Liga Softball 50+";
$leagueLogoUrl = lsl_setting($pdo, "league_logo_url", "");

include __DIR__ . "/partials/public/head.php";
include __DIR__ . "/partials/public/header.php";
?>
<main class="lsl-main">
  <div class="lsl-wrap">
    <h1 class="lsl-page-title">Equipos</h1>
    <p class="lsl-page-sub">Temporada activa LSL50</p>
    <div class="lsl-team-grid">
      <?php foreach ($teams as $team): ?>
        <article class="lsl-card lsl-team-card">
          <?php if (!empty($team["logo_url"])): ?><img src="<?= h($team["logo_url"]) ?>" alt=""><?php endif; ?>
          <h3><?= h($team["name"]) ?></h3>
          <p class="lsl-meta">Manager: <?= h($team["manager_display"] ?? $team["manager_name"] ?? "N/A") ?></p>
          <?php if (!empty($team["city"])): ?><p class="lsl-meta"><?= h($team["city"]) ?></p><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . "/partials/public/footer.php"; ?>
