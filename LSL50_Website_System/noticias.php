<?php
require __DIR__ . "/config.php";
require_once __DIR__ . "/src/autoload.php";

use Lsl50\Support\YoutubeHelper;

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];

$aiNews = $pdo->query("SELECT n.*, g.game_date, ht.name home_name, at.name away_name, g.final_home, g.final_away, g.youtube_video_id
  FROM ai_game_notes n
  JOIN games g ON g.id = n.game_id
  JOIN teams ht ON ht.id = g.home_team_id
  JOIN teams at ON at.id = g.away_team_id
  WHERE n.status = 'published' AND n.season_id = $seasonId
  ORDER BY COALESCE(n.published_at, n.updated_at) DESC, n.id DESC")->fetchAll();

$featuredNews = $pdo->query("SELECT * FROM media WHERE featured = 1
  ORDER BY COALESCE(week_start, created_at) DESC LIMIT 12")->fetchAll();

$pageTitle = "Noticias — Liga Softball 50+";
$leagueLogoUrl = lsl_setting($pdo, "league_logo_url", "");

include __DIR__ . "/partials/public/head.php";
include __DIR__ . "/partials/public/header.php";
?>
<main class="lsl-main">
  <div class="lsl-wrap">
    <h1 class="lsl-page-title">Noticias</h1>
    <p class="lsl-page-sub">Crónicas IA multimedia y destacados oficiales LSL50</p>
    <?php
      $editorialNews = [];
      include __DIR__ . "/partials/public/widget-news.php";
    ?>
  </div>
</main>
<?php include __DIR__ . "/partials/public/footer.php"; ?>
