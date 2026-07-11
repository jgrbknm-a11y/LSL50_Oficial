<?php
/**
 * AI Sports Writer — Legends Softball League 50+
 *
 * Genera crónicas automáticas al cerrar un juego en el cuaderno.
 * Guarda en ai_game_notes (MySQL/SQLite) con embed de YouTube.
 *
 * CLI:
 *   php admin/services/ai_news_generator.php --game-id=5
 *   php admin/services/ai_news_generator.php --game-id=5 --publish
 *
 * Programático:
 *   lsl_ai_generate_for_game($pdo, $seasonId, $gameId, $autoPublish);
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/config.php";
require_once dirname(__DIR__, 2) . "/src/autoload.php";

use Lsl50\Services\AiNewsGenerator;
use Lsl50\Services\StatsEngine;
use Lsl50\Support\YoutubeHelper;

function lsl_ai_build_sports_prompt(array $context, string $style, int $clipSeconds): string
{
  $game = $context["game"];
  $box = $context["box"] ?? ["teams" => [], "mvp" => null];
  $leaders = $context["leaders"] ?? [];
  $plays = $context["plays"] ?? [];
  $videoUrl = $context["video_url"] ?? "";

  $payload = [
    "liga" => "Legends Softball League 50+ (LSL50)",
    "ubicacion" => "Broward County, Florida",
    "partido" => [
      "fecha" => $game["game_date"] ?? "",
      "visitante" => $game["away_name"] ?? "",
      "local" => $game["home_name"] ?? "",
      "marcador_visitante" => (int)($game["final_away"] ?? 0),
      "marcador_local" => (int)($game["final_home"] ?? 0),
      "campo" => $game["location"] ?? "Campo Principal",
      "pitcher_ganador" => $game["winning_pitcher_name"] ?? null,
      "video_youtube" => $videoUrl !== "" ? $videoUrl : null,
    ],
    "totales_por_equipo" => $box["teams"] ?? [],
    "mvp_sugerido" => $box["mvp"] ?? null,
    "lideres_ofensivos_juego" => $leaders,
    "jugadas_clave" => array_slice($plays, 0, 25),
  ];

  $instructions = <<<PROMPT
Eres el redactor deportivo oficial de Legends Softball League 50+ (softball +50, Broward, FL).

MISIÓN: Redactar una crónica periodística PREMIUM en español, emocionante y precisa, como ESPN o MLB.com en tono local.

DATOS OFICIALES (NO INVENTAR):
- Usa únicamente el JSON adjunto del cuaderno de anotación.
- Marcador, hits, errores, RBI, HR y pitcher ganador deben coincidir con los números provistos.
- Identifica al equipo ganador y celebra su triunfo con narrativa vibrante pero veraz.
- Destaca al MVP del partido (mvp_sugerido) y a los lideres_ofensivos_juego.
- Si hay video_youtube, menciona que la transmisión está disponible en la crónica multimedia.

ESTILO EDITORIAL: {$style}

ESTRUCTURA REQUERIDA (JSON de salida):
{
  "title": "Titular periodístico atractivo (máx 120 caracteres)",
  "summary": "Lead de 1-2 frases con el resultado y la figura del juego",
  "body": "3 a 5 párrafos en español. Párrafo 1: contexto y marcador. Párrafo 2: desarrollo y momentos clave. Párrafo 3: figuras (MVP, pitcher ganador). Párrafo 4: impacto en la temporada/playoffs si aplica. Cierre emocional LSL50.",
  "clip_start_seconds": 0,
  "clip_end_seconds": {$clipSeconds},
  "highlight_reason": "Descripción del momento a destacar en video"
}

REGLAS:
- NO inventes jugadas, jugadores ni estadísticas ausentes en el JSON.
- NO uses markdown en body; solo texto plano con párrafos separados por \\n\\n.
- Devuelve ÚNICAMENTE JSON válido, sin bloques ``` ni texto extra.

DATOS DEL PARTIDO:
PROMPT;

  return $instructions . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

function lsl_ai_generate_for_game(PDO $pdo, int $seasonId, int $gameId, bool $autoPublish = false): array
{
  $context = AiNewsGenerator::gameContext($pdo, $gameId, $seasonId);
  if (!$context) {
    return ["ok" => false, "error" => "Juego no encontrado."];
  }

  $context["box"] = StatsEngine::gameBoxSummary($pdo, $gameId);
  $game = $context["game"];
  $videoId = trim((string)($game["youtube_video_id"] ?? ""));
  $context["video_url"] = $videoId !== "" ? (YoutubeHelper::watchUrl($videoId) ?? "") : "";

  $clipSeconds = max(15, (int)lsl_setting($pdo, "ai_clip_seconds", "90"));
  $style = lsl_setting($pdo, "ai_editorial_style", "profesional, emocionante, periodismo deportivo en español, voz de liga premium");
  $openAiKey = lsl_setting($pdo, "openai_api_key", "");
  $model = lsl_setting($pdo, "openai_model", "gpt-4.1-mini");

  $provider = "local-stats";
  try {
    if ($openAiKey !== "") {
      $prompt = lsl_ai_build_sports_prompt($context, $style, $clipSeconds);
      $note = lsl_ai_call_openai($openAiKey, $model, $prompt, $clipSeconds);
      $provider = "openai";
    } else {
      $note = lsl_ai_build_local_note($context, $clipSeconds);
    }
  } catch (Throwable $e) {
    $note = lsl_ai_build_local_note($context, $clipSeconds);
    $provider = "local-stats";
  }

  $status = ($autoPublish || lsl_setting($pdo, "ai_publish_mode", "review") === "auto") ? "published" : "draft";
  $publishedAt = $status === "published" ? date("Y-m-d H:i:s") : null;

  SqlDialect::upsertAiGameNote(
    $pdo,
    $seasonId,
    $gameId,
    $status,
    $note["title"],
    $note["summary"],
    $note["body"],
    $context["video_url"],
    (int)$note["clip_start_seconds"],
    (int)$note["clip_end_seconds"],
    $note["highlight_reason"],
    $provider,
    $publishedAt
  );

  return [
    "ok" => true,
    "provider" => $provider,
    "status" => $status,
    "title" => $note["title"],
    "video_url" => $context["video_url"],
    "game_id" => $gameId,
  ];
}

function lsl_ai_build_local_note(array $context, int $clipSeconds): array
{
  $game = $context["game"];
  $box = $context["box"] ?? ["teams" => [], "mvp" => null];
  $mvp = $box["mvp"] ?? null;
  $home = $game["home_name"];
  $away = $game["away_name"];
  $homeRuns = (int)$game["final_home"];
  $awayRuns = (int)$game["final_away"];
  $winner = $homeRuns === $awayRuns ? "Empate" : ($homeRuns > $awayRuns ? $home : $away);
  $loser = $homeRuns === $awayRuns ? "" : ($homeRuns > $awayRuns ? $away : $home);

  $title = $homeRuns === $awayRuns
    ? "{$home} y {$away} firman tablas en la LSL50"
    : "{$winner} se impone {$homeRuns}-{$awayRuns} sobre {$loser} en la LSL50";

  $summary = $mvp
    ? "Figura del juego: {$mvp["player_name"]} ({$mvp["team_name"]}) con " . (int)$mvp["H"] . " hits y " . (int)$mvp["RBI"] . " impulsadas."
    : "Crónica oficial generada desde el cuaderno digital LSL50.";

  $body = [];
  $body[] = "En partido válido por la Legends Softball League 50+, el marcador final fue {$away} {$awayRuns}, {$home} {$homeRuns}.";
  if ($homeRuns !== $awayRuns) {
    $body[] = "{$winner} capitalizó sus oportunidades ofensivas para llevarse la victoria ante {$loser}, en un duelo disputado de la Temporada Primavera.";
  }
  foreach ($box["teams"] ?? [] as $teamRow) {
    $body[] = "{$teamRow["team_name"]}: {$teamRow["runs"]} carreras, {$teamRow["hits"]} hits, {$teamRow["errors"]} errores.";
  }
  if (!empty($game["winning_pitcher_name"])) {
    $body[] = "El pitcher ganador registrado fue {$game["winning_pitcher_name"]}, pieza clave en el cierre del encuentro.";
  }
  if ($mvp) {
    $body[] = "El MVP del partido fue {$mvp["player_name"]} ({$mvp["team_name"]}), líder ofensivo con " . (int)$mvp["H"] . " hits, " . (int)$mvp["RBI"] . " RBI y " . (int)$mvp["R"] . " anotadas.";
  }
  $body[] = "Datos verificados en el cuaderno oficial LSL50. Consulta la crónica multimedia con video del partido en legendssoftball50.com.";

  return [
    "title" => $title,
    "summary" => $summary,
    "body" => implode("\n\n", $body),
    "clip_start_seconds" => 0,
    "clip_end_seconds" => $clipSeconds,
    "highlight_reason" => $mvp ? "Turno decisivo de {$mvp["player_name"]}" : "Momento clave del cierre",
  ];
}

function lsl_ai_call_openai(string $apiKey, string $model, string $prompt, int $clipSeconds): array
{
  $payload = [
    "model" => $model ?: "gpt-4.1-mini",
    "input" => [
      ["role" => "system", "content" => "Eres un editor deportivo senior. Respondes exclusivamente con JSON válido UTF-8."],
      ["role" => "user", "content" => $prompt],
    ],
  ];
  $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
  $ch = curl_init("https://api.openai.com/v1/responses");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      "Accept: application/json",
      "Content-Type: application/json",
      "Authorization: Bearer " . $apiKey,
    ],
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_TIMEOUT => 45,
  ]);
  $raw = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  if ($raw === false || $status < 200 || $status >= 300) {
    throw new RuntimeException("OpenAI API error HTTP {$status}");
  }
  $data = json_decode($raw, true);
  $text = "";
  if (isset($data["output_text"]) && is_string($data["output_text"])) {
    $text = trim($data["output_text"]);
  } else {
    foreach (($data["output"] ?? []) as $item) {
      foreach (($item["content"] ?? []) as $content) {
        if (($content["type"] ?? "") === "output_text" && isset($content["text"])) {
          $text .= $content["text"];
        }
      }
    }
    $text = trim($text);
  }
  $text = preg_replace('/^```json\s*|\s*```$/', "", $text);
  $note = json_decode($text, true);
  if (!is_array($note)) {
    throw new RuntimeException("OpenAI devolvió JSON inválido.");
  }
  return [
    "title" => trim((string)($note["title"] ?? "")) ?: "Crónica LSL50",
    "summary" => trim((string)($note["summary"] ?? "")),
    "body" => trim((string)($note["body"] ?? "")),
    "clip_start_seconds" => max(0, (int)($note["clip_start_seconds"] ?? 0)),
    "clip_end_seconds" => max(15, min(3600, (int)($note["clip_end_seconds"] ?? $clipSeconds))),
    "highlight_reason" => trim((string)($note["highlight_reason"] ?? "")) ?: "Highlight oficial LSL50",
  ];
}

if (PHP_SAPI === "cli" && realpath($argv[0] ?? "") === __FILE__) {
  $gameId = 0;
  $publish = false;
  foreach ($argv as $arg) {
    if (str_starts_with($arg, "--game-id=")) {
      $gameId = (int)substr($arg, 10);
    }
    if ($arg === "--publish") {
      $publish = true;
    }
  }
  if ($gameId <= 0) {
    fwrite(STDERR, "Uso: php admin/services/ai_news_generator.php --game-id=N [--publish]\n");
    exit(1);
  }
  $pdo = db();
  $season = active_season($pdo);
  $result = lsl_ai_generate_for_game($pdo, (int)$season["id"], $gameId, $publish);
  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
  exit($result["ok"] ? 0 : 1);
}
