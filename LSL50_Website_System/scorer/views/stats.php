          <?php if ($gameClosed): ?>
            <div id="scoreForm" class="card <?= $activeView === "stats" ? "" : "view-hidden" ?>">
              <h2>Estadísticas guardadas</h2>
              <div class="warning">Este juego está cerrado. Las estadísticas quedaron guardadas para posiciones, líderes y consulta oficial. Para modificar este juego, primero debes reabrirlo desde Validación oficial.</div>
              <a class="btn primary" href="/scorer/?game_id=<?= (int)$selectedGame["id"] ?>&view=lineups#officialValidation">Ver/Reabrir cierre</a>
            </div>
          <?php else: ?>
          <form method="post" id="scoreForm" class="<?= $activeView === "stats" ? "" : "view-hidden" ?>" data-home-team-id="<?= (int)$selectedGame["home_team_id"] ?>" data-away-team-id="<?= (int)$selectedGame["away_team_id"] ?>">
            <input type="hidden" name="action" value="save_box">
            <input type="hidden" name="game_id" value="<?= (int)$selectedGame["id"] ?>">
            <div class="card">
              <h1><?= h($selectedGame["home_name"]) ?> vs <?= h($selectedGame["away_name"]) ?></h1>
              <div class="score">
                <?= h($selectedGame["home_name"]) ?> <span id="homeScorePreview"><?= (int)$homeTotal ?></span>
                -
                <?= h($selectedGame["away_name"]) ?> <span id="awayScorePreview"><?= (int)$awayTotal ?></span>
              </div>
              <div class="small">PA automático: AB + BB + HBP + SH + SF. Para semifinales/final: mínimo 3 juegos legales, cada uno con al menos 1 PA.</div>
            </div>

            <div class="card">
              <label class="small">Pitcher ganador</label>
              <select name="winning_pitcher_id">
                <option value="">Sin pitcher ganador / empate</option>
                <?php foreach ($scorerRows as $p): ?>
                  <option value="<?= (int)$p["player_id"] ?>" <?= (int)($selectedGame["winning_pitcher_id"] ?? 0) === (int)$p["player_id"] ? "selected" : "" ?>>
                    <?= h($p["team_name"] . " - " . $p["player_name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="card scroll">
              <table id="boxTable">
                <thead>
                  <tr>
                    <th>Turno</th><th>Jugador</th><th class="hide-mobile">Equipo</th><th>PA</th>
                    <?php foreach ($statLabels as $label): ?><th><?= h($label) ?></th><?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                <?php $i = 0; $lastTeam = null; foreach ($scorerRows as $row): ?>
                  <?php if ($lastTeam !== (int)$row["team_id"]): $lastTeam = (int)$row["team_id"]; ?>
                    <tr class="team-row"><td colspan="<?= 4 + count($statLabels) ?>"><?= h($row["team_name"]) ?></td></tr>
                  <?php endif; ?>
                  <tr data-scorer-row>
                    <td><?= $row["lineup_order"] !== null ? (int)$row["lineup_order"] : "-" ?></td>
                    <td>
                      <strong><?= h($row["player_name"]) ?></strong>
                      <div class="small">
                        <?= $row["lineup_position"] ? h("Pos. " . $row["lineup_position"]) : "Fuera del lineup" ?>
                        <?= $row["number"] ? h(" | #" . $row["number"]) : "" ?>
                      </div>
                      <input type="hidden" name="rows[<?= $i ?>][player_id]" value="<?= (int)$row["player_id"] ?>">
                      <input type="hidden" name="rows[<?= $i ?>][team_id]" value="<?= (int)$row["team_id"] ?>">
                    </td>
                    <td class="hide-mobile" data-team-id="<?= (int)$row["team_id"] ?>"><?= h($row["team_name"]) ?></td>
                    <td><span class="pa-auto" data-pa-auto><?= scorer_plate_appearances($row) ?></span></td>
                    <?php foreach ($statKeys as $key): ?>
                      <td><input class="stat" type="number" min="0" name="rows[<?= $i ?>][<?= h($key) ?>]" value="<?= (int)($row[$key] ?? 0) ?>"></td>
                    <?php endforeach; ?>
                  </tr>
                <?php $i++; endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="card actions">
              <button class="primary">Guardar cuaderno</button>
              <a class="btn soft" href="/scorer/?game_id=<?= (int)$selectedGame["id"] ?>&view=stats#scorerTabs">Reabrir</a>
            </div>
          </form>
          <script src="/scorer/assets/scorer-stats.js"></script>
          <?php endif; ?>

