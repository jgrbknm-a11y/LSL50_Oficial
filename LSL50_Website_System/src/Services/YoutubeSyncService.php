<?php

namespace Lsl50\Services;

use InvalidArgumentException;
use Lsl50\Support\YoutubeHelper;
use PDO;
use RuntimeException;

/** YouTube Data API — credenciales .env, listado de videos y vínculo con juegos LSL50. */
final class YoutubeSyncService
{
  private const SETTING_API_KEY = "youtube_api_key";
  private const SETTING_CHANNEL_ID = "ai_youtube_channel_id";
  private const SETTING_RECENT_VIDEOS = "ai_youtube_recent_videos";
  private const ENV_API_KEY = "YOUTUBE_API_KEY";
  private const ENV_CHANNEL_ID = "YOUTUBE_CHANNEL_ID";

  /** @return array{api_key:string,channel_id:string,source:array{api_key:string,channel_id:string}} */
  public static function resolveCredentials(PDO $pdo): array
  {
    $apiFromDb = trim(lsl_setting($pdo, self::SETTING_API_KEY, ""));
    $apiFromEnv = trim(lsl_env(self::ENV_API_KEY) ?? "");
    $channelFromDb = trim(lsl_setting($pdo, self::SETTING_CHANNEL_ID, ""));
    $channelFromEnv = trim(lsl_env(self::ENV_CHANNEL_ID) ?? "");

    return [
      "api_key" => $apiFromDb !== "" ? $apiFromDb : $apiFromEnv,
      "channel_id" => $channelFromDb !== "" ? $channelFromDb : $channelFromEnv,
      "source" => [
        "api_key" => $apiFromDb !== "" ? "app_settings" : ($apiFromEnv !== "" ? "env" : "none"),
        "channel_id" => $channelFromDb !== "" ? "app_settings" : ($channelFromEnv !== "" ? "env" : "none"),
      ],
    ];
  }

  /** Persiste credenciales del .env en app_settings cuando faltan en DB. */
  public static function syncCredentialsFromEnv(PDO $pdo): array
  {
    $projectRoot = dirname(__DIR__, 3);
    $websiteRoot = dirname(__DIR__, 2);
    lsl_load_env_files([
      $projectRoot . "/.env",
      $websiteRoot . "/data/.env",
      $websiteRoot . "/.env",
    ]);

    $api = trim(lsl_env(self::ENV_API_KEY) ?? "");
    $channel = trim(lsl_env(self::ENV_CHANNEL_ID) ?? "");

    if ($api !== "" && trim(lsl_setting($pdo, self::SETTING_API_KEY, "")) === "") {
      lsl_set_setting($pdo, self::SETTING_API_KEY, $api);
    }
    if ($channel !== "" && trim(lsl_setting($pdo, self::SETTING_CHANNEL_ID, "")) === "") {
      lsl_set_setting($pdo, self::SETTING_CHANNEL_ID, $channel);
    }

    return self::resolveCredentials($pdo);
  }

  public static function isConfigured(PDO $pdo): bool
  {
    $creds = self::resolveCredentials($pdo);
    return $creds["api_key"] !== "" && $creds["channel_id"] !== "";
  }

