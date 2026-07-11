<?php
require __DIR__ . "/config.php";
require_once __DIR__ . "/src/autoload.php";

use Lsl50\Support\YoutubeHelper;

$pdo = db();
$id = (int)($_GET["id"] ?? 0);
if (!$id) { header("Location: /"); exit; }

$stmt = $pdo->prepare("SELECT n.*, g.game_date, g.youtube_video_id, ht.name home_name, at.name away_name,
    g.final_home, g.final_away
  FROM ai_game_notes n
  JOIN games g ON g.id=n.game_id
  JOIN teams ht ON ht.id=g.home_team_id
  JOIN teams at ON at.id=g.away_team_id
  WHERE n.id=? AND n.status='published'");
$stmt->execute([$id]);
$note = $stmt->fetch();
if (!$note) { header("Location: /"); exit; }

$videoId = YoutubeHelper::extractVideoId($note["video_url"] ?? "")
  ?: YoutubeHelper::extractVideoId($note["youtube_video_id"] ?? "");
$embed = YoutubeHelper::embedUrl($videoId ?? "");
$leagueLogo = lsl_setting($pdo, "league_logo_url", "");
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($note["title"]) ?> — LSL50</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/public/assets/css/lsl50-public.css">
</head>
<body>
  <header class="lsl-top">
    <div class="lsl-wrap lsl-top-inner">
      <a href="/" class="lsl-brand">
        <?php if ($leagueLogo): ?><img src="<?= h($leagueLogo) ?>" alt="LSL50"><?php endif; ?>
        <div><strong>LEGENDS SOFTBALL LEAGUE 50+</strong><span>Broward · Florida</span></div>
      </a>
      <nav class="lsl-nav">
        <a href="/">Inicio</a>
        <a href="/admin/schedule.php">Calendario</a>
        <a href="/admin/leaders.php">Líderes</a>
      </nav>
    </div>
  </header>

  <main class="lsl-wrap" style="padding: 28px 18px 48px">
    <span class="lsl-tag">Crónica oficial · IA</span>
    <h1 style="font-size:32px;line-height:1.15;margin:0 0 8px"><?= h($note["title"]) ?></h1>
    <p class="lsl-meta" style="margin-bottom:20px">
      <?= h($note["away_name"]) ?> <?= (int)$note["final_away"] ?> — <?= (int)$note["final_home"] ?> <?= h($note["home_name"]) ?>
      · <?= h($note["game_date"]) ?>
    </p>

    <div class="lsl-grid-2" style="align-items:start">
      <article class="lsl-card" style="padding:20px">
        <p class="lsl-news-summary" style="font-size:16px;color:#E4E4E7"><?= h($note["summary"]) ?></p>
        <div style="white-space:pre-line;line-height:1.65;color:#D4D4D8;font-size:15px"><?= h($note["body"]) ?></div>
      </article>

      <?php if ($embed): ?>
      <div class="lsl-card lsl-news-card">
        <div class="lsl-news-media">
          <iframe src="<?= h($embed) ?>" title="Transmisión LSL50" allowfullscreen loading="lazy"></iframe>
        </div>
        <div class="lsl-news-body">
          <span class="lsl-tag">Video del partido</span>
          <p class="lsl-meta"><?= h($note["highlight_reason"] ?? "Highlights oficiales de la liga") ?></p>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <p style="margin-top:24px"><a class="lsl-btn secondary" href="/">← Volver al inicio</a></p>
  </main>
</body>
</html>
