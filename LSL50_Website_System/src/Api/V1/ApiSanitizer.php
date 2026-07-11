<?php

namespace Lsl50\Api\V1;

/** Sanitización compartida para respuestas JSON API v1. */
final class ApiSanitizer
{
  public static function text(string $value, int $max = 200): string
  {
    $value = trim(strip_tags($value));
    return mb_substr($value, 0, $max);
  }

  public static function slug(string $value): string
  {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? "";
    return trim($value, "-");
  }

  public static function url(mixed $value): ?string
  {
    if (!is_string($value)) {
      return null;
    }
    $value = trim($value);
    if ($value === "") {
      return null;
    }
    if (str_starts_with($value, "/") || preg_match('#^https?://#i', $value)) {
      return mb_substr($value, 0, 512);
    }
    return null;
  }

  public static function clampInt(mixed $value, int $min, int $max, int $default): int
  {
    if (!is_numeric($value)) {
      return $default;
    }
    $int = (int)$value;
    return max($min, min($max, $int));
  }
}
