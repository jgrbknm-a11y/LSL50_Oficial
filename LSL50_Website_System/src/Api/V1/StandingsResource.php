<?php

namespace Lsl50\Api\V1;

use Lsl50\Services\PublicStatsService;
use PDO;
use Throwable;

/** Respuesta JSON de standings — paridad 1:1 con portal público (Racha, U10, GB). */
final class StandingsResource
{
  /**
   * @param array{id:int,name:string} $season
   * @return array{ok:bool,meta:array,standings:list<array>}
   */
  public static function build(PDO $pdo, array $season, bool $activeTeamsOnly = true): array
  {
    $seasonId = (int)($season["id"] ?? 0);
    if ($seasonId <= 0) {
      throw new \InvalidArgumentException("season.id inválido.");
    }

    $rows = PublicStatsService::standings($pdo, $seasonId, $activeTeamsOnly);

    return [
      "ok" => true,
      "meta" => [
        "season" => [
          "id" => $seasonId,
          "name" => self::sanitizeText((string)($season["name"] ?? "")),
        ],
        "generated_at" => gmdate("c"),
        "active_teams_only" => $activeTeamsOnly,
        "team_count" => count($rows),
      ],
      "standings" => array_map(static fn(array $row) => self::mapRow($row), $rows),
    ];
  }

  /** @return array<string, mixed> */
  private static function mapRow(array $row): array
  {
    return [
      "rank" => (int)($row["pos"] ?? 0),
      "team" => [
        "id" => (int)($row["team_id"] ?? 0),
        "name" => self::sanitizeText((string)($row["name"] ?? "")),
        "slug" => self::sanitizeSlug((string)($row["slug"] ?? "")),
        "logo_url" => self::sanitizeUrl($row["logo_url"] ?? null),
      ],
      "record" => [
        "wins" => (int)($row["wins"] ?? 0),
        "losses" => (int)($row["losses"] ?? 0),
        "ties" => (int)($row["ties"] ?? 0),
        "games_played" => (int)($row["gp"] ?? 0),
        "pct" => round((float)($row["pct"] ?? 0), 6),
        "pct_display" => self::sanitizeText((string)($row["pct_fmt"] ?? ".000")),
        "label" => self::sanitizeText((string)($row["record"] ?? "0-0")),
      ],
      "runs" => [
        "for" => (int)($row["runs_for"] ?? 0),
        "against" => (int)($row["runs_against"] ?? 0),
        "diff" => (int)($row["run_diff"] ?? 0),
      ],
      "form" => [
        "streak" => self::sanitizeText((string)($row["streak"] ?? "-")),
        "last_10" => self::sanitizeText((string)($row["l10"] ?? "-")),
        "games_back" => self::sanitizeText((string)($row["gb"] ?? "-")),
      ],
    ];
  }

  private static function sanitizeText(string $value): string
  {
    $value = trim(strip_tags($value));
    return mb_substr($value, 0, 200);
  }

  private static function sanitizeSlug(string $value): string
  {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? "";
    return trim($value, "-");
  }

  private static function sanitizeUrl(mixed $value): ?string
  {
    if (!is_string($value)) {
      return null;
    }
    $value = trim($value);
    if ($value === "") {
      return null;
    }
    if (str_starts_with($value, "/")) {
      return mb_substr($value, 0, 512);
    }
    if (preg_match('#^https?://#i', $value)) {
      return mb_substr($value, 0, 512);
    }
    return null;
  }

  /** @return array{ok:false,error:array} */
  public static function errorPayload(Throwable $e, bool $debug = false): array
  {
    $payload = [
      "ok" => false,
      "error" => [
        "code" => "standings_unavailable",
        "message" => "No fue posible cargar la tabla de posiciones.",
      ],
    ];
    if ($debug) {
      $payload["error"]["detail"] = $e->getMessage();
    }
    return $payload;
  }
}
