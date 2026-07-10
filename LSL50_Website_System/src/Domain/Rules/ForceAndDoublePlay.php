<?php

namespace Lsl50\Domain\Rules;

final class ForceAndDoublePlay
{
  public static function forceOutMap(): array
  {
    return [
      "6-4" => "1B",
      "4-6" => "1B",
      "5-4" => "1B",
      "5-2" => "3B",
      "1-2" => "3B",
      "3-2" => "3B",
    ];
  }

  public static function doublePlayMap(): array
  {
    return [
      "6-4-3" => "1B",
      "4-6-3" => "1B",
      "5-4-3" => "1B",
      "5-2-3" => "3B",
      "1-2-3" => "3B",
      "3-2-3" => "3B",
    ];
  }

  public static function forceOutBase(string $outDetail): string
  {
    return self::forceOutMap()[$outDetail] ?? "";
  }

  public static function doublePlayBase(string $outDetail): string
  {
    return self::doublePlayMap()[$outDetail] ?? "";
  }
}
