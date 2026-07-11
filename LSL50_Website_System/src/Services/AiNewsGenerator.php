<?php

namespace Lsl50\Services;

use Lsl50\Support\YoutubeHelper;
use PDO;
use RuntimeException;
use SqlDialect;

/**
 * Genera crónicas deportivas automáticas al cerrar un juego.
 * Usa OpenAI si hay API key; fallback con estadísticas oficiales del cuaderno.
 */
final class AiNewsGenerator
{
  public static function generateForGame(PDO $pdo, int $seasonId, int $gameId, bool $autoPublish = false): array
  {
    $context = self::gameContext($pdo, $gameId, $seasonId);
    if (!$context) {
      return ["ok" => false, "error" => "Juego no encontrado."];
    }

    $clipSeconds = max(15, (int)lsl_setting($pdo, "ai_clip_seconds", "90"));
    $style = lsl_setting($pdo, "ai_editorial_style", "profesional, emocionante, periodismo deportivo en español");
    $videoUrl = self::resolveVideoUrl($pdo, $context["game"]);
    $videoMeta = [];

    $provider = "local-stats";
    try {
      $openAiKey = lsl_setting($pdo, "openai_api_key", "");
      if ($openAiKey !== "") {
        $note = self::buildOpenAiNote(
          $context,
          $clipSeconds,
          $openAiKey,
          lsl_setting($pdo, "openai_model", "gpt-4.1-mini"),
          $style,
          $videoUrl,
          $videoMeta
        );
        $provider = "openai";
      } else {
        $note = self::buildLocalNote($context, $clipSeconds);
      }
    } catch (Throwable $e) {
      $note = self::buildLocalNote($context, $clipSeconds);
      $provider = "local-stats";
    }

    $status = ($autoPublish || lsl_setting($pdo, "ai_publish_mode", "review") === "auto") ? "published" : "draft";
    self::upsertNote($pdo, $seasonId, $gameId, $note, $videoUrl, $provider, $status);

    return [
      "ok" => true,
      "provider" => $provider,
      "status" => $status,
      "title" => $note["title"],
      "video_url" => $videoUrl,
    ];
  }

