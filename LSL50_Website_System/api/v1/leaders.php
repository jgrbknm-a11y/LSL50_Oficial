<?php
require __DIR__ . "/bootstrap.php";

use Lsl50\Api\V1\LeadersResource;

try {
  $pdo = db();
  $season = api_v1_season($pdo);
  $limit = api_v1_query_int("limit", 1, 1, 10);
  $scope = api_v1_query_scope("scope", "legacy");

  $payload = LeadersResource::build($pdo, $season, $limit, $scope);
  api_v1_json($payload);
} catch (Throwable $e) {
  $debug = (getenv("LSL50_DEBUG") ?: "") === "1";
  api_v1_json(LeadersResource::errorPayload($e, $debug), 500);
}
