<?php
require __DIR__ . "/../config.php";
require_admin();

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];

function ai_fmt_stat($value, string $type = "int"): string {
  return $type === "avg" ? number_format((float)$value, 3) : (string)(int)$value;
}

function ai_http_json(string $method, string $url, array $headers = [], ?array $payload = null): array {
  $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $headerLines = array_merge(["Accept: application/json"], $headers);
  if ($body !== null) $headerLines[] = "Content-Type: application/json";

  if (function_exists("curl_init")) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headerLines,
      CURLOPT_TIMEOUT => 20,
      CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false) return ["ok" => false, "status" => 0, "error" => $error ?: "No se pudo conectar."];
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
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $status = (int)$m[1];
    if ($raw === false) return ["ok" => false, "status" => $status, "error" => "No se pudo conectar."];
  }

  $data = json_decode($raw, true);
  if (!is_array($data)) return ["ok" => false, "status" => $status, "error" => "Respuesta inválida.", "raw" => $raw];
  if ($status < 200 || $status >= 300) {
    $message = $data["error"]["message"] ?? $data["error"]["errors"][0]["message"] ?? $data["error"]["message"] ?? "Error API.";
    return ["ok" => false, "status" => $status, "error" => $message, "data" => $data];
  }
  return ["ok" => true, "status" => $status, "data" => $data];
}

