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
          "name" => ApiSanitizer::text((string)($season["name"] ?? "")),
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
        "name" => ApiSanitizer::text((string)($row["name"] ?? "")),
        "slug" => ApiSanitizer::slug((string)($row["slug"] ?? "")),
        "logo_url" => ApiSanitizer::url($row["logo_url"] ?? null),
      ],
      "record" => [
        "wins" => (int)($row["wins"] ?? 0),
        "losses" => (int)($row["losses"] ?? 0),
        "ties" => (int)($row["ties"] ?? 0),
        "games_played" => (int)($row["gp"] ?? 0),
        "pct" => round((float)($row["pct"] ?? 0), 6),
        "pct_display" => ApiSanitizer::text((string)($row["pct_fmt"] ?? ".000")),
        "label" => ApiSanitizer::text((string)($row["record"] ?? "0-0")),
      ],
      "runs" => [
        "for" => (int)($row["runs_for"] ?? 0),
        "against" => (int)($row["runs_against"] ?? 0),
        "diff" => (int)($row["run_diff"] ?? 0),
      ],
      "form" => [
        "streak" => ApiSanitizer::text((string)($row["streak"] ?? "-")),
        "last_10" => ApiSanitizer::text((string)($row["l10"] ?? "-")),
        "games_back" => ApiSanitizer::text((string)($row["gb"] ?? "-")),
      ],
    ];
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
