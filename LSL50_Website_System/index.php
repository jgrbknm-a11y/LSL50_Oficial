<?php
require __DIR__ . "/config.php";
require_once __DIR__ . "/src/autoload.php";

use Lsl50\Services\LeaderboardService;
use Lsl50\Services\PublicStatsService;
use Lsl50\Services\StatsEngine;

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];

LeaderboardService::syncPitchingWins($pdo, $seasonId);

$standings = StatsEngine::standings($pdo, $seasonId);
$standingsMap = [];
foreach ($standings as $s) {
  $standingsMap[$s["name"]] = $s["record"];
}

$latestDate = PublicStatsService::latestGameDay($pdo, $seasonId);
$heroGames = $latestDate ? PublicStatsService::gamesOnDate($pdo, $seasonId, $latestDate) : [];
$heroGame = $heroGames[0] ?? null;
$lineTotals = $heroGames
  ? PublicStatsService::gameLineTotals($pdo, array_map(fn($g) => (int)$g["id"], $heroGames))
  : [];

$calendarByMonth = PublicStatsService::calendarByMonth($pdo, $seasonId);
$recentResults = PublicStatsService::recentResults($pdo, $seasonId, 6);
$upcomingGames = PublicStatsService::upcomingGames($pdo, $seasonId, 4);

$aiNews = $pdo->query("SELECT n.*, g.game_date, ht.name home_name, at.name away_name
  FROM ai_game_notes n
  JOIN games g ON g.id = n.game_id
  JOIN teams ht ON ht.id = g.home_team_id
  JOIN teams at ON at.id = g.away_team_id
  WHERE n.status = 'published' AND n.season_id = $seasonId
  ORDER BY COALESCE(n.published_at, n.updated_at) DESC, n.id DESC
  LIMIT 5")->fetchAll();

$featuredNews = $pdo->query("SELECT * FROM media WHERE featured = 1
  ORDER BY COALESCE(week_start, created_at) DESC, order_index ASC, id DESC LIMIT 3")->fetchAll();

$pageTitle = "Liga Softball 50+ | Inicio";
$leagueLogoUrl = lsl_setting($pdo, "league_logo_url", "");

include __DIR__ . "/partials/public/head.php";
include __DIR__ . "/partials/public/header.php";
?>
<main class="lsl-main">
  <div class="lsl-wrap lsl-home-stack">
    <?php if ($heroGame):
      $game = $heroGame;
      $homeTotals = $lineTotals[(int)$game["id"]][(int)$game["home_team_id"]] ?? ["hits" => 0, "errors" => 0];
      $awayTotals = $lineTotals[(int)$game["id"]][(int)$game["away_team_id"]] ?? ["hits" => 0, "errors" => 0];
      $seasonName = $season["name"];
      include __DIR__ . "/partials/public/widget-scorecard.php";
    endif; ?>

    <div class="lsl-home-grid">
      <section class="lsl-card lsl-pad">
        <div class="lsl-section-head">
          <h2 class="lsl-section-title">Calendario de Juegos</h2>
          <a class="lsl-link" href="/calendario">Ver calendario completo →</a>
        </div>
        <?php include __DIR__ . "/partials/public/widget-calendar-home.php"; ?>
      </section>

      <aside class="lsl-sidebar-stack">
        <?php
          $compact = true;
          include __DIR__ . "/partials/public/widget-standings.php";
          include __DIR__ . "/partials/public/widget-recent-results.php";
        ?>
      </aside>
    </div>

    <section>
      <div class="lsl-section-head">
        <h2 class="lsl-section-title">Últimas Noticias</h2>
        <a class="lsl-link" href="/noticias">Ver todas las noticias →</a>
      </div>
      <?php include __DIR__ . "/partials/public/widget-news.php"; ?>
    </section>

    <section class="lsl-card lsl-pad">
      <div class="lsl-section-head">
        <h2 class="lsl-section-title">Próximos Juegos</h2>
        <a class="lsl-link" href="/calendario">Ver calendario completo →</a>
      </div>
      <?php if ($upcomingGames): ?>
        <div class="lsl-calendar-list">
          <?php foreach ($upcomingGames as $game): ?>
            <div class="lsl-cal-card">
              <div class="lsl-cal-date">
                <span><?= h(strtoupper((new DateTimeImmutable($game["game_date"]))->format("D"))) ?></span>
                <b><?= h((new DateTimeImmutable($game["game_date"]))->format("j")) ?></b>
              </div>
              <div class="lsl-cal-match">
                <div class="lsl-cal-team">
                  <?php if (!empty($game["home_logo"])): ?><img src="<?= h($game["home_logo"]) ?>" alt=""><?php endif; ?>
                  <span><?= h($game["home_label"] ?: $game["home_name"]) ?></span>
                </div>
                <div class="lsl-cal-score">
                  <em class="lsl-badge-sched">Programado</em>
                  <span><?= h(date("g:i A", strtotime($game["game_time"]))) ?></span>
                </div>
                <div class="lsl-cal-team">
                  <?php if (!empty($game["away_logo"])): ?><img src="<?= h($game["away_logo"]) ?>" alt=""><?php endif; ?>
                  <span><?= h($game["away_label"] ?: $game["away_name"]) ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="lsl-empty">No hay juegos programados próximamente.</div>
      <?php endif; ?>
    </section>

    <section>
      <div class="lsl-section-head">
        <h2 class="lsl-section-title">Resultados Recientes</h2>
      </div>
      <div class="lsl-recent-list">
        <?php foreach ($recentResults as $game): ?>
          <a class="lsl-recent-item" href="/juego?id=<?= (int)$game["id"] ?>">
            <span class="lsl-meta"><?= h(lsl_public_fmt_date_es($game["game_date"])) ?></span>
            <span class="lsl-badge-final">Final</span>
            <span class="lsl-recent-line">
              <?= h($game["away_name"]) ?> <strong><?= (int)$game["final_away"] ?></strong>
              · <?= h($game["home_name"]) ?> <strong><?= (int)$game["final_home"] ?></strong>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</main>
<?php include __DIR__ . "/partials/public/footer.php"; ?>
