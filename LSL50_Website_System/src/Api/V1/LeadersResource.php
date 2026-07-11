<?php

namespace Lsl50\Api\V1;

use Lsl50\Services\StatsEngine;
use PDO;
use Throwable;

/** Líderes ofensivos API v1 — delegación total a StatsEngine (paridad /estadisticas). */
final class LeadersResource
{
  /** Claves legacy para compatibilidad con consumidores existentes. */
  private const LEGACY_KEYS = [
    "avg" => "AVG",
    "hits" => "H",
    "hr" => "HR",
    "rbi" => "RBI",
  ];

  /**
   * @param array{id:int,name:string} $season
   * @return array{ok:bool,meta:array,leaders:array,featured:?array}
   */
  public static function build(PDO $pdo, array $season, int $limit = 1, string $scope = "legacy"): array
  {
    $seasonId = (int)($season["id"] ?? 0);
    if ($seasonId <= 0) {
      throw new \InvalidArgumentException("season.id inválido.");
    }

    $limit = ApiSanitizer::clampInt($limit, 1, 10, 1);
    $departments = self::resolveDepartments($scope);
    $leaders = [];
    $featured = null;

    foreach ($departments as $key => $dept) {
      $rows = StatsEngine::offensiveLeaders(
        $pdo,
        (string)$dept["expr"],
        (string)$dept["where"],
        (string)$dept["order"],
        $limit,
        !empty($dept["qualified"])
      );

      $entry = [
        "key" => $key,
        "department" => [
          "title" => ApiSanitizer::text((string)$dept["title"]),
          "abbr" => ApiSanitizer::text((string)$dept["abbr"]),
          "type" => ApiSanitizer::text((string)($dept["type"] ?? "int")),
          "qualified" => !empty($dept["qualified"]),
        ],
        "leader" => isset($rows[0]) ? self::mapPlayer($rows[0], (string)($dept["type"] ?? "int")) : null,
        "top" => array_map(
          static fn(array $row) => self::mapPlayer($row, (string)($dept["type"] ?? "int")),
          $rows
        ),
      ];

      $leaders[$key] = $entry;
      if (!empty($dept["featured"]) && $featured === null && $entry["leader"] !== null) {
        $featured = $entry;
      }
    }

    return [
      "ok" => true,
      "meta" => [
        "season" => [
          "id" => $seasonId,
          "name" => ApiSanitizer::text((string)($season["name"] ?? "")),
        ],
        "generated_at" => gmdate("c"),
        "league_games" => StatsEngine::leagueGames($pdo, $seasonId),
        "scope" => $scope,
        "limit" => $limit,
        "department_count" => count($leaders),
      ],
      "featured" => $featured,
      "leaders" => $leaders,
    ];
  }

  /** @return array<string, array> */
  private static function resolveDepartments(string $scope): array
  {
    $scope = strtolower(trim($scope));
    $all = StatsEngine::offensiveDepartments($scope === "full" ? "full" : "public");

    if ($scope === "full") {
      $out = [];
      foreach ($all as $dept) {
        $key = self::departmentKey($dept);
        $out[$key] = $dept;
      }
      return $out;
    }

    $indexed = [];
    foreach ($all as $dept) {
      $abbr = (string)$dept["abbr"];
      if (!isset($indexed[$abbr]) || !empty($dept["featured"])) {
        $indexed[$abbr] = $dept;
      }
    }

    $out = [];
    foreach (self::LEGACY_KEYS as $legacyKey => $abbr) {
      if (isset($indexed[$abbr])) {
        $out[$legacyKey] = $indexed[$abbr];
      }
    }
    return $out;
  }

  /** @param array<string, mixed> $dept */
  private static function departmentKey(array $dept): string
  {
    $abbr = strtoupper((string)($dept["abbr"] ?? "stat"));
    $map = array_flip(self::LEGACY_KEYS);
    if (isset($map[$abbr])) {
      return $map[$abbr];
    }
    return ApiSanitizer::slug(strtolower($abbr));
  }

  /** @param array<string, mixed> $row */
  private static function mapPlayer(array $row, string $type): array
  {
    $value = $row["leader_value"] ?? 0;
    $isAvg = $type === "avg";

    return [
      "player" => [
        "id" => (int)($row["id"] ?? 0),
        "name" => ApiSanitizer::text((string)($row["player_name"] ?? "")),
        "number" => ApiSanitizer::text((string)($row["number"] ?? ""), 8),
      ],
      "team" => [
        "name" => ApiSanitizer::text((string)($row["team_name"] ?? "-")),
      ],
      "stat" => [
        "value" => $isAvg ? round((float)$value, 3) : (int)$value,
        "display" => StatsEngine::fmtLeaderValue($value, $type),
        "type" => $type,
      ],
      "line" => [
        "games_played" => (int)($row["games_played"] ?? 0),
        "ab" => (int)($row["AB"] ?? 0),
        "h" => (int)($row["H"] ?? 0),
        "pa" => (int)($row["PA"] ?? 0),
      ],
    ];
  }

  /** @return array{ok:false,error:array} */
  public static function errorPayload(Throwable $e, bool $debug = false): array
  {
    $payload = [
      "ok" => false,
      "error" => [
        "code" => "leaders_unavailable",
        "message" => "No fue posible cargar los líderes ofensivos.",
      ],
    ];
    if ($debug) {
      $payload["error"]["detail"] = $e->getMessage();
    }
    return $payload;
  }
}
