<?php
require __DIR__ . "/../config.php";
require_once __DIR__ . "/../src/autoload.php";
require_admin();

use Lsl50\Services\AiNewsGenerator;

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];

function ai_http_json(string $method, string $url, array $headers = [], ?array $payload = null): array {
  $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $headerLines = array_merge(["Accept: application/json"], $headers);
  if ($body !== null) {
    $headerLines[] = "Content-Type: application/json";
  }

  if (function_exists("curl_init")) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headerLines,
      CURLOPT_TIMEOUT => 20,
      CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    if ($body !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false) {
      return ["ok" => false, "status" => 0, "error" => $error ?: "No se pudo conectar."];
    }
  } else {
    $context = stream_context_create([
      "http" => [
        "method" => $method,
        "header" => implode("\r\n", $headerLines),
        "content" => $body ?? "",
        "timeout" => 20,
        "ignore_errors" => true,
      ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
      $status = (int)$m[1];
    }
    if ($raw === false) {
      return ["ok" => false, "status" => $status, "error" => "No se pudo conectar."];
    }
  }

  $data = json_decode($raw, true);
  if (!is_array($data)) {
    return ["ok" => false, "status" => $status, "error" => "Respuesta inválida.", "raw" => $raw];
  }
  if ($status < 200 || $status >= 300) {
    $message = $data["error"]["message"] ?? $data["error"]["errors"][0]["message"] ?? "Error API.";
    return ["ok" => false, "status" => $status, "error" => $message, "data" => $data];
  }
  return ["ok" => true, "status" => $status, "data" => $data];
}

function ai_parse_youtube_handle(string $channelUrl): string {
  if (preg_match('~@([A-Za-z0-9._-]+)~', $channelUrl, $m)) {
    return "@" . $m[1];
  }
  return trim($channelUrl);
}

function ai_youtube_recent_videos(string $apiKey, string $channelUrl, int $limit = 8): array {
  $handle = ai_parse_youtube_handle($channelUrl);
  $channelApi = "https://www.googleapis.com/youtube/v3/channels?" . http_build_query([
    "part" => "id,snippet",
    "forHandle" => $handle,
    "key" => $apiKey,
  ]);
  $channelResponse = ai_http_json("GET", $channelApi);
  if (!$channelResponse["ok"]) {
    return $channelResponse + ["videos" => []];
  }
  $items = $channelResponse["data"]["items"] ?? [];
  if (!$items) {
    return ["ok" => false, "status" => 404, "error" => "No se encontró el canal con ese handle.", "videos" => []];
  }
  $channelId = $items[0]["id"] ?? "";
  if ($channelId === "") {
    return ["ok" => false, "status" => 404, "error" => "El canal no devolvió ID.", "videos" => []];
  }

  $searchApi = "https://www.googleapis.com/youtube/v3/search?" . http_build_query([
    "part" => "snippet",
    "channelId" => $channelId,
    "maxResults" => max(1, min(20, $limit)),
    "order" => "date",
    "type" => "video",
    "key" => $apiKey,
  ]);
  $videoResponse = ai_http_json("GET", $searchApi);
  if (!$videoResponse["ok"]) {
    return $videoResponse + ["videos" => []];
  }
  $videos = [];
  foreach (($videoResponse["data"]["items"] ?? []) as $item) {
    $videoId = $item["id"]["videoId"] ?? "";
    if ($videoId === "") {
      continue;
    }
    $videos[] = [
      "id" => $videoId,
      "url" => "https://www.youtube.com/watch?v=" . $videoId,
      "title" => $item["snippet"]["title"] ?? "Video LSL50",
      "published_at" => $item["snippet"]["publishedAt"] ?? "",
      "thumbnail" => $item["snippet"]["thumbnails"]["medium"]["url"] ?? ($item["snippet"]["thumbnails"]["default"]["url"] ?? ""),
      "description" => $item["snippet"]["description"] ?? "",
    ];
  }
  return ["ok" => true, "status" => 200, "channel_id" => $channelId, "videos" => $videos];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = post("action");
  if ($action === "save_config") {
    $channel = trim(post("youtube_channel_url")) ?: "https://www.youtube.com/@LegendsSoftballLeague50";
    lsl_set_setting($pdo, "ai_youtube_channel_url", $channel);
    lsl_set_setting($pdo, "ai_publish_mode", post("publish_mode") === "auto" ? "auto" : "review");
    lsl_set_setting($pdo, "ai_clip_seconds", (string)max(15, min(180, (int)post("clip_seconds", "45"))));
    lsl_set_setting($pdo, "ai_editorial_style", trim(post("editorial_style")) ?: "Profesional deportivo, claro, positivo y breve.");
    lsl_set_setting($pdo, "openai_model", trim(post("openai_model")) ?: "gpt-4.1-mini");
    if (trim(post("openai_api_key")) !== "") lsl_set_setting($pdo, "openai_api_key", trim(post("openai_api_key")));
    if (trim(post("youtube_api_key")) !== "") lsl_set_setting($pdo, "youtube_api_key", trim(post("youtube_api_key")));
    flash("Configuración del Publicador IA guardada.");
  }

  if ($action === "sync_youtube") {
    $apiKey = lsl_setting($pdo, "youtube_api_key", "");
    $channel = lsl_setting($pdo, "ai_youtube_channel_url", "https://www.youtube.com/@LegendsSoftballLeague50");
    if ($apiKey === "") {
      flash("Falta guardar la YouTube API Key.");
    } else {
      $result = ai_youtube_recent_videos($apiKey, $channel, 8);
      if ($result["ok"]) {
        lsl_set_setting($pdo, "ai_youtube_recent_videos", json_encode($result["videos"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        lsl_set_setting($pdo, "ai_youtube_channel_id", $result["channel_id"] ?? "");
        flash("YouTube conectado. Videos recientes sincronizados: " . count($result["videos"]) . ".");
      } else {
        flash("YouTube API: " . ($result["error"] ?? "No se pudo sincronizar."));
      }
    }
  }

  if ($action === "test_openai") {
    $apiKey = lsl_setting($pdo, "openai_api_key", "");
    $model = lsl_setting($pdo, "openai_model", "gpt-4.1-mini");
    if ($apiKey === "") {
      flash("Falta guardar la OpenAI API Key.");
    } else {
      $result = ai_http_json("POST", "https://api.openai.com/v1/responses", [
        "Authorization: Bearer " . $apiKey,
      ], [
        "model" => $model,
        "input" => "Responde solo con: LSL50 IA lista",
      ]);
      flash($result["ok"] ? "OpenAI conectado correctamente con el modelo $model." : "OpenAI API: " . ($result["error"] ?? "No se pudo conectar."));
    }
  }

  if ($action === "generate_note") {
    $gameId = (int)post("game_id");
    $videoUrl = trim(post("video_url"));
    $videoMeta = [];
    $recentVideos = json_decode(lsl_setting($pdo, "ai_youtube_recent_videos", "[]"), true) ?: [];
    foreach ($recentVideos as $video) {
      if (($video["url"] ?? "") === $videoUrl) {
        $videoMeta = $video;
        break;
      }
    }

    $result = AiNewsGenerator::generateForGame($pdo, $seasonId, $gameId, [
      "video_url" => $videoUrl,
      "video_meta" => $videoMeta,
      "status" => "draft",
      "require_stats" => true,
    ]);

    if (!$result["ok"]) {
      flash($result["error"] ?? "No se pudo generar la nota.");
    } elseif (($result["provider"] ?? "") === "openai") {
      flash("Borrador generado con OpenAI y estadísticas oficiales.");
    } else {
      flash("Borrador generado con estadísticas oficiales (redacción local).");
    }
  }

  if ($action === "publish_note") {
    $pdo->prepare("UPDATE ai_game_notes SET status='published', published_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE id=?")
      ->execute([(int)post("id")]);
    flash("Nota publicada en la portada.");
  }

  if ($action === "unpublish_note") {
    $pdo->prepare("UPDATE ai_game_notes SET status='draft', published_at=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=?")
      ->execute([(int)post("id")]);
    flash("Nota retirada de la portada.");
  }

  if ($action === "delete_note") {
    $pdo->prepare("DELETE FROM ai_game_notes WHERE id=?")->execute([(int)post("id")]);
    flash("Borrador eliminado.");
  }

  header("Location: /admin/ai-publisher.php");
  exit;
}

$defaultChannel = "https://www.youtube.com/@LegendsSoftballLeague50";
$youtubeChannelUrl = lsl_setting($pdo, "ai_youtube_channel_url", $defaultChannel) ?: $defaultChannel;
$publishMode = lsl_setting($pdo, "ai_publish_mode", "review");
$clipSeconds = lsl_setting($pdo, "ai_clip_seconds", "45");
$editorialStyle = lsl_setting($pdo, "ai_editorial_style", "Profesional deportivo, claro, positivo y breve.");
$openAiModel = lsl_setting($pdo, "openai_model", "gpt-4.1-mini");
$hasOpenAiKey = lsl_setting($pdo, "openai_api_key", "") !== "";
$hasYoutubeKey = lsl_setting($pdo, "youtube_api_key", "") !== "";
$youtubeChannelId = lsl_setting($pdo, "ai_youtube_channel_id", "");
$recentVideos = json_decode(lsl_setting($pdo, "ai_youtube_recent_videos", "[]"), true) ?: [];

$games = $pdo->query("SELECT g.*, ht.name home_name, at.name away_name,
    COUNT(gps.id) stat_rows,
    n.id note_id,
    n.status note_status
  FROM games g
  JOIN teams ht ON ht.id=g.home_team_id
  JOIN teams at ON at.id=g.away_team_id
  LEFT JOIN game_player_stats gps ON gps.game_id=g.id
  LEFT JOIN ai_game_notes n ON n.game_id=g.id
  WHERE COALESCE(g.season_id, $seasonId) = $seasonId
  GROUP BY g.id
  ORDER BY g.game_date DESC, g.id DESC
  LIMIT 20")->fetchAll();

$notes = $pdo->query("SELECT n.*, g.game_date, ht.name home_name, at.name away_name
  FROM ai_game_notes n
  JOIN games g ON g.id=n.game_id
  JOIN teams ht ON ht.id=g.home_team_id
  JOIN teams at ON at.id=g.away_team_id
  WHERE n.season_id=$seasonId
  ORDER BY n.updated_at DESC, n.id DESC")->fetchAll();

include __DIR__ . "/../partials/header.php";
?>

<h1 class="text-2xl font-bold mb-4">Publicador IA</h1>
<div class="notice">Canal configurado: <strong><?= h($youtubeChannelUrl) ?></strong>. Modo recomendado activo: <strong><?= $publishMode === "auto" ? "Publicación automática" : "Revisión antes de publicar" ?></strong>.</div>
<?php flashes(); ?>

<div class="grid md:grid-cols-2 gap-4">
  <div class="card">
    <h2 class="font-semibold mb-2">Configuración inteligente</h2>
    <form method="post" class="space-y-2">
      <input type="hidden" name="action" value="save_config">
      <div>
        <label class="block mb-1">Canal de YouTube LSL50</label>
        <input name="youtube_channel_url" value="<?= h($youtubeChannelUrl) ?>" class="w-full">
      </div>
      <div>
        <label class="block mb-1">Modo de publicación</label>
        <select name="publish_mode" class="w-full">
          <option value="review" <?= $publishMode === "review" ? "selected" : "" ?>>Revisar antes de publicar</option>
          <option value="auto" <?= $publishMode === "auto" ? "selected" : "" ?>>Publicar automáticamente</option>
        </select>
      </div>
      <div>
        <label class="block mb-1">Duración sugerida del clip destacado</label>
        <input name="clip_seconds" type="number" min="15" max="180" value="<?= h($clipSeconds) ?>" class="w-full">
      </div>
      <div>
        <label class="block mb-1">Estilo editorial LSL50</label>
        <textarea name="editorial_style" class="w-full" rows="3"><?= h($editorialStyle) ?></textarea>
      </div>
      <div>
        <label class="block mb-1">Modelo OpenAI</label>
        <input name="openai_model" value="<?= h($openAiModel) ?>" class="w-full" placeholder="gpt-4.1-mini">
        <div class="small mt-2">Recomendado: un modelo rápido y económico para notas de juego. Se puede cambiar luego.</div>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="block mb-1">OpenAI API Key <?= $hasOpenAiKey ? "(guardada)" : "" ?></label>
          <input name="openai_api_key" type="password" class="w-full" placeholder="<?= $hasOpenAiKey ? "••••••••" : "Pendiente" ?>">
        </div>
        <div>
          <label class="block mb-1">YouTube API Key <?= $hasYoutubeKey ? "(guardada)" : "" ?></label>
          <input name="youtube_api_key" type="password" class="w-full" placeholder="<?= $hasYoutubeKey ? "••••••••" : "Pendiente" ?>">
        </div>
      </div>
      <button class="btn-primary">Guardar configuración</button>
    </form>
    <div class="flex gap-2 mt-2" style="flex-wrap:wrap">
      <form method="post"><input type="hidden" name="action" value="test_openai"><button class="btn">Probar OpenAI</button></form>
      <form method="post"><input type="hidden" name="action" value="sync_youtube"><button class="btn">Sincronizar YouTube</button></form>
    </div>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Estado operativo</h2>
    <table class="table">
      <tbody>
        <tr><th>Datos oficiales</th><td><span class="badge badge-ok">Listo</span></td><td class="small">Usa resultados, box score, líderes y pitcher ganador.</td></tr>
        <tr><th>Notas IA</th><td><span class="badge badge-ok">Listo</span></td><td class="small">Genera borradores por juego con motor local.</td></tr>
        <tr><th>Portada</th><td><span class="badge badge-ok">Listo</span></td><td class="small">Las notas publicadas aparecen automáticamente.</td></tr>
        <tr><th>YouTube API</th><td><span class="badge <?= $hasYoutubeKey ? "badge-ok" : "badge-bad" ?>"><?= $hasYoutubeKey ? "Lista" : "Pendiente" ?></span></td><td class="small"><?= $youtubeChannelId ? "Canal ID: " . h($youtubeChannelId) . ". " : "" ?>Videos sincronizados: <?= count($recentVideos) ?>.</td></tr>
        <tr><th>OpenAI API</th><td><span class="badge <?= $hasOpenAiKey ? "badge-ok" : "badge-bad" ?>"><?= $hasOpenAiKey ? "Lista" : "Pendiente" ?></span></td><td class="small">Modelo activo: <?= h($openAiModel) ?>. Sin clave, usa redacción local basada en estadísticas.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php if ($recentVideos): ?>
  <div class="card" style="margin-top:16px">
    <h2 class="font-semibold mb-2">Videos recientes del canal</h2>
    <div class="grid md:grid-cols-2 gap-4">
      <?php foreach ($recentVideos as $video): ?>
        <div style="display:grid;grid-template-columns:96px 1fr;gap:12px;align-items:center;border:1px solid var(--line);border-radius:8px;padding:10px">
          <?php if (!empty($video["thumbnail"])): ?><img src="<?= h($video["thumbnail"]) ?>" alt="" style="width:96px;height:54px;object-fit:cover;border-radius:6px"><?php endif; ?>
          <div>
            <strong><?= h($video["title"] ?? "Video LSL50") ?></strong><br>
            <span class="small"><?= h(substr((string)($video["published_at"] ?? ""), 0, 10)) ?></span><br>
            <a class="small" href="<?= h($video["url"] ?? "#") ?>" target="_blank">Abrir video</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
  <h2 class="font-semibold mb-2">Generar nota por juego</h2>
  <table class="table">
    <thead><tr><th>Fecha</th><th>Juego</th><th>Final</th><th>Stats</th><th>Video</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($games as $g): ?>
        <tr>
          <td><?= h($g["game_date"]) ?></td>
          <td><?= h($g["home_name"]) ?> vs <?= h($g["away_name"]) ?></td>
          <td><?= (int)$g["final_home"] ?>-<?= (int)$g["final_away"] ?></td>
          <td><?= (int)$g["stat_rows"] ?> filas</td>
          <td>
            <form method="post" class="space-y-2">
              <input type="hidden" name="action" value="generate_note">
              <input type="hidden" name="game_id" value="<?= (int)$g["id"] ?>">
              <?php if ($recentVideos): ?>
                <select name="video_url" class="w-full">
                  <option value="">Sin video asignado</option>
                  <?php foreach ($recentVideos as $video): ?>
                    <option value="<?= h($video["url"] ?? "") ?>"><?= h(substr((string)($video["published_at"] ?? ""), 0, 10) . " - " . ($video["title"] ?? "Video LSL50")) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <input name="video_url" class="w-full" placeholder="URL del video YouTube">
              <?php endif; ?>
          </td>
          <td><?= h($g["note_status"] ?: "sin nota") ?></td>
          <td><button class="btn-primary">Generar borrador</button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$games): ?>
        <tr><td colspan="7" class="small">Todavía no hay juegos creados para esta temporada.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:16px">
  <h2 class="font-semibold mb-2">Borradores y publicaciones</h2>
  <table class="table">
    <thead><tr><th>Estado</th><th>Juego</th><th>Título</th><th>Resumen</th><th>Clip</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($notes as $note): ?>
        <tr>
          <td><span class="badge <?= $note["status"] === "published" ? "badge-ok" : "badge-bad" ?>"><?= h($note["status"]) ?></span></td>
          <td><?= h($note["game_date"]) ?><br><span class="small"><?= h($note["home_name"]) ?> vs <?= h($note["away_name"]) ?></span></td>
          <td><strong><?= h($note["title"]) ?></strong><br><span class="small"><?= h($note["provider"]) ?></span></td>
          <td><?= nl2br(h($note["summary"])) ?></td>
          <td class="small"><?= h($note["highlight_reason"]) ?><br><?= h($note["video_url"] ?: "sin video") ?></td>
          <td>
            <?php if ($note["status"] === "published"): ?>
              <form method="post"><input type="hidden" name="action" value="unpublish_note"><input type="hidden" name="id" value="<?= (int)$note["id"] ?>"><button class="btn">Retirar</button></form>
            <?php else: ?>
              <form method="post" style="margin-bottom:6px"><input type="hidden" name="action" value="publish_note"><input type="hidden" name="id" value="<?= (int)$note["id"] ?>"><button class="btn-primary">Publicar</button></form>
              <form method="post"><input type="hidden" name="action" value="delete_note"><input type="hidden" name="id" value="<?= (int)$note["id"] ?>"><button class="btn" onclick="return confirm('Eliminar borrador?')">Eliminar</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$notes): ?>
        <tr><td colspan="6" class="small">Cuando haya un juego anotado, genera aquí la primera nota IA.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>
