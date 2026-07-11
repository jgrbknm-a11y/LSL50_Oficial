<?php
use Lsl50\Support\YoutubeHelper;

/** @var array $aiNews @var array $featuredNews @var array $editorialNews */
$aiNews = $aiNews ?? [];
$featuredNews = $featuredNews ?? [];
$editorialNews = $editorialNews ?? [];
$hasNews = $aiNews || $featuredNews || $editorialNews;
?>
<div class="lsl-news-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  <?php foreach ($aiNews as $note):
    $vid = YoutubeHelper::extractVideoId($note["video_url"] ?? "");
    $embed = YoutubeHelper::embedUrl($vid ?? "");
    $slug = lsl_public_news_slug($note["title"], (int)$note["id"]);
  ?>
    <article class="lsl-card lsl-news-card overflow-hidden rounded-xl border border-lsl-border bg-lsl-card shadow-lg shadow-black/20">
      <div class="lsl-news-media aspect-video w-full overflow-hidden bg-lsl-bg">
        <?php if ($embed): ?>
          <iframe class="h-full w-full" src="<?= h($embed) ?>" title="<?= h($note["title"]) ?>" allowfullscreen loading="lazy"></iframe>
        <?php else: ?>
          <div class="lsl-news-media-placeholder flex h-full items-center justify-center text-xs font-bold uppercase tracking-widest text-lsl-muted">Crónica IA · LSL50</div>
        <?php endif; ?>
      </div>
      <div class="lsl-news-body p-4">
        <span class="lsl-tag inline-block rounded bg-lsl-accent/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-lsl-accent">Crónica IA</span>
        <h3 class="lsl-news-title mt-2 text-base font-bold leading-snug text-white"><a class="transition hover:text-lsl-accent" href="/noticias/<?= h($slug) ?>"><?= h($note["title"]) ?></a></h3>
        <p class="lsl-news-summary mt-2 line-clamp-3 text-sm text-lsl-muted"><?= h($note["summary"]) ?></p>
        <div class="lsl-meta mt-3 text-xs text-zinc-500"><?= h(lsl_public_fmt_date_es($note["game_date"])) ?> · <?= h($note["away_name"]) ?> @ <?= h($note["home_name"]) ?></div>
      </div>
    </article>
  <?php endforeach; ?>

  <?php foreach ($editorialNews as $item): ?>
    <article class="lsl-card lsl-news-card lsl-news-card-text overflow-hidden rounded-xl border border-lsl-border bg-lsl-card p-4">
      <span class="lsl-tag lsl-tag-muted inline-block rounded bg-lsl-bg px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-lsl-muted">Noticia</span>
      <h3 class="lsl-news-title mt-2 text-base font-bold text-white"><?= h($item["title"]) ?></h3>
      <?php if (!empty($item["summary"])): ?><p class="lsl-news-summary mt-2 text-sm text-lsl-muted"><?= h($item["summary"]) ?></p><?php endif; ?>
      <div class="lsl-meta mt-3 text-xs text-zinc-500"><?= h($item["published_at"] ?? "") ?></div>
    </article>
  <?php endforeach; ?>

  <?php foreach ($featuredNews as $item): ?>
    <article class="lsl-card lsl-news-card overflow-hidden rounded-xl border border-lsl-border bg-lsl-card">
      <?php if (($item["type"] ?? "") === "image" && !empty($item["thumbnail_url"] ?: $item["url"])): ?>
        <div class="lsl-news-media aspect-video overflow-hidden">
          <img src="<?= h($item["thumbnail_url"] ?: $item["url"]) ?>" alt="" class="lsl-news-img h-full w-full object-cover">
        </div>
      <?php endif; ?>
      <div class="lsl-news-body p-4">
        <span class="lsl-tag lsl-tag-muted inline-block rounded bg-lsl-bg px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-lsl-muted">Destacado</span>
        <h3 class="lsl-news-title mt-2 text-base font-bold text-white"><?= h($item["title"]) ?></h3>
        <div class="lsl-meta mt-3 text-xs text-zinc-500"><?= h($item["week_start"] ?: date("Y-m-d", strtotime($item["created_at"] ?? "now"))) ?></div>
      </div>
    </article>
  <?php endforeach; ?>

  <?php if (!$hasNews): ?>
    <div class="lsl-empty col-span-full rounded-lg border border-dashed border-lsl-border px-4 py-8 text-center text-sm text-lsl-muted">Publica una crónica IA o noticia destacada para mostrarla aquí.</div>
  <?php endif; ?>
</div>
