<?php

namespace Lsl50\Api\Admin;

use InvalidArgumentException;
use Lsl50\Services\AppSettingsService;
use PDO;
use Throwable;

/** API admin JSON — actualización segura de app_settings. */
final class SettingsResource
{
  /**
   * @param array<string, mixed> $input
   * @return array{ok:bool,setting:array}|array{ok:false,error:array}
   */
  public static function updateBool(PDO $pdo, array $input): array
  {
    $key = AppSettingsService::sanitizeBoolKey((string)($input["key"] ?? ""));
    $value = AppSettingsService::parseBool($input["value"] ?? false);

    $setting = match ($key) {
      AppSettingsService::KEY_AI_AUTO_GENERATE_ON_CLOSE => AppSettingsService::setAiAutoGenerateOnClose($pdo, $value),
      default => throw new InvalidArgumentException("Clave de configuración no soportada."),
    };

    return [
      "ok" => true,
      "setting" => [
        "key" => $setting["key"],
        "value" => $setting["value"],
        "label" => self::labelForKey($key),
        "updated_at" => $setting["updated_at"],
      ],
    ];
  }

  /** @return array{ok:false,error:array} */
  public static function errorPayload(Throwable $e, bool $debug = false): array
  {
    $code = "settings_update_failed";
    $message = "No fue posible guardar la configuración.";
    $status = 500;

    if ($e instanceof InvalidArgumentException) {
      $code = "settings_invalid_input";
      $message = $e->getMessage();
      $status = 422;
    }

    $payload = [
      "ok" => false,
      "error" => [
        "code" => $code,
        "message" => $message,
        "status" => $status,
      ],
    ];
    if ($debug) {
      $payload["error"]["detail"] = $e->getMessage();
    }
    return $payload;
  }

  private static function labelForKey(string $key): string
  {
    return match ($key) {
      AppSettingsService::KEY_AI_AUTO_GENERATE_ON_CLOSE => "Generar crónica IA al cerrar juego",
      default => $key,
    };
  }
}
