<?php

namespace Lsl50\Domain\Rules;

final class LineupRules
{
  public static function positions(): array
  {
    return [
      "" => "-",
      "P" => "P",
      "C" => "C",
      "1B" => "1B",
      "2B" => "2B",
      "3B" => "3B",
      "SS" => "SS",
      "SF" => "SF",
      "LF" => "LF",
      "CF" => "CF",
      "CR" => "CR",
      "RF" => "RF",
      "DH" => "DH",
      "OTRO" => "OTRO",
    ];
  }

  public static function requiredFieldPositions(): array
  {
    return ["P", "C", "1B", "2B", "3B", "SS", "SF", "LF", "CF", "CR", "RF"];
  }

  public static function playerOptions(array $rows, int $teamId = 0): array
  {
    $players = [];
    foreach ($rows as $row) {
      if ($teamId && (int)$row["team_id"] !== $teamId) continue;
      $players[] = $row;
    }
    return $players;
  }

  public static function playerTeamMap(array $rows): array
  {
    $map = [];
    foreach ($rows as $row) $map[(int)$row["player_id"]] = (int)$row["team_id"];
    return $map;
  }

  public static function sortRowsByLineup(array $rows, array $lineups, array $game): array
  {
    $lineupByPlayer = [];
    foreach ($lineups as $teamId => $teamLineup) {
      foreach ($teamLineup as $lineupRow) {
        $lineupByPlayer[(int)$lineupRow["player_id"]] = [
          "batting_order" => (int)$lineupRow["batting_order"],
          "field_position" => (string)($lineupRow["field_position"] ?? ""),
        ];
      }
    }

    foreach ($rows as &$row) {
      $lineupInfo = $lineupByPlayer[(int)$row["player_id"]] ?? null;
      $row["lineup_order"] = $lineupInfo["batting_order"] ?? null;
      $row["lineup_position"] = $lineupInfo["field_position"] ?? "";
    }
    unset($row);

    $teamRank = [
      (int)$game["away_team_id"] => 0,
      (int)$game["home_team_id"] => 1,
    ];

    usort($rows, function ($a, $b) use ($teamRank) {
      $teamA = $teamRank[(int)$a["team_id"]] ?? 9;
      $teamB = $teamRank[(int)$b["team_id"]] ?? 9;
      if ($teamA !== $teamB) return $teamA <=> $teamB;

      $orderA = $a["lineup_order"] !== null ? (int)$a["lineup_order"] : 999;
      $orderB = $b["lineup_order"] !== null ? (int)$b["lineup_order"] : 999;
      if ($orderA !== $orderB) return $orderA <=> $orderB;

      $numA = is_numeric($a["number"] ?? "") ? (int)$a["number"] : 999;
      $numB = is_numeric($b["number"] ?? "") ? (int)$b["number"] : 999;
      if ($numA !== $numB) return $numA <=> $numB;

      return strcmp((string)$a["player_name"], (string)$b["player_name"]);
    });

    return $rows;
  }

  /**
   * @param array<int|string, array{player_id?: mixed, field_position?: mixed}> $lineupRows
   * @return list<string>
   */
  public static function validate(array $lineupRows, int $teamId, array $playerTeams): array
  {
    $seenPlayers = [];
    $seenPositions = [];
    $validPositions = array_keys(self::positions());
    $errors = [];

    foreach ($lineupRows as $order => $row) {
      $battingOrder = max(1, min(15, (int)$order));
      $playerId = (int)($row["player_id"] ?? 0);
      $position = strtoupper(trim((string)($row["field_position"] ?? "")));
      if (!$playerId && $position !== "") $errors[] = "Turno $battingOrder: seleccionaste posición sin jugador.";
      if (!$playerId) continue;
      if (($playerTeams[$playerId] ?? 0) !== $teamId) $errors[] = "Turno $battingOrder: el jugador no pertenece a este equipo.";
      if (isset($seenPlayers[$playerId])) $errors[] = "Turno $battingOrder: ese jugador está repetido en el lineup.";
      if ($position === "") $errors[] = "Turno $battingOrder: falta seleccionar posición.";
      if (!in_array($position, $validPositions, true)) $errors[] = "Turno $battingOrder: posición inválida.";
      if ($position !== "" && $position !== "DH" && isset($seenPositions[$position])) $errors[] = "La posición $position está repetida.";
      $seenPlayers[$playerId] = true;
      if ($position !== "") $seenPositions[$position] = true;
    }

    foreach (self::requiredFieldPositions() as $position) {
      if (!isset($seenPositions[$position])) $errors[] = "Falta seleccionar la posición $position.";
    }

    return $errors;
  }
}
