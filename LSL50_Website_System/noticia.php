<?php
require __DIR__ . "/config.php";
require_once __DIR__ . "/src/autoload.php";

use Lsl50\Support\YoutubeHelper;

$pdo = db();
$slug = trim($_GET["slug"] ?? "");
$id = (int)($_GET["id"] ?? 0);

if ($slug !== "" && preg_match('/-(\d+)$/', $slug, $m)) {
  $id = (int)$m[1];
}

if (!$id) {
  header("Location: /noticias");
  exit;
}

$stmt = $pdo->prepare("SELECT n.*, g.game_date, g.youtube_video_id, ht.name home_name, at.name away_name,
    g.final_home, g.final_away
  FROM ai_game_notes n
  JOIN games g ON g.id = n.game_id
  JOIN teams ht ON ht.id = g.home_team_id
  JOIN teams at ON at.id = g.away_team_id
  WHERE n.id = ? AND n.status = 'published'");
$stmt->execute([$id]);
$note = $stmt->fetch();
if (!$note) {
  header("Location: /noticias");
  exit;
}

$videoId = YoutubeHelper::extractVideoId($note["video_url"] ?? "")
  ?: YoutubeHelper::extractVideoId($note["youtube_video_id"] ?? "");
$embed = YoutubeHelper::embedUrl($videoId ?? "");

$pageTitle = $note["title"] . " — LSL50";
$leagueLogoUrl = lsl_setting($pdo, "league_logo_url", "");

include __DIR__ . "/partials/public/head.php";
include __DIR__ . "/partials/public/header.php";
?>
<main class="lsl-main">
  <div class="lsl-wrap" style="max-width:960px">
    <span class="lsl-tag">Crónica oficial · IA</span>
    <h1 class="lsl-page-title" style="font-size:34px"><?= h($note["title"]) ?></h1>
    <p class="lsl-page-sub">
      <?= h($note["away_name"]) ?> <?= (int)$note["final_away"] ?> — <?= (int)$note["final_home"] ?> <?= h($note["home_name"]) ?>
      · <?= h(lsl_public_fmt_date_es($note["game_date"])) ?>
    </p>

    <div class="lsl-grid-2" style="align-items:start">
      <article class="lsl-card lsl-pad">
        <p class="lsl-news-summary" style="font-size:16px"><?= h($note["summary"]) ?></p>
        <div style="white-space:pre-line;line-height:1.65;color:#D4D4D8;font-size:15px"><?= h($note["body"]) ?></div>
      </article>
      <?php if ($embed): ?>
        <div class="lsl-card lsl-news-card">
          <div class="lsl-news-media"><iframe src="<?= h($embed) ?>" title="Transmisión LSL50" allowfullscreen loading="lazy"></iframe></div>
          <div class="lsl-news-body">
            <span class="lsl-tag">Video del partido</span>
            <p class="lsl-meta"><?= h($note["highlight_reason"] ?? "Highlights oficiales LSL50") ?></p>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <p style="margin-top:24px"><a class="lsl-btn secondary" href="/noticias">← Volver a Noticias</a></p>
  </div>
</main>
<?php include __DIR__ . "/partials/public/footer.php"; ?>
