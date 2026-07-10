<?php

namespace Lsl50\Domain\Rules;

final class BaseState
{
  public static function fromEvents(array $playEvents, ?int $inning = null, ?string $half = null): array
  {
    $bases = [
      "1B" => null,
      "2B" => null,
      "3B" => null,
    ];
    $putRunner = function (?int $id, ?string $name, string $to) use (&$bases): void {
      if (!$id) return;
      foreach (["1B", "2B", "3B"] as $base) {
        if (($bases[$base]["id"] ?? 0) === $id) $bases[$base] = null;
      }
      if (in_array($to, ["1B", "2B", "3B"], true)) {
        $bases[$to] = ["id" => $id, "name" => $name ?: "Corredor"];
      }
    };

    foreach ($playEvents as $event) {
      if ($inning !== null && ((int)$event["inning"] !== $inning || (string)$event["half"] !== (string)$half)) continue;
      if (!empty($event["runner_1b_id"])) {
        $to = $event["runner_1b_to"] ?: "1B";
        if ($to === "STAY") $to = "1B";
        $putRunner((int)$event["runner_1b_id"], $event["runner_1b_name"] ?? "", $to);
      }
      if (!empty($event["runner_2b_id"])) {
        $to = $event["runner_2b_to"] ?: "2B";
        if ($to === "STAY") $to = "2B";
        $putRunner((int)$event["runner_2b_id"], $event["runner_2b_name"] ?? "", $to);
      }
      if (!empty($event["runner_3b_id"])) {
        $to = $event["runner_3b_to"] ?: "3B";
        if ($to === "STAY") $to = "3B";
        $putRunner((int)$event["runner_3b_id"], $event["runner_3b_name"] ?? "", $to);
      }
      if (!empty($event["batter_id"])) {
        $putRunner((int)$event["batter_id"], $event["batter_name"] ?? "", (string)($event["batter_to"] ?? ""));
      }
    }
    return $bases;
  }
}