function ai_parse_youtube_handle(string $channelUrl): string {
  if (preg_match('~@([A-Za-z0-9._-]+)~', $channelUrl, $m)) return "@" . $m[1];
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
  if (!$channelResponse["ok"]) return $channelResponse + ["videos" => []];
  $items = $channelResponse["data"]["items"] ?? [];
  if (!$items) return ["ok" => false, "status" => 404, "error" => "No se encontró el canal con ese handle.", "videos" => []];
  $channelId = $items[0]["id"] ?? "";
  if ($channelId === "") return ["ok" => false, "status" => 404, "error" => "El canal no devolvió ID.", "videos" => []];

  $searchApi = "https://www.googleapis.com/youtube/v3/search?" . http_build_query([
    "part" => "snippet",
    "channelId" => $channelId,
    "maxResults" => max(1, min(20, $limit)),
    "order" => "date",
    "type" => "video",
    "key" => $apiKey,
  ]);
  $videoResponse = ai_http_json("GET", $searchApi);
  if (!$videoResponse["ok"]) return $videoResponse + ["videos" => []];
  $videos = [];
  foreach (($videoResponse["data"]["items"] ?? []) as $item) {
    $videoId = $item["id"]["videoId"] ?? "";
    if ($videoId === "") continue;
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

function ai_openai_text_from_response(array $data): string {
  if (isset($data["output_text"]) && is_string($data["output_text"])) return trim($data["output_text"]);
  $chunks = [];
  foreach (($data["output"] ?? []) as $item) {
    foreach (($item["content"] ?? []) as $content) {
      if (($content["type"] ?? "") === "output_text" && isset($content["text"])) $chunks[] = $content["text"];
    }
  }
  return trim(implode("\n", $chunks));
}

function ai_game_context(PDO $pdo, int $gameId, int $seasonId): ?array {
  $stmt = $pdo->prepare("SELECT g.*, ht.name home_name, at.name away_name,
      wp.first_name || ' ' || wp.last_name winning_pitcher_name
    FROM games g
    JOIN teams ht ON ht.id=g.home_team_id
    JOIN teams at ON at.id=g.away_team_id
    LEFT JOIN players wp ON wp.id=g.winning_pitcher_id
    WHERE g.id=? AND COALESCE(g.season_id, $seasonId) = $seasonId");
  $stmt->execute([$gameId]);
  $game = $stmt->fetch();
  if (!$game) return null;

  $leaders = $pdo->prepare("SELECT p.first_name || ' ' || p.last_name player_name, p.number, t.name team_name,
      gps.AB, MAX(gps.H, gps.dbl + gps.tpl + gps.HR) H, gps.dbl, gps.tpl, gps.R, gps.RBI, gps.HR, gps.BB, gps.SO, gps.SB, gps.HBP, gps.SH, gps.SF, gps.E,
      ((MAX(gps.H, gps.dbl + gps.tpl + gps.HR) - (gps.dbl + gps.tpl + gps.HR)) + gps.dbl*2 + gps.tpl*3 + gps.HR*4) TB
    FROM game_player_stats gps
    JOIN players p ON p.id=gps.player_id
    JOIN teams t ON t.id=gps.team_id
    WHERE gps.game_id=?
    ORDER BY gps.HR DESC, gps.RBI DESC, MAX(gps.H, gps.dbl + gps.tpl + gps.HR) DESC, TB DESC, gps.R DESC, p.last_name, p.first_name
    LIMIT 5");
  $leaders->execute([$gameId]);

  $teamRows = $pdo->prepare("SELECT t.name team_name, SUM(gps.R) runs, SUM(MAX(gps.H, gps.dbl + gps.tpl + gps.HR)) hits, SUM(gps.E) errors, SUM(gps.HR) hrs
    FROM game_player_stats gps
    JOIN teams t ON t.id=gps.team_id
    WHERE gps.game_id=?
    GROUP BY t.id, t.name
    ORDER BY t.name");
  $teamRows->execute([$gameId]);

  $plays = $pdo->prepare("SELECT e.*, bt.name batting_team, b.first_name || ' ' || b.last_name batter_name,
      r1.first_name || ' ' || r1.last_name runner_1b_name,
      r2.first_name || ' ' || r2.last_name runner_2b_name,
      r3.first_name || ' ' || r3.last_name runner_3b_name
    FROM game_play_events e
    JOIN teams bt ON bt.id=e.batting_team_id
    JOIN players b ON b.id=e.batter_id
    LEFT JOIN players r1 ON r1.id=e.runner_1b_id
    LEFT JOIN players r2 ON r2.id=e.runner_2b_id
    LEFT JOIN players r3 ON r3.id=e.runner_3b_id
    WHERE e.game_id=?
    ORDER BY e.inning, CASE e.half WHEN 'top' THEN 0 ELSE 1 END, e.id
    LIMIT 80");
  $plays->execute([$gameId]);

  return [
    "game" => $game,
    "leaders" => $leaders->fetchAll(),
    "teams" => $teamRows->fetchAll(),
    "plays" => $plays->fetchAll(),
  ];
}

function ai_build_openai_note(array $context, int $clipSeconds, string $apiKey, string $model, string $style, string $videoUrl = "", array $videoMeta = []): array {
  $game = $context["game"];
  $payload = [
    "game" => [
      "home" => $game["home_name"],
      "away" => $game["away_name"],
      "final_home" => (int)$game["final_home"],
      "final_away" => (int)$game["final_away"],
      "date" => $game["game_date"],
      "winning_pitcher" => $game["winning_pitcher_name"] ?: null,
    ],
    "team_totals" => $context["teams"],
    "leaders" => $context["leaders"],
    "play_by_play" => $context["plays"] ?? [],
    "video" => [
      "url" => $videoUrl,
      "title" => $videoMeta["title"] ?? "",
      "description" => $videoMeta["description"] ?? "",
    ],
  ];
  $prompt = "Eres el publicador oficial de Legends Softball League 50+ en Broward, Florida.\n"
    . "Redacta una nota deportiva profesional en español usando solamente estos datos oficiales.\n"
    . "Estilo editorial: $style\n"
    . "Devuelve solo JSON válido con estas claves: title, summary, body, clip_start_seconds, clip_end_seconds, highlight_reason.\n"
    . "El body debe tener 3 a 5 párrafos breves. No inventes jugadas que no estén en los datos.\n"
    . "Si hay video, sugiere un clip de máximo $clipSeconds segundos basado en la figura estadística o el momento más probable.\n\n"
    . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  $response = ai_http_json("POST", "https://api.openai.com/v1/responses", [
    "Authorization: Bearer " . $apiKey,
  ], [
    "model" => $model ?: "gpt-4.1-mini",
    "input" => [
      [
        "role" => "system",
        "content" => "Eres un editor deportivo cuidadoso. Respondes en JSON válido y no inventas datos.",
      ],
      [
        "role" => "user",
        "content" => $prompt,
      ],
    ],
  ]);
  if (!$response["ok"]) throw new RuntimeException($response["error"] ?? "OpenAI no respondió.");
  $text = ai_openai_text_from_response($response["data"]);
  $text = preg_replace('/^```json\s*|\s*```$/', "", trim($text));
  $note = json_decode($text, true);
  if (!is_array($note)) throw new RuntimeException("OpenAI respondió, pero el JSON no se pudo leer.");
  return [
    "title" => trim((string)($note["title"] ?? "")) ?: "Nota LSL50",
    "summary" => trim((string)($note["summary"] ?? "")),
    "body" => trim((string)($note["body"] ?? "")),
    "clip_start_seconds" => max(0, (int)($note["clip_start_seconds"] ?? 0)),
    "clip_end_seconds" => max(15, min(3600, (int)($note["clip_end_seconds"] ?? $clipSeconds))),
    "highlight_reason" => trim((string)($note["highlight_reason"] ?? "")) ?: "Clip sugerido por impacto estadístico del juego.",
  ];
}

function ai_build_local_note(array $context, int $clipSeconds): array {
  $game = $context["game"];
  $home = $game["home_name"];
  $away = $game["away_name"];
  $homeRuns = (int)$game["final_home"];
  $awayRuns = (int)$game["final_away"];
  $winner = $homeRuns === $awayRuns ? "Empate" : ($homeRuns > $awayRuns ? $home : $away);
  $loser = $homeRuns === $awayRuns ? "" : ($homeRuns > $awayRuns ? $away : $home);
  $score = "$home $homeRuns, $away $awayRuns";
  $title = $homeRuns === $awayRuns
    ? "$home y $away terminan empatados en la jornada LSL50"
    : "$winner supera a $loser en la jornada LSL50";

  $leaders = $context["leaders"];
  $top = $leaders[0] ?? null;
  $topLine = "";
  if ($top) {
    $parts = [];
    if ((int)$top["H"] > 0) $parts[] = (int)$top["H"] . " hits";
    if ((int)$top["RBI"] > 0) $parts[] = (int)$top["RBI"] . " impulsadas";
    if ((int)$top["R"] > 0) $parts[] = (int)$top["R"] . " anotadas";
    if ((int)$top["HR"] > 0) $parts[] = (int)$top["HR"] . " jonrones";
    $topLine = $parts ? implode(", ", $parts) : "aporte clave en el partido";
  }
  $summary = $top
    ? "La jornada dejó como figura a " . $top["player_name"] . " de " . $top["team_name"] . ", con " . $topLine . "."
    : "La jornada quedó registrada en el cuaderno oficial y actualizó automáticamente las posiciones y líderes de la liga.";

  $body = [];
  $body[] = "Resultado final: $score.";
  if ($homeRuns !== $awayRuns) {
    $body[] = "$winner logró imponer su ofensiva y cerró el juego con ventaja sobre $loser.";
  } else {
    $body[] = "Ambos equipos sostuvieron un partido parejo que terminó sin ganador.";
  }
  if ($game["winning_pitcher_name"]) {
    $body[] = "El pitcher ganador registrado fue " . $game["winning_pitcher_name"] . ".";
  }
  if ($leaders) {
    $body[] = "Jugadores destacados:";
    foreach ($leaders as $row) {
      $body[] = "- " . ($row["number"] ? "#" . $row["number"] . " " : "") . $row["player_name"] . " (" . $row["team_name"] . "): " . (int)$row["H"] . " H, " . (int)$row["RBI"] . " RBI, " . (int)$row["R"] . " R, " . (int)$row["HR"] . " HR.";
    }
  }
  if (!empty($context["plays"])) {
    $body[] = "El cuaderno jugada por jugada incluye avances de corredores para apoyar la crónica del partido.";
  }
  $body[] = "Esta nota fue preparada a partir de las estadísticas oficiales capturadas en el cuaderno de anotación LSL50.";

  return [
    "title" => $title,
    "summary" => $summary,
    "body" => implode("\n\n", $body),
    "clip_start_seconds" => 0,
    "clip_end_seconds" => $clipSeconds,
    "highlight_reason" => $top
      ? "Clip sugerido: buscar el turno ofensivo más importante de " . $top["player_name"] . ", por ser el jugador de mayor impacto estadístico del juego."
      : "Clip sugerido: seleccionar la jugada decisiva del cierre del partido.",
  ];
}

function ai_upsert_note(PDO $pdo, int $seasonId, int $gameId, array $note, string $videoUrl, string $provider): void {
  $stmt = $pdo->prepare("INSERT INTO ai_game_notes
      (season_id, game_id, status, title, summary, body, video_url, clip_start_seconds, clip_end_seconds, highlight_reason, provider, updated_at)
    VALUES (?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ON CONFLICT(game_id) DO UPDATE SET
      status='draft',
      title=excluded.title,
      summary=excluded.summary,
      body=excluded.body,
      video_url=excluded.video_url,
      clip_start_seconds=excluded.clip_start_seconds,
      clip_end_seconds=excluded.clip_end_seconds,
      highlight_reason=excluded.highlight_reason,
      provider=excluded.provider,
      updated_at=CURRENT_TIMESTAMP");
  $stmt->execute([
    $seasonId,
    $gameId,
    $note["title"],
    $note["summary"],
    $note["body"],
    $videoUrl,
    (int)$note["clip_start_seconds"],
    (int)$note["clip_end_seconds"],
    $note["highlight_reason"],
    $provider,
  ]);
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
    $context = ai_game_context($pdo, $gameId, $seasonId);
    if (!$context) {
      flash("No se encontró el juego.");
    } elseif (!$context["leaders"]) {
      flash("Ese juego todavía no tiene estadísticas guardadas en el cuaderno.");
    } else {
      $clipSeconds = (int)lsl_setting($pdo, "ai_clip_seconds", "45");
      $videoUrl = trim(post("video_url"));
      $provider = "local-stats";
      $note = null;
      $openAiKey = lsl_setting($pdo, "openai_api_key", "");
      if ($openAiKey !== "") {
        try {
          $recentVideos = json_decode(lsl_setting($pdo, "ai_youtube_recent_videos", "[]"), true) ?: [];
          $videoMeta = [];
          foreach ($recentVideos as $video) {
            if (($video["url"] ?? "") === $videoUrl) $videoMeta = $video;
          }
          $note = ai_build_openai_note(
            $context,
            $clipSeconds,
            $openAiKey,
            lsl_setting($pdo, "openai_model", "gpt-4.1-mini"),
            lsl_setting($pdo, "ai_editorial_style", "Profesional deportivo, claro, positivo y breve."),
            $videoUrl,
            $videoMeta
          );
          $provider = "openai";
        } catch (Throwable $e) {
          flash("OpenAI no pudo generar la nota; se usó redacción local. Detalle: " . $e->getMessage());
        }
      }
      if (!$note) $note = ai_build_local_note($context, $clipSeconds);
      ai_upsert_note($pdo, $seasonId, $gameId, $note, $videoUrl, $provider);
      flash($provider === "openai" ? "Borrador generado con OpenAI y estadísticas oficiales." : "Borrador generado con estadísticas oficiales.");
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