  /**
   * @return array{ok:bool,status:int,channel_id?:string,videos:array<int,array<string,mixed>>,error?:string}
   */
  public static function fetchRecentVideos(PDO $pdo, int $limit = 20): array
  {
    $creds = self::resolveCredentials($pdo);
    if ($creds["api_key"] === "") {
      throw new InvalidArgumentException("YOUTUBE_API_KEY no configurada (.env o app_settings).");
    }
    if ($creds["channel_id"] === "") {
      throw new InvalidArgumentException("YOUTUBE_CHANNEL_ID no configurado (.env o app_settings).");
    }

    $limit = max(1, min(50, $limit));
    $url = "https://www.googleapis.com/youtube/v3/search?" . http_build_query([
      "part" => "snippet",
      "channelId" => $creds["channel_id"],
      "maxResults" => $limit,
      "order" => "date",
      "type" => "video",
      "key" => $creds["api_key"],
    ]);

    $response = self::httpJson("GET", $url);
    if (!$response["ok"]) {
      return [
        "ok" => false,
        "status" => (int)($response["status"] ?? 0),
        "error" => (string)($response["error"] ?? "YouTube API error"),
        "videos" => [],
      ];
    }

    $videos = [];
    foreach (($response["data"]["items"] ?? []) as $item) {
      $videoId = (string)($item["id"]["videoId"] ?? "");
      if ($videoId === "") {
        continue;
      }
      $publishedAt = (string)($item["snippet"]["publishedAt"] ?? "");
      $videos[] = [
        "id" => $videoId,
        "url" => YoutubeHelper::watchUrl($videoId) ?? "",
        "title" => (string)($item["snippet"]["title"] ?? "Video LSL50"),
        "published_at" => $publishedAt,
        "published_date" => substr($publishedAt, 0, 10),
        "thumbnail" => (string)($item["snippet"]["thumbnails"]["medium"]["url"] ?? ($item["snippet"]["thumbnails"]["default"]["url"] ?? "")),
        "description" => (string)($item["snippet"]["description"] ?? ""),
      ];
    }

    lsl_set_setting($pdo, self::SETTING_RECENT_VIDEOS, json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    lsl_set_setting($pdo, self::SETTING_CHANNEL_ID, $creds["channel_id"]);

    return [
      "ok" => true,
      "status" => 200,
      "channel_id" => $creds["channel_id"],
      "videos" => $videos,
    ];
  }

  /**
   * @param array<int, array<string, mixed>> $videos
   * @return list<array{game_id:int,home_name:string,away_name:string,game_date:string,video_id:string,video_url:string,video_title:string,score:int,reason:string}>
   */
  public static function matchVideosToGames(PDO $pdo, int $seasonId, array $videos, int $minScore = 45): array
  {
    $seasonId = (int)$seasonId;
    $games = $pdo->query("SELECT g.id, g.game_date, g.youtube_video_id,
        ht.name home_name, at.name away_name
      FROM games g
      JOIN teams ht ON ht.id = g.home_team_id
      JOIN teams at ON at.id = g.away_team_id
      WHERE COALESCE(g.season_id, $seasonId) = $seasonId
        AND COALESCE(g.status, '') IN ('final', 'completed', 'closed', 'played')
      ORDER BY g.game_date DESC, g.id DESC")->fetchAll();

    if (!$games) {
      $games = $pdo->query("SELECT g.id, g.game_date, g.youtube_video_id,
          ht.name home_name, at.name away_name
        FROM games g
        JOIN teams ht ON ht.id = g.home_team_id
        JOIN teams at ON at.id = g.away_team_id
        WHERE COALESCE(g.season_id, $seasonId) = $seasonId
          AND (g.final_home > 0 OR g.final_away > 0 OR COALESCE(g.status,'') = 'final')
        ORDER BY g.game_date DESC, g.id DESC")->fetchAll();
    }

    $pairs = [];
    foreach ($games as $game) {
      if (trim((string)($game["youtube_video_id"] ?? "")) !== "") {
        continue;
      }
      foreach ($videos as $video) {
        $score = self::scoreVideoForGame($video, $game);
        if ($score >= $minScore) {
          $pairs[] = [
            "game" => $game,
            "video" => $video,
            "score" => $score,
            "reason" => self::matchReason($video, $game, $score),
          ];
        }
      }
    }

    usort($pairs, static fn(array $a, array $b) => $b["score"] <=> $a["score"]);

    $usedGames = [];
    $usedVideos = [];
    $matches = [];

    foreach ($pairs as $pair) {
      $gameId = (int)$pair["game"]["id"];
      $videoId = (string)$pair["video"]["id"];
      if (isset($usedGames[$gameId]) || isset($usedVideos[$videoId])) {
        continue;
      }
      $usedGames[$gameId] = true;
      $usedVideos[$videoId] = true;
      $matches[] = [
        "game_id" => $gameId,
        "home_name" => (string)$pair["game"]["home_name"],
        "away_name" => (string)$pair["game"]["away_name"],
        "game_date" => substr((string)$pair["game"]["game_date"], 0, 10),
        "video_id" => $videoId,
        "video_url" => (string)$pair["video"]["url"],
        "video_title" => (string)$pair["video"]["title"],
        "score" => (int)$pair["score"],
        "reason" => (string)$pair["reason"],
      ];
    }

    return $matches;
  }

  /**
   * @return array{
   *   ok:bool,
   *   channel_id?:string,
   *   videos_fetched:int,
   *   matches:array,
   *   linked:int,
   *   dry_run:bool,
   *   credentials:array
   * }
   */
  public static function synchronize(PDO $pdo, int $seasonId, bool $dryRun = false, int $videoLimit = 25): array
  {
    self::syncCredentialsFromEnv($pdo);
    $creds = self::resolveCredentials($pdo);

    $fetch = self::fetchRecentVideos($pdo, $videoLimit);
    if (!$fetch["ok"]) {
      throw new RuntimeException($fetch["error"] ?? "No se pudieron obtener videos de YouTube.");
    }

    $matches = self::matchVideosToGames($pdo, $seasonId, $fetch["videos"]);
    $linked = 0;

    if (!$dryRun) {
      $updateGame = $pdo->prepare("UPDATE games SET youtube_video_id = ? WHERE id = ? AND COALESCE(season_id, ?) = ?");
      $updateNote = $pdo->prepare("UPDATE ai_game_notes SET video_url = ?, updated_at = CURRENT_TIMESTAMP WHERE game_id = ? AND season_id = ?");

      foreach ($matches as $match) {
        $updateGame->execute([$match["video_id"], $match["game_id"], $seasonId, $seasonId]);
        $updateNote->execute([$match["video_url"], $match["game_id"], $seasonId]);
        $linked++;
      }
    }

    return [
      "ok" => true,
      "channel_id" => $fetch["channel_id"] ?? $creds["channel_id"],
      "videos_fetched" => count($fetch["videos"]),
      "matches" => $matches,
      "linked" => $dryRun ? 0 : $linked,
      "dry_run" => $dryRun,
      "credentials" => $creds["source"],
    ];
  }

  /** @param array<string, mixed> $video @param array<string, mixed> $game */
  public static function scoreVideoForGame(array $video, array $game): int
  {
    $title = self::normalizeText((string)($video["title"] ?? ""));
    $home = self::normalizeText((string)($game["home_name"] ?? ""));
    $away = self::normalizeText((string)($game["away_name"] ?? ""));

    $score = 0;
    $publishedDate = (string)($video["published_date"] ?? substr((string)($video["published_at"] ?? ""), 0, 10));
    $gameDate = substr((string)($game["game_date"] ?? ""), 0, 10);

    if ($publishedDate !== "" && $gameDate !== "" && $publishedDate === $gameDate) {
      $score += 55;
    } elseif ($publishedDate !== "" && $gameDate !== "") {
      $delta = abs(strtotime($publishedDate) - strtotime($gameDate));
      if ($delta <= 86400) {
        $score += 35;
      } elseif ($delta <= 86400 * 3) {
        $score += 15;
      }
    }

    if ($home !== "" && str_contains($title, $home)) {
      $score += 25;
    }
    if ($away !== "" && str_contains($title, $away)) {
      $score += 25;
    }

    foreach (self::teamTokens($home) as $token) {
      if (str_contains($title, $token)) {
        $score += 8;
      }
    }
    foreach (self::teamTokens($away) as $token) {
      if (str_contains($title, $token)) {
        $score += 8;
      }
    }

    if (preg_match('/\b(vs|versus|frente a|@)\b/u', $title)) {
      $score += 5;
    }

    return $score;
  }

  /** @param array<string, mixed> $video @param array<string, mixed> $game */
  private static function matchReason(array $video, array $game, int $score): string
  {
    $bits = ["score={$score}"];
    $pub = (string)($video["published_date"] ?? "");
    $gd = substr((string)($game["game_date"] ?? ""), 0, 10);
    if ($pub === $gd) {
      $bits[] = "fecha exacta";
    }
    $title = self::normalizeText((string)($video["title"] ?? ""));
    if (str_contains($title, self::normalizeText((string)$game["home_name"]))) {
      $bits[] = "local en título";
    }
    if (str_contains($title, self::normalizeText((string)$game["away_name"]))) {
      $bits[] = "visitante en título";
    }
    return implode(", ", $bits);
  }

  /** @return list<string> */
  private static function teamTokens(string $teamName): array
  {
    $normalized = self::normalizeText($teamName);
    if ($normalized === "") {
      return [];
    }
    $tokens = [$normalized];
    foreach (preg_split('/\s+/u', $normalized) ?: [] as $word) {
      if (mb_strlen($word) >= 4) {
        $tokens[] = $word;
      }
    }
    return array_values(array_unique($tokens));
  }

  private static function normalizeText(string $value): string
  {
    $value = mb_strtolower(trim($value));
    $map = ["á" => "a", "é" => "e", "í" => "i", "ó" => "o", "ú" => "u", "ñ" => "n"];
    return strtr($value, $map);
  }

  /** @param array<string, mixed>|null $payload */
  private static function httpJson(string $method, string $url, array $headers = [], ?array $payload = null): array
  {
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
        CURLOPT_TIMEOUT => 25,
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
          "timeout" => 25,
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
      return ["ok" => false, "status" => $status, "error" => "Respuesta inválida de YouTube."];
    }
    if ($status < 200 || $status >= 300) {
      $message = $data["error"]["message"] ?? $data["error"]["errors"][0]["message"] ?? "Error YouTube API.";
      return ["ok" => false, "status" => $status, "error" => $message, "data" => $data];
    }

    return ["ok" => true, "status" => $status, "data" => $data];
  }
}
