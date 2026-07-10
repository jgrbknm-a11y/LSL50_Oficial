<?php

namespace Lsl50\Domain\Rules;

final class PlateAppearance
{
  public static function fromRow(array $row): int
  {
    return max(0, (int)($row["AB"] ?? 0))
      + max(0, (int)($row["BB"] ?? 0))
      + max(0, (int)($row["HBP"] ?? 0))
      + max(0, (int)($row["SH"] ?? 0))
      + max(0, (int)($row["SF"] ?? 0));
  }
}
