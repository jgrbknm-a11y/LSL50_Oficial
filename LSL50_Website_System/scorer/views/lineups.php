          <section class="card <?= $activeView === "lineups" ? "" : "view-hidden" ?>" id="lineups">
            <h2>Lineup oficial</h2>
            <div class="game-state">
              <div class="state-tile"><span class="small">Inning</span><strong><?= (int)($gameContext["inning"] ?? 1) ?></strong></div>
              <div class="state-tile"><span class="small">Parte</span><strong><?= ($gameContext["half"] ?? "top") === "bottom" ? "Baja" : "Alta" ?></strong></div>
              <div class="state-tile"><span class="small">Equipo al bate</span><strong><?= h($currentBattingTeamName) ?></strong></div>
              <div class="state-tile"><span class="small">Próximo bateador</span><strong><?= h($nextBatterName) ?></strong><span class="small"><?= !empty($gameContext["next_order"]) ? "Turno #" . (int)$gameContext["next_order"] : "Guarda el lineup para activar el turno" ?></span></div>
            </div>
            <div class="warning" style="margin-top:12px">
              <?= $gameClosed ? "Este juego está cerrado. La información quedó guardada para consulta oficial; para corregirlo debe reabrirse desde la validación." : "Primero se carga el lineup que entrega cada manager. Si hay cambio durante el juego, se reemplaza el jugador en el mismo turno al bate y se ajusta su posición." ?>
            </div>
            <div class="lineup-grid">
              <?php if (!$gameClosed): ?>
                <form method="post" class="muted-box">
                  <input type="hidden" name="action" value="add_borrowed_player">
                  <input type="hidden" name="game_id" value="<?= (int)$selectedGame["id"] ?>">
                  <h3>Jugador prestado para este juego</h3>
                  <div class="small">Se usa solo para evitar forfeit. No cambia el roster oficial del jugador.</div>
                  <div style="height:8px"></div>
                  <label class="small">Equipo que lo usa</label>
                  <select name="borrowed_team_id">
                    <option value="<?= (int)$selectedGame["away_team_id"] ?>"><?= h($selectedGame["away_name"]) ?></option>
                    <option value="<?= (int)$selectedGame["home_team_id"] ?>"><?= h($selectedGame["home_name"]) ?></option>
                  </select>
                  <div style="height:8px"></div>
                  <label class="small">Jugador de otro equipo</label>
                  <select name="player_id" required>
                    <option value="">Seleccionar jugador</option>
                    <?php foreach ($borrowablePlayers as $p): ?>
                      <option value="<?= (int)$p["player_id"] ?>">
                        <?= h(($p["team_name"] ? $p["team_name"] . " - " : "") . ($p["number"] ? "#" . $p["number"] . " " : "") . $p["player_name"]) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div style="height:8px"></div>
                  <label class="small">Motivo</label>
                  <input name="reason" value="Evitar forfeit">
                  <div style="height:8px"></div>
                  <label class="small">Aprobado por</label>
                  <input name="approved_by" placeholder="Liga / manager / anotador">
                  <div style="height:10px"></div>
                  <button class="primary">Agregar prestado</button>
                </form>
              <?php endif; ?>
              <?php
                $selectedResultType = (string)($selectedGame["result_type"] ?? "pending");
                if (!$gameClosed && $selectedResultType === "pending") {
                  $selectedResultType = (int)($selectedGame["completed_innings"] ?? 0) >= 7 ? "innings_limit" : "time_limit";
                }
              ?>
              <form method="post" class="muted-box closure-card" id="officialValidation">
                <input type="hidden" name="action" value="save_game_status">
                <input type="hidden" name="game_id" value="<?= (int)$selectedGame["id"] ?>">
                <div class="closure-kicker">CIERRE OFICIAL DEL JUEGO</div>
                <h3>Validación oficial</h3>
                <div class="small">Regular y semifinal: 1h 45m o 7 innings, lo que llegue primero. Lluvia: juego legal desde 5 innings completos. Final/extra innings puede extenderse.</div>
                <?php if (!$gameClosed): ?>
                  <div class="notice" style="margin-top:8px">Al guardar, este juego queda cerrado, se conservan sus estadísticas y el cuaderno pasa al próximo juego abierto.</div>
                <?php endif; ?>
                <div style="height:8px"></div>
                <label class="small">Tipo de resultado</label>
                <select name="result_type">
                  <?php foreach (scorer_result_types() as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $selectedResultType === (string)$value ? "selected" : "" ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="context-grid" style="margin-top:8px">
                  <div>
                    <label class="small"><?= h($selectedGame["home_name"]) ?></label>
                    <input name="official_home_score" type="number" min="0" value="<?= (int)$selectedGame["final_home"] ?>">
                  </div>
                  <div>
                    <label class="small"><?= h($selectedGame["away_name"]) ?></label>
                    <input name="official_away_score" type="number" min="0" value="<?= (int)$selectedGame["final_away"] ?>">
                  </div>
                  <div>
                    <label class="small">Innings completos</label>
                    <input name="completed_innings" type="number" min="0" max="20" value="<?= (int)($selectedGame["completed_innings"] ?? 0) ?>">
                  </div>
                </div>
                <div style="height:8px"></div>
                <label class="small">Ganador por forfeit</label>
                <select name="forfeit_winner_team_id">
                  <option value="<?= (int)$selectedGame["home_team_id"] ?>" <?= (int)($selectedGame["forfeit_winner_team_id"] ?? 0) === (int)$selectedGame["home_team_id"] ? "selected" : "" ?>><?= h($selectedGame["home_name"]) ?></option>
                  <option value="<?= (int)$selectedGame["away_team_id"] ?>" <?= (int)($selectedGame["forfeit_winner_team_id"] ?? 0) === (int)$selectedGame["away_team_id"] ? "selected" : "" ?>><?= h($selectedGame["away_name"]) ?></option>
                </select>
                <label style="display:flex;gap:8px;align-items:center;margin-top:8px">
                  <input style="width:auto" type="checkbox" name="is_legal_game" value="1" <?= !empty($selectedGame["is_legal_game"]) ? "checked" : "" ?>>
                  <span class="small">Juego legal para récord oficial</span>
                </label>
                <div style="height:8px"></div>
                <label class="small">Motivo del cierre / nota oficial</label>
                <input name="official_result_note" value="<?= h($selectedGame["official_result_note"] ?? "") ?>" placeholder="Ej. Cerrado por tiempo 1h 45m / lluvia en el 5to / forfeit confirmado">
                <?php if ($gameClosed && trim((string)($selectedGame["official_result_note"] ?? "")) === ""): ?>
                  <div class="warning" style="margin-top:8px">Este juego está cerrado sin motivo registrado. Puedes escribir el motivo y guardar la validación.</div>
                <?php endif; ?>
                <div style="height:10px"></div>
                <button class="primary"><?= $gameClosed ? "Guardar validación" : "Cerrar juego y pasar al próximo" ?></button>
              </form>
            </div>
            <?php if (!$gameClosed && $borrowedPlayers): ?>
              <div class="muted-box" style="margin-top:12px">
                <h3>Prestados activos en este juego</h3>
                <table>
                  <thead><tr><th>Jugador</th><th>Origen</th><th>Juega con</th><th>Motivo</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach ($borrowedPlayers as $bp): ?>
                      <tr>
                        <td><?= h(($bp["number"] ? "#" . $bp["number"] . " " : "") . $bp["player_name"]) ?></td>
                        <td><?= h($bp["original_team_name"] ?? "-") ?></td>
                        <td><?= h($bp["borrowed_team_name"] ?? "-") ?></td>
                        <td><?= h($bp["reason"] ?? "") ?></td>
                        <td>
                          <form method="post" onsubmit="return confirm('¿Retirar este prestado del juego?');">
                            <input type="hidden" name="action" value="remove_borrowed_player">
                            <input type="hidden" name="game_id" value="<?= (int)$selectedGame["id"] ?>">
                            <input type="hidden" name="borrowed_id" value="<?= (int)$bp["id"] ?>">
                            <button class="danger">Retirar</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
            <?php if (!$gameClosed): ?>
              <div class="lineup-grid">
                <?php foreach ([[(int)$selectedGame["away_team_id"], $selectedGame["away_name"], $awayPlayers], [(int)$selectedGame["home_team_id"], $selectedGame["home_name"], $homePlayers]] as $teamBlock): ?>
                  <?php [$teamId, $teamName, $teamPlayers] = $teamBlock; ?>
                  <form method="post" class="muted-box lineup-form">
                    <input type="hidden" name="action" value="save_lineup">
                    <input type="hidden" name="game_id" value="<?= (int)$selectedGame["id"] ?>">
                    <input type="hidden" name="team_id" value="<?= (int)$teamId ?>">
                    <h3><?= h($teamName) ?></h3>
                    <div class="lineup-error" data-lineup-error></div>
                    <?php for ($order = 1; $order <= 15; $order++): ?>
                      <?php $savedLineup = $lineups[$teamId][$order] ?? null; ?>
                      <div class="lineup-row">
                        <div class="order-badge"><?= $order ?></div>
                        <div>
                          <label class="small">Jugador</label>
                          <select name="lineup[<?= $order ?>][player_id]">
                            <option value="">Sin asignar</option>
                            <?php foreach ($teamPlayers as $p): ?>
                              <option value="<?= (int)$p["player_id"] ?>" <?= (int)($savedLineup["player_id"] ?? 0) === (int)$p["player_id"] ? "selected" : "" ?>>
                                <?= h(($p["number"] ? "#" . $p["number"] . " " : "") . $p["player_name"] . (!empty($p["borrowed_label"]) ? " (" . $p["borrowed_label"] . ")" : "")) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div>
                          <label class="small">Pos.</label>
                          <select name="lineup[<?= $order ?>][field_position]">
                            <?php foreach ($positionOptions as $value => $label): ?>
                              <option value="<?= h($value) ?>" <?= (string)($savedLineup["field_position"] ?? "") === (string)$value ? "selected" : "" ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>
                    <?php endfor; ?>
                    <button class="primary">Guardar lineup de <?= h($teamName) ?></button>
                  </form>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <script src="/scorer/assets/scorer-lineup.js"></script>
          </section>