  public static function gameContext(PDO $pdo, int $gameId, int $seasonId): ?array
  {
    $stmt = $pdo->prepare("SELECT g.*, ht.name home_name, at.name away_name,
        " . lsl_sql_full_name("wp") . " winning_pitcher_name
      FROM games g
      JOIN teams ht ON ht.id=g.home_team_id
      JOIN teams at ON at.id=g.away_team_id
      LEFT JOIN players wp ON wp.id=g.winning_pitcher_id
      WHERE g.id=? AND COALESCE(g.season_id, $seasonId) = $seasonId");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    if (!$game) return null;

    $hitExpr = SqlDialect::greatest("gps.H", "gps.dbl + gps.tpl + gps.HR");
    $leaders = $pdo->prepare("SELECT " . lsl_sql_full_name("p") . " player_name, p.number, t.name team_name,
        gps.AB, {$hitExpr} H, gps.dbl, gps.tpl, gps.R, gps.RBI, gps.HR, gps.BB, gps.SO
      FROM game_player_stats gps
      JOIN players p ON p.id=gps.player_id
      JOIN teams t ON t.id=gps.team_id
      WHERE gps.game_id=?
      ORDER BY gps.HR DESC, gps.RBI DESC, {$hitExpr} DESC, gps.R DESC
      LIMIT 5");
    $leaders->execute([$gameId]);

    $plays = $pdo->prepare("SELECT e.inning, e.half, e.result, e.rbi, e.runs_scored, e.out_detail,
        " . lsl_sql_full_name("b") . " batter_name
      FROM game_play_events e
      JOIN players b ON b.id=e.batter_id
      WHERE e.game_id=?
      ORDER BY e.inning, CASE e.half WHEN 'top' THEN 0 ELSE 1 END, e.id
      LIMIT 40");
    $plays->execute([$gameId]);

    return [
      "game" => $game,
      "leaders" => $leaders->fetchAll(),
      "plays" => $plays->fetchAll(),
    ];
  }

  private static function resolveVideoUrl(PDO $pdo, array $game): string
  {
    $id = trim((string)($game["youtube_video_id"] ?? ""));
    if ($id !== "") {
      return YoutubeHelper::watchUrl($id) ?? "";
    }
    return "";
  }

  private static function buildLocalNote(array $context, int $clipSeconds): array
  {
    $game = $context["game"];
    $home = $game["home_name"];
    $away = $game["away_name"];
    $homeRuns = (int)$game["final_home"];
    $awayRuns = (int)$game["final_away"];
    $winner = $homeRuns === $awayRuns ? "Empate" : ($homeRuns > $awayRuns ? $home : $away);
    $loser = $homeRuns === $awayRuns ? "" : ($homeRuns > $awayRuns ? $away : $home);
    $score = "$away $awayRuns, $home $homeRuns";

    $title = $homeRuns === $awayRuns
      ? "$home y $away empatan en la jornada LSL50"
      : "$winner vence a $loser en emocionante duelo LSL50";

    $leaders = $context["leaders"];
    $top = $leaders[0] ?? null;
    $summary = $top
      ? "Figura del partido: " . $top["player_name"] . " (" . $top["team_name"] . ") con " . (int)$top["H"] . " hits y " . (int)$top["RBI"] . " impulsadas."
      : "Crónica generada desde el cuaderno oficial LSL50.";

    $body = [];
    $body[] = "En una noche de softball +50 en Broward, el marcador final fue $score.";
    if ($homeRuns !== $awayRuns) {
      $body[] = "$winner se llevó la victoria tras un encuentro intenso ante $loser, dejando huella en la tabla de posiciones.";
    }
    if (!empty($game["winning_pitcher_name"])) {
      $body[] = "El pitcher ganador fue " . $game["winning_pitcher_name"] . ", clave en el cierre del partido.";
    }
    if ($leaders) {
      $body[] = "Los protagonistas ofensivos:";
      foreach ($leaders as $row) {
        $body[] = "• " . ($row["number"] ? "#" . $row["number"] . " " : "") . $row["player_name"]
          . " (" . $row["team_name"] . "): " . (int)$row["H"] . " H, " . (int)$row["RBI"] . " RBI, " . (int)$row["HR"] . " HR.";
      }
    }
    $body[] = "Crónica oficial Legends Softball League 50+ — datos verificados en el cuaderno digital.";

    return [
      "title" => $title,
      "summary" => $summary,
      "body" => implode("\n\n", $body),
      "clip_start_seconds" => 0,
      "clip_end_seconds" => $clipSeconds,
      "highlight_reason" => $top ? "Momento clave de " . $top["player_name"] : "Jugada decisiva del cierre",
    ];
  }

  private static function buildOpenAiNote(
    array $context,
    int $clipSeconds,
    string $apiKey,
    string $model,
    string $style,
    string $videoUrl,
    array $videoMeta
  ): array {
    $game = $context["game"];
    $payload = [
      "game" => [
        "away" => $game["away_name"],
        "home" => $game["home_name"],
        "final_away" => (int)$game["final_away"],
        "final_home" => (int)$game["final_home"],
        "date" => $game["game_date"],
        "winning_pitcher" => $game["winning_pitcher_name"] ?: null,
      ],
      "leaders" => $context["leaders"],
      "play_by_play" => $context["plays"] ?? [],
      "video" => ["url" => $videoUrl],
    ];
    $prompt = "Eres el redactor oficial de Legends Softball League 50+ (Broward, FL).\n"
      . "Escribe una crónica periodística PREMIUM, emocionante y profesional en español.\n"
      . "Destaca al equipo ganador, al bateador clave y al pitcher ganador si aplica.\n"
      . "Estilo: $style\n"
      . "Devuelve SOLO JSON válido: title, summary, body, clip_start_seconds, clip_end_seconds, highlight_reason.\n"
      . "body = 3-5 párrafos. No inventes datos.\n\n"
      . json_encode($payload, JSON_UNESCAPED_UNICODE);

    $response = self::httpJson("POST", "https://api.openai.com/v1/responses", [
      "Authorization: Bearer " . $apiKey,
    ], [
      "model" => $model ?: "gpt-4.1-mini",
      "input" => [
        ["role" => "system", "content" => "Editor deportivo. JSON válido únicamente."],
        ["role" => "user", "content" => $prompt],
      ],
    ]);
    if (!$response["ok"]) throw new RuntimeException($response["error"] ?? "OpenAI error");
    $text = self::openAiText($response["data"]);
    $text = preg_replace('/^```json\s*|\s*```$/', "", trim($text));
    $note = json_decode($text, true);
    if (!is_array($note)) throw new RuntimeException("JSON inválido de OpenAI");
    return [
      "title" => trim((string)($note["title"] ?? "")) ?: "Crónica LSL50",
      "summary" => trim((string)($note["summary"] ?? "")),
      "body" => trim((string)($note["body"] ?? "")),
      "clip_start_seconds" => max(0, (int)($note["clip_start_seconds"] ?? 0)),
      "clip_end_seconds" => max(15, min(3600, (int)($note["clip_end_seconds"] ?? $clipSeconds))),
      "highlight_reason" => trim((string)($note["highlight_reason"] ?? "")) ?: "Momento destacado del partido",
    ];
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
    if (isset($data["output_text"]) && is_string($data["output_text"])) return trim($data["output_text"]);
    $chunks = [];
    foreach (($data["output"] ?? []) as $item) {
      foreach (($item["content"] ?? []) as $content) {
        if (($content["type"] ?? "") === "output_text" && isset($content["text"])) $chunks[] = $content["text"];
      }
    }
    return trim(implode("\n", $chunks));
  }

  private static function httpJson(string $method, string $url, array $headers = [], ?array $payload = null): array
  {
    $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);
    $headerLines = array_merge(["Accept: application/json"], $headers);
    if ($body !== null) $headerLines[] = "Content-Type: application/json";
    if (function_exists("curl_init")) {
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headerLines,
        CURLOPT_TIMEOUT => 30,
      ]);
      if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
      $raw = curl_exec($ch);
      $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      curl_close($ch);
      if ($raw === false) return ["ok" => false, "error" => "Conexión fallida"];
    } else {
      return ["ok" => false, "error" => "cURL no disponible"];
    }
    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300) {
      return ["ok" => false, "error" => $data["error"]["message"] ?? "API error $status"];
    }
    return ["ok" => true, "data" => is_array($data) ? $data : []];
  }
}
