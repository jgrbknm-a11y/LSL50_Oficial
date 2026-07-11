<?php
require __DIR__ . "/config.php";
require_once __DIR__ . "/src/autoload.php";

use Lsl50\Services\PublicStatsService;

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];
$calendarByMonth = PublicStatsService::calendarByMonth($pdo, $seasonId);
$monthsLimit = 999;

$pageTitle = "Calendario — Liga Softball 50+";
$leagueLogoUrl = lsl_setting($pdo, "league_logo_url", "");

include __DIR__ . "/partials/public/head.php";
include __DIR__ . "/partials/public/header.php";
?>
<main class="lsl-main">
  <div class="lsl-wrap">
    <h1 class="lsl-page-title">Calendario de Juegos</h1>
    <p class="lsl-page-sub"><?= h($season["name"]) ?></p>
    <div class="lsl-card lsl-pad">
      <?php include __DIR__ . "/partials/public/widget-calendar-home.php"; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . "/partials/public/footer.php"; ?>
