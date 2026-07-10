<?php

namespace Lsl50\Domain\Rules;

final class PlayResults
{
  public static function labels(): array
  {
    return [
      "OUT" => "Out",
      "1B" => "Hit sencillo",
      "2B" => "Doble",
      "3B" => "Triple",
      "HR" => "Jonrón",
      "BB" => "Base por bolas",
      "HBP" => "Golpeado",
      "E" => "Error",
      "FC" => "Fielder choice",
      "SO" => "Ponche",
      "SF" => "Sacrifice fly",
      "SH" => "Sacrifice bunt",
      "SB" => "Robo / avance",
      "WP" => "Wild pitch",
      "PB" => "Passed ball",
      "CR" => "Corredor emergente",
    ];
  }

  public static function label(string $result, ?string $outDetail = ""): string
  {
    $labels = self::labels();
    $label = $labels[$result] ?? $result;
    $detail = trim((string)$outDetail);
    return $detail !== "" ? $label . " (" . $detail . ")" : $label;
  }

  public static function baseDestinationLabel(?string $value): string
  {
    return match ((string)$value) {
      "1B" => "1B",
      "2B" => "2B",
      "3B" => "3B",
      "H" => "Anotó",
      "OUT" => "Out",
      "STAY" => "Se quedó",
      default => "-",
    };
  }

  public static function defaultBatterDestination(string $result): string
  {
    return [
      "1B" => "1B",
      "BB" => "1B",
      "HBP" => "1B",
      "E" => "1B",
      "FC" => "1B",
      "2B" => "2B",
      "3B" => "3B",
      "HR" => "H",
    ][$result] ?? "OUT";
  }

  public static function defaultOutDetail(string $result): string
  {
    return [
      "SO" => "K",
      "SF" => "SF",
      "SH" => "SH",
    ][$result] ?? "";
  }
}
