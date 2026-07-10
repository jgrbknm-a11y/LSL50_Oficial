<?php

namespace Lsl50\Domain\Support;

use DateTimeImmutable;
use Throwable;

final class Dates
{
  public static function parseShortDate(string $value): ?string
  {
    $value = trim($value);
    if ($value === "") return null;
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})$/', $value, $m)) {
      $month = (int)$m[1];
      $day = (int)$m[2];
      $year = (int)$m[3];
      if ($year < 100) $year += 2000;
      if (checkdate($month, $day, $year)) return sprintf("%04d-%02d-%02d", $year, $month, $day);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    return null;
  }

  public static function formatShortDate(?string $value): string
  {
    if (!$value) return "-";
    try {
      return (new DateTimeImmutable($value))->format("m/d/y");
    } catch (Throwable $e) {
      return (string)$value;
    }
  }

  public static function gameTimeLabel(?string $location): string
  {
    if (!$location) return "Hora pendiente";
    if (!preg_match('/\b(\d{1,2}):(\d{2})\b/', $location, $m)) return "Hora pendiente";
    $hour = (int)$m[1];
    $minute = (int)$m[2];
    $suffix = $hour >= 12 ? "PM" : "AM";
    $displayHour = $hour % 12;
    if ($displayHour === 0) $displayHour = 12;
    return sprintf("%02d:%02d %s", $displayHour, $minute, $suffix);
  }
}
