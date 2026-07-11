<?php
require __DIR__ . "/config.php";
require_once __DIR__ . "/src/autoload.php";

use Lsl50\Services\LeaderboardService;
use Lsl50\Services\StatsEngine;

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];
$standings = StatsEngine::standings($pdo, $seasonId, false);

$pageTitle = "Posiciones — Liga Softball 50+";
$leagueLogoUrl = lsl_setting($pdo, "league_logo_url", "");
$compact = false;
$linkFull = null;

include __DIR__ . "/partials/public/head.php";
include __DIR__ . "/partials/public/header.php";
?>
<main class="lsl-main">
  <div class="lsl-wrap">
    <h1 class="lsl-page-title">Tabla de Posiciones</h1>
    <p class="lsl-page-sub"><?= h($season["name"]) ?> · Actualizada desde el cuaderno oficial</p>
    <?php include __DIR__ . "/partials/public/widget-standings.php"; ?>
  </div>
</main>
<?php include __DIR__ . "/partials/public/footer.php"; ?>
