<?php
require __DIR__ . "/bootstrap.php";

use Lsl50\Api\V1\StandingsResource;

try {
  $pdo = db();
  $season = api_v1_season($pdo);
  $activeOnly = api_v1_query_bool("active_only", true);

  $payload = StandingsResource::build($pdo, $season, $activeOnly);
  api_v1_json($payload);
} catch (Throwable $e) {
  $debug = (getenv("LSL50_DEBUG") ?: "") === "1";
  api_v1_json(StandingsResource::errorPayload($e, $debug), 500);
}
