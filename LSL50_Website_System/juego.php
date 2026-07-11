<?php
require __DIR__ . "/config.php";
require_once __DIR__ . "/src/autoload.php";

use Lsl50\Services\PublicStatsService;
use Lsl50\Support\YoutubeHelper;

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];
$gameId = (int)($_GET["id"] ?? 0);

if (!$gameId) {
  header("Location: /calendario");
  exit;
}

$stmt = $pdo->prepare("SELECT g.*, ht.name home_name, ht.logo_url home_logo, at.name away_name, at.logo_url away_logo
  FROM games g
  JOIN teams ht ON ht.id = g.home_team_id
  JOIN teams at ON at.id = g.away_team_id
  WHERE g.id = ? AND COALESCE(g.season_id, $seasonId) = $seasonId");
$stmt->execute([$gameId]);
$game = $stmt->fetch();
if (!$game) {
  header("Location: /calendario");
  exit;
}

$lineTotals = PublicStatsService::gameLineTotals($pdo, [$gameId]);
$homeTotals = $lineTotals[$gameId][(int)$game["home_team_id"]] ?? ["hits" => 0, "errors" => 0];
$awayTotals = $lineTotals[$gameId][(int)$game["away_team_id"]] ?? ["hits" => 0, "errors" => 0];

$hitExpr = SqlDialect::greatest("gps.H", "gps.dbl + gps.tpl + gps.HR");
$stats = $pdo->prepare("SELECT " . lsl_sql_full_name("p") . " player_name, p.number, t.name team_name,
    gps.AB, gps.H, gps.dbl, gps.tpl, gps.R, gps.RBI, gps.HR, gps.BB, gps.SO
  FROM game_player_stats gps
  JOIN players p ON p.id = gps.player_id
  JOIN teams t ON t.id = gps.team_id
  WHERE gps.game_id = ?
  ORDER BY gps.RBI DESC, {$hitExpr} DESC, p.last_name");
$stats->execute([$gameId]);
$boxStats = $stats->fetchAll();

$note = $pdo->prepare("SELECT * FROM ai_game_notes WHERE game_id = ? AND status = 'published' LIMIT 1");
$note->execute([$gameId]);
$aiNote = $note->fetch();

$standings = PublicStatsService::standings($pdo, $seasonId);
$standingsMap = [];
foreach ($standings as $s) {
  $standingsMap[$s["name"]] = $s["record"];
}

$pageTitle = $game["away_name"] . " @ " . $game["home_name"] . " — LSL50";
$leagueLogoUrl = lsl_setting($pdo, "league_logo_url", "");
$seasonName = $season["name"];

include __DIR__ . "/partials/public/head.php";
include __DIR__ . "/partials/public/header.php";
?>
<main class="lsl-main">
  <div class="lsl-wrap lsl-home-stack">
    <?php include __DIR__ . "/partials/public/widget-scorecard.php"; ?>

    <?php if ($aiNote):
      $vid = YoutubeHelper::extractVideoId($aiNote["video_url"] ?? "") ?: YoutubeHelper::extractVideoId($game["youtube_video_id"] ?? "");
      $embed = YoutubeHelper::embedUrl($vid ?? "");
    ?>
      <section>
        <div class="lsl-section-head">
          <h2 class="lsl-section-title">Crónica del Partido</h2>
          <a class="lsl-link" href="/noticias/<?= h(lsl_public_news_slug($aiNote["title"], (int)$aiNote["id"])) ?>">Leer completa →</a>
        </div>
        <div class="lsl-grid-2">
          <article class="lsl-card lsl-pad">
            <span class="lsl-tag">Crónica IA</span>
            <h2 class="lsl-news-title"><?= h($aiNote["title"]) ?></h2>
            <p class="lsl-news-summary"><?= h($aiNote["summary"]) ?></p>
          </article>
          <?php if ($embed): ?>
            <div class="lsl-card lsl-news-card">
              <div class="lsl-news-media"><iframe src="<?= h($embed) ?>" title="Video del partido" allowfullscreen loading="lazy"></iframe></div>
            </div>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="lsl-card lsl-pad lsl-table-wrap">
      <h2 class="lsl-section-title">Box Score Oficial</h2>
      <table class="lsl-table">
        <thead><tr><th>Jugador</th><th>Equipo</th><th>AB</th><th>H</th><th>2B</th><th>3B</th><th>HR</th><th>R</th><th>RBI</th><th>BB</th><th>SO</th></tr></thead>
        <tbody>
          <?php foreach ($boxStats as $row): ?>
            <tr>
              <td><?= h(($row["number"] ? "#" . $row["number"] . " " : "") . $row["player_name"]) ?></td>
              <td><?= h($row["team_name"]) ?></td>
              <td><?= (int)$row["AB"] ?></td>
              <td><?= (int)$row["H"] ?></td>
              <td><?= (int)$row["dbl"] ?></td>
              <td><?= (int)$row["tpl"] ?></td>
              <td><?= (int)$row["HR"] ?></td>
              <td><?= (int)$row["R"] ?></td>
              <td><?= (int)$row["RBI"] ?></td>
              <td><?= (int)$row["BB"] ?></td>
              <td><?= (int)$row["SO"] ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$boxStats): ?><tr><td colspan="11" class="lsl-empty">Sin líneas oficiales registradas.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>
  </div>
</main>
<?php include __DIR__ . "/partials/public/footer.php"; ?>
