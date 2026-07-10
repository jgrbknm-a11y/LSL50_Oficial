          <?php
            $homeTotal = 0; $awayTotal = 0;
            foreach ($scorerRows as $row) {
              if ((int)$row["team_id"] === (int)$selectedGame["home_team_id"]) $homeTotal += (int)$row["R"];
              if ((int)$row["team_id"] === (int)$selectedGame["away_team_id"]) $awayTotal += (int)$row["R"];
            }
            $homePlayers = scorer_player_options($scorerRows, (int)$selectedGame["home_team_id"]);
            $awayPlayers = scorer_player_options($scorerRows, (int)$selectedGame["away_team_id"]);
            $positionOptions = scorer_positions();
            $currentBattingTeamName = (int)($gameContext["batting_team_id"] ?? 0) === (int)$selectedGame["home_team_id"] ? $selectedGame["home_name"] : $selectedGame["away_name"];
            $nextBatterName = "Sin lineup";
            if (!empty($gameContext["next_batter_id"])) {
              foreach ($scorerRows as $row) {
                if ((int)$row["player_id"] === (int)$gameContext["next_batter_id"]) $nextBatterName = $row["player_name"];
              }
            }
          ?>
