<?php

namespace Lsl50\Services;

use Lsl50\Support\YoutubeHelper;
use PDO;
use RuntimeException;
use SqlDialect;
use Throwable;

/**
 * Servicio unificado AI Sports Writer LSL50.
 * Consumido por: GameClosurePipeline, admin/ai-publisher.php, CLI.
 */
final class AiNewsGenerator
{
  /**
   * @param array{
   *   auto_publish?: bool,
   *   video_url?: string|null,
   *   video_meta?: array<string, mixed>,
   *   status?: 'draft'|'published'|null,
   *   require_stats?: bool
   * } $options
   */
  public static function generateForGame(PDO $pdo, int $seasonId, int $gameId, array $options = []): array
  {
    $autoPublish = !empty($options["auto_publish"]);
    $requireStats = !empty($options["require_stats"]);
    $videoUrlOverride = isset($options["video_url"]) ? trim((string)$options["video_url"]) : null;
    $videoMeta = is_array($options["video_meta"] ?? null) ? $options["video_meta"] : [];
    $forcedStatus = $options["status"] ?? null;

    $context = self::buildContext($pdo, $gameId, $seasonId, $videoUrlOverride, $videoMeta);
    if (!$context) {
      return ["ok" => false, "error" => "Juego no encontrado."];
    }

    if ($requireStats && empty($context["leaders"])) {
      return ["ok" => false, "error" => "El juego no tiene estadísticas guardadas en el cuaderno."];
    }

    $clipSeconds = max(15, (int)lsl_setting($pdo, "ai_clip_seconds", "90"));
    $style = lsl_setting(
      $pdo,
      "ai_editorial_style",
      "profesional, emocionante, periodismo deportivo en español, voz de liga premium"
    );
    $openAiKey = trim(lsl_setting($pdo, "openai_api_key", ""));
    $model = lsl_setting($pdo, "openai_model", "gpt-4.1-mini");

    $provider = "local-stats";
    try {
      if ($openAiKey !== "") {
        $prompt = self::buildSportsPrompt($context, $style, $clipSeconds);
        $note = self::callOpenAi($openAiKey, $model, $prompt, $clipSeconds);
        $provider = "openai";
      } else {
        $note = self::buildLocalNote($context, $clipSeconds);
      }
    } catch (Throwable $e) {
      $note = self::buildLocalNote($context, $clipSeconds);
      $provider = "local-stats";
    }

    $status = self::resolveStatus($pdo, $autoPublish, $forcedStatus);
    self::upsertNote($pdo, $seasonId, $gameId, $note, (string)$context["video_url"], $provider, $status);

    return [
      "ok" => true,
      "provider" => $provider,
      "status" => $status,
      "title" => $note["title"],
      "video_url" => $context["video_url"],
      "game_id" => $gameId,
    ];
  }

  /** @param array<string, mixed> $videoMeta */
  public static function buildContext(
    PDO $pdo,
    int $gameId,
    int $seasonId,
    ?string $videoUrlOverride = null,
    array $videoMeta = []
  ): ?array {
    $base = self::gameContext($pdo, $gameId, $seasonId);
    if (!$base) {
      return null;
    }

    $game = $base["game"];
    $videoUrl = $videoUrlOverride ?? self::resolveVideoUrl($game);
    if ($videoUrl === "" && !empty($videoMeta["url"])) {
      $videoUrl = trim((string)$videoMeta["url"]);
    }

    return [
      "game" => $game,
      "leaders" => $base["leaders"],
      "plays" => $base["plays"],
      "box" => StatsEngine::gameBoxSummary($pdo, $gameId),
      "video_url" => $videoUrl,
      "video_meta" => $videoMeta,
    ];
  }

