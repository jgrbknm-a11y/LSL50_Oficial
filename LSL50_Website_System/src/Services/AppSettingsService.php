<?php

namespace Lsl50\Services;

use InvalidArgumentException;
use PDO;

/** Configuración persistente en app_settings con validación estricta. */
final class AppSettingsService
{
  public const KEY_AI_AUTO_GENERATE_ON_CLOSE = "ai_auto_generate_on_close";

  /** @var list<string> */
  private const BOOL_KEYS = [
    self::KEY_AI_AUTO_GENERATE_ON_CLOSE,
  ];

  public static function getBool(PDO $pdo, string $key, bool $default = false): bool
  {
    self::assertBoolKey($key);
    $raw = lsl_setting($pdo, $key, $default ? "1" : "0");
    return self::parseBool($raw);
  }

  public static function setBool(PDO $pdo, string $key, bool $value): void
  {
    self::assertBoolKey($key);
    lsl_set_setting($pdo, $key, $value ? "1" : "0");
  }

  public static function isAiAutoGenerateOnClose(PDO $pdo): bool
  {
    return self::getBool($pdo, self::KEY_AI_AUTO_GENERATE_ON_CLOSE, true);
  }

  /** @return array{ok:true,key:string,value:bool,updated_at:string} */
  public static function setAiAutoGenerateOnClose(PDO $pdo, bool $enabled): array
  {
    self::setBool($pdo, self::KEY_AI_AUTO_GENERATE_ON_CLOSE, $enabled);
    return self::aiAutoGeneratePayload($pdo);
  }

  /** @return array{ok:true,key:string,value:bool,updated_at:string} */
  public static function aiAutoGeneratePayload(PDO $pdo): array
  {
    return [
      "ok" => true,
      "key" => self::KEY_AI_AUTO_GENERATE_ON_CLOSE,
      "value" => self::isAiAutoGenerateOnClose($pdo),
      "updated_at" => gmdate("c"),
    ];
  }

  public static function parseBool(mixed $raw): bool
  {
    if (is_bool($raw)) {
      return $raw;
    }
    $value = strtolower(trim((string)$raw));
    return in_array($value, ["1", "true", "yes", "on"], true);
  }

  public static function sanitizeBoolKey(string $key): string
  {
    $key = trim($key);
    if ($key === "" || !preg_match('/^[a-z0-9_]{2,64}$/', $key)) {
      throw new InvalidArgumentException("Clave de configuración inválida.");
    }
    self::assertBoolKey($key);
    return $key;
  }

  private static function assertBoolKey(string $key): void
  {
    if (!in_array($key, self::BOOL_KEYS, true)) {
      throw new InvalidArgumentException("Clave de configuración no permitida.");
    }
  }
}