  public static function gameContext(PDO $pdo, int $gameId, int $seasonId): ?array
  {
    $seasonId = (int)$seasonId;
    $stmt = $pdo->prepare("SELECT g.*, ht.name home_name, at.name away_name,
        " . lsl_sql_full_name("wp") . " winning_pitcher_name
      FROM games g
      JOIN teams ht ON ht.id = g.home_team_id
      JOIN teams at ON at.id = g.away_team_id
      LEFT JOIN players wp ON wp.id = g.winning_pitcher_id
      WHERE g.id = ? AND COALESCE(g.season_id, $seasonId) = $seasonId");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    if (!$game) {
      return null;
    }

    $hitExpr = SqlDialect::greatest("gps.H", "gps.dbl + gps.tpl + gps.HR");
    $leaders = $pdo->prepare("SELECT p.id player_id, p.number, " . lsl_sql_full_name("p") . " player_name, t.name team_name,
        gps.AB, {$hitExpr} H, gps.dbl, gps.tpl, gps.R, gps.RBI, gps.HR, gps.BB, gps.SO, gps.SB,
        (({$hitExpr} - (gps.dbl + gps.tpl + gps.HR)) + gps.dbl*2 + gps.tpl*3 + gps.HR*4) TB
      FROM game_player_stats gps
      JOIN players p ON p.id = gps.player_id
      JOIN teams t ON t.id = gps.team_id
      WHERE gps.game_id = ?
      ORDER BY gps.HR DESC, gps.RBI DESC, {$hitExpr} DESC, TB DESC, gps.R DESC, p.last_name, p.first_name
      LIMIT 5");
    $leaders->execute([$gameId]);

    $plays = $pdo->prepare("SELECT e.inning, e.half, e.result, e.rbi, e.runs_scored, e.out_detail,
        bt.name batting_team, " . lsl_sql_full_name("b") . " batter_name
      FROM game_play_events e
      JOIN teams bt ON bt.id = e.batting_team_id
      JOIN players b ON b.id = e.batter_id
      WHERE e.game_id = ?
      ORDER BY e.inning, CASE e.half WHEN 'top' THEN 0 ELSE 1 END, e.id
      LIMIT 40");
    $plays->execute([$gameId]);

    return [
      "game" => $game,
      "leaders" => $leaders->fetchAll(),
      "plays" => $plays->fetchAll(),
    ];
  }

  public static function buildSportsPrompt(array $context, string $style, int $clipSeconds): string
  {
    $game = $context["game"];
    $box = $context["box"] ?? ["teams" => [], "mvp" => null];
    $leaders = $context["leaders"] ?? [];
    $plays = $context["plays"] ?? [];
    $videoUrl = (string)($context["video_url"] ?? "");
    $videoMeta = $context["video_meta"] ?? [];

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
        "video_titulo" => $videoMeta["title"] ?? null,
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

  public static function buildLocalNote(array $context, int $clipSeconds): array
  {
    $game = $context["game"];
    $box = $context["box"] ?? ["teams" => [], "mvp" => null];
    $mvp = $box["mvp"] ?? null;
    $home = (string)$game["home_name"];
    $away = (string)$game["away_name"];
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
      $body[] = "{$winner} capitalizó sus oportunidades ofensivas para llevarse la victoria ante {$loser}, en un duelo disputado de la temporada.";
    }
    foreach ($box["teams"] ?? [] as $teamRow) {
      $body[] = "{$teamRow["team_name"]}: {$teamRow["runs"]} carreras, {$teamRow["hits"]} hits, {$teamRow["errors"]} errores.";
    }
    if (!empty($game["winning_pitcher_name"])) {
      $body[] = "El pitcher ganador registrado fue {$game["winning_pitcher_name"]}, pieza clave en el cierre del encuentro.";
    }
    if ($mvp) {
      $body[] = "El MVP del partido fue {$mvp["player_name"]} ({$mvp["team_name"]}), líder ofensivo con "
        . (int)$mvp["H"] . " hits, " . (int)$mvp["RBI"] . " RBI y " . (int)$mvp["R"] . " anotadas.";
    }
    $body[] = "Datos verificados en el cuaderno oficial LSL50. Consulta la crónica multimedia en legendssoftball50.com.";

    return [
      "title" => $title,
      "summary" => $summary,
      "body" => implode("\n\n", $body),
      "clip_start_seconds" => 0,
      "clip_end_seconds" => $clipSeconds,
      "highlight_reason" => $mvp
        ? "Turno decisivo de {$mvp["player_name"]}"
        : "Momento clave del cierre",
    ];
  }

  public static function callOpenAi(string $apiKey, string $model, string $prompt, int $clipSeconds): array
  {
    $response = self::httpJson("POST", "https://api.openai.com/v1/responses", [
      "Authorization: Bearer " . $apiKey,
    ], [
      "model" => $model ?: "gpt-4.1-mini",
      "input" => [
        ["role" => "system", "content" => "Eres un editor deportivo senior. Respondes exclusivamente con JSON válido UTF-8."],
        ["role" => "user", "content" => $prompt],
      ],
    ]);
    if (!$response["ok"]) {
      throw new RuntimeException($response["error"] ?? "OpenAI API error");
    }

    $text = self::openAiText($response["data"]);
    $text = preg_replace('/^```json\s*|\s*```$/', "", trim($text));
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

  private static function resolveStatus(PDO $pdo, bool $autoPublish, ?string $forcedStatus): string
  {
    if ($forcedStatus === "draft" || $forcedStatus === "published") {
      return $forcedStatus;
    }
    if ($autoPublish || lsl_setting($pdo, "ai_publish_mode", "review") === "auto") {
      return "published";
    }
    return "draft";
  }

  private static function resolveVideoUrl(array $game): string
  {
    $id = trim((string)($game["youtube_video_id"] ?? ""));
    if ($id === "") {
      return "";
    }
    return YoutubeHelper::watchUrl($id) ?? "";
  }

  private static function upsertNote(
    PDO $pdo,
    int $seasonId,
    int $gameId,
    array $note,
    string $videoUrl,
    string $provider,
    string $status
  ): void {
    $publishedAt = $status === "published" ? date("Y-m-d H:i:s") : null;
    SqlDialect::upsertAiGameNote(
      $pdo,
      $seasonId,
      $gameId,
      $status,
      $note["title"],
      $note["summary"],
      $note["body"],
      $videoUrl,
      (int)$note["clip_start_seconds"],
      (int)$note["clip_end_seconds"],
      $note["highlight_reason"],
      $provider,
      $publishedAt
    );
  }

  private static function openAiText(array $data): string
  {
    if (isset($data["output_text"]) && is_string($data["output_text"])) {
      return trim($data["output_text"]);
    }
    $chunks = [];
    foreach (($data["output"] ?? []) as $item) {
      foreach (($item["content"] ?? []) as $content) {
        if (($content["type"] ?? "") === "output_text" && isset($content["text"])) {
          $chunks[] = $content["text"];
        }
      }
    }
    return trim(implode("\n", $chunks));
  }

  /** @return array{ok:bool,data?:array,error?:string} */
  private static function httpJson(string $method, string $url, array $headers = [], ?array $payload = null): array
  {
    $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);
    $headerLines = array_merge(["Accept: application/json"], $headers);
    if ($body !== null) {
      $headerLines[] = "Content-Type: application/json";
    }
    if (!function_exists("curl_init")) {
      return ["ok" => false, "error" => "cURL no disponible"];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headerLines,
      CURLOPT_TIMEOUT => 45,
    ]);
    if ($body !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
      return ["ok" => false, "error" => "Conexión fallida"];
    }

    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300) {
      $message = is_array($data) ? ($data["error"]["message"] ?? "API error {$status}") : "API error {$status}";
      return ["ok" => false, "error" => $message];
    }
    return ["ok" => true, "data" => is_array($data) ? $data : []];
  }
}
