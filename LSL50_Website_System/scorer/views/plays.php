          <?php
            $destinationOptions = [
              "" => "-",
              "STAY" => "Se quedó",
              "1B" => "Avanzó a 1B",
              "2B" => "Avanzó a 2B",
              "3B" => "Avanzó a 3B",
              "H" => "Anotó",
              "OUT" => "Out",
            ];
            $batterDestinationOptions = [
              "OUT" => "Out",
              "1B" => "Llegó a 1B",
              "2B" => "Llegó a 2B",
              "3B" => "Llegó a 3B",
              "H" => "Anotó",
            ];
            $resultOptions = [
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
            ];
            $outDetailOptions = [
              "K" => "Ponche",
              "F7" => "Fly al LF",
              "F8" => "Fly al CF",
              "F9" => "Fly al RF",
              "F6" => "Fly al SS",
              "L4" => "Línea al 2B",
              "L5" => "Línea al 3B",
              "L6" => "Línea al SS",
              "P2" => "Pop al catcher",
              "P3" => "Pop a 1B",
              "G1-3" => "Rolling pitcher a 1B",
              "G3U" => "Rolling a 1B sin asistencia",
              "G4-3" => "Rolling 2B a 1B",
              "G5-3" => "Rolling 3B a 1B",
              "G6-3" => "Rolling SS a 1B",
              "6-4" => "Forzado en 2B",
              "4-6" => "Forzado en 2B",
              "5-4" => "Forzado en 2B",
              "6-4-3" => "Doble play",
              "4-6-3" => "Doble play",
              "5-4-3" => "Doble play",
              "1-6-3" => "Doble play",
              "SF" => "Fly de sacrificio",
              "SH" => "Toque de sacrificio",
              "FC" => "Fielder choice",
            ];
            $homePlayers = scorer_player_options($scorerRows, (int)$selectedGame["home_team_id"]);
            $awayPlayers = scorer_player_options($scorerRows, (int)$selectedGame["away_team_id"]);
            $currentInning = (int)($gameContext["inning"] ?? 1);
            $currentHalf = (string)($gameContext["half"] ?? "top");
            $currentBattingTeamId = (int)($gameContext["batting_team_id"] ?? $selectedGame["away_team_id"]);
            $expectedBatterId = (int)($gameContext["next_batter_id"] ?? 0);
            $baseState = scorer_base_state($playEvents, $currentInning, $currentHalf);
            $currentBattingPlayers = scorer_player_options($scorerRows, $currentBattingTeamId);
            $turnTeamName = $currentBattingTeamId === (int)$selectedGame["home_team_id"] ? $selectedGame["home_name"] : $selectedGame["away_name"];
            $turnPartLabel = $currentHalf === "bottom" ? "Baja" : "Alta";
            $lastPlayEvent = $playEvents ? $playEvents[count($playEvents) - 1] : null;
            $occupiedBases = [];
            foreach (["1B", "2B", "3B"] as $base) {
              if (!empty($baseState[$base]["name"])) $occupiedBases[] = $base . ": " . $baseState[$base]["name"];
            }
            $basesLabel = $occupiedBases ? implode(" | ", $occupiedBases) : "Bases limpias";
          ?>
          <section class="card live-console <?= $activeView === "plays" ? "" : "view-hidden" ?> <?= $gameClosed ? "game-closed" : "" ?>" id="plays">
            <h2>Cuaderno de anotación en vivo</h2>
            <div class="scorer-workbench">
              <div class="scorer-stage">
                <div class="scoreboard <?= !empty($gameContext["inning_changed_on_last_play"]) ? "next-half" : "" ?>">
                  <div class="score-team away">
                    <div>
                      <div class="score-team-label">Visitante</div>
                      <div class="score-team-name"><?= h($selectedGame["away_name"]) ?></div>
                    </div>
                    <div class="score-number away"><?= (int)$awayTotal ?></div>
                  </div>
                  <div class="score-center">
                    <div class="inning-box">
                      <span><?= !empty($gameContext["inning_changed_on_last_play"]) ? "Cambio listo" : "Entrada" ?></span>
                      <strong><?= h($turnPartLabel) ?> <?= (int)$currentInning ?></strong>
                      <span><?= h($turnTeamName) ?> al bate</span>
                      <div class="outs-dots" aria-label="Outs">
                        <?php for ($outDot = 1; $outDot <= 3; $outDot++): ?>
                          <i class="<?= $outDot <= (int)($gameContext["outs"] ?? 0) ? "active" : "" ?>"></i>
                        <?php endfor; ?>
                      </div>
                    </div>
                  </div>
                  <div class="score-team home">
                    <div class="score-number"><?= (int)$homeTotal ?></div>
                    <div>
                      <div class="score-team-label">Home club</div>
                      <div class="score-team-name"><?= h($selectedGame["home_name"]) ?></div>
                    </div>
                  </div>
                </div>
                <div class="game-strip">
                  <div class="game-pill"><span>Bateando</span><strong><?= h($turnTeamName) ?></strong></div>
                  <div class="game-pill"><span>Bateador de turno</span><strong><?= h($nextBatterName) ?></strong></div>
                  <div class="game-pill"><span>Bases</span><strong><?= h($basesLabel) ?></strong></div>
                </div>
                <div class="diamond-top-panel">
                  <div>
                    <h3>Diamante en vivo</h3>
                    <div class="compact-note">Se actualiza mientras marcas el resultado y los avances.</div>
                  </div>
                  <div class="diamond-brand-stack">
                    <div class="diamond" aria-label="Diamante en vivo">
                      <div class="base second <?= $baseState["2B"] ? "active" : "" ?>" data-live-base="2B"><span>2B</span></div>
                      <div class="base third <?= $baseState["3B"] ? "active" : "" ?>" data-live-base="3B"><span>3B</span></div>
                      <div class="base first <?= $baseState["1B"] ? "active" : "" ?>" data-live-base="1B"><span>1B</span></div>
                      <div class="plate"></div>
                    </div>
                    <div class="official-scorer-mark">
                      <img src="/scorer/lsl50_mark.png?v=2" alt="Logo oficial LSL50">
                      <span>Software oficial - Derechos reservados LSL50</span>
                    </div>
                  </div>
                  <div class="runner-names">
                    <div class="runner-chip <?= $baseState["1B"] ? "active" : "" ?>" data-live-chip="1B"><strong>1B</strong><span><?= h($baseState["1B"]["name"] ?? "Vacía") ?></span></div>
                    <div class="runner-chip <?= $baseState["2B"] ? "active" : "" ?>" data-live-chip="2B"><strong>2B</strong><span><?= h($baseState["2B"]["name"] ?? "Vacía") ?></span></div>
                    <div class="runner-chip <?= $baseState["3B"] ? "active" : "" ?>" data-live-chip="3B"><strong>3B</strong><span><?= h($baseState["3B"]["name"] ?? "Vacía") ?></span></div>
                  </div>
                </div>

                <?php if ($gameClosed): ?>
                  <div class="closed-panel">
                    <strong>Juego cerrado para anotación</strong>
                    <div>El resultado ya fue validado oficialmente. El cuaderno queda disponible para consulta, pero no permite nuevas jugadas ni correcciones hasta reabrirlo desde Validación oficial.</div>
                    <div style="margin-top:8px"><strong style="font-size:14px">Motivo del cierre</strong><?= h(trim((string)($selectedGame["official_result_note"] ?? "")) !== "" ? $selectedGame["official_result_note"] : "Sin motivo registrado") ?></div>
                  </div>
                <?php endif; ?>

                <form method="post" id="playForm" data-expected-batter-id="<?= (int)$expectedBatterId ?>">
              <input type="hidden" name="action" value="save_play">
              <input type="hidden" name="game_id" value="<?= (int)$selectedGame["id"] ?>">
              <input type="hidden" name="current_outs" value="<?= (int)($gameContext["outs"] ?? 0) ?>">
                  <div class="stage-body">
                    <div class="scorer-focus">
                    <div class="step-card current-batter-card">
                      <h3 class="step-title"><span class="step-badge">1</span>Bateador de turno</h3>
                      <div class="context-grid">
                        <div>
                          <label class="small">Inning</label>
                          <input name="inning" type="number" min="1" value="<?= (int)$currentInning ?>">
                        </div>
                        <div>
                          <label class="small">Parte</label>
                          <select name="half">
                            <option value="top" <?= $currentHalf === "top" ? "selected" : "" ?>>Alta</option>
                            <option value="bottom" <?= $currentHalf === "bottom" ? "selected" : "" ?>>Baja</option>
                          </select>
                        </div>
                        <div>
                          <label class="small">Equipo bateando</label>
                          <select name="batting_team_id">
                            <option value="<?= (int)$selectedGame["away_team_id"] ?>" <?= $currentBattingTeamId === (int)$selectedGame["away_team_id"] ? "selected" : "" ?>><?= h($selectedGame["away_name"]) ?></option>
                            <option value="<?= (int)$selectedGame["home_team_id"] ?>" <?= $currentBattingTeamId === (int)$selectedGame["home_team_id"] ? "selected" : "" ?>><?= h($selectedGame["home_name"]) ?></option>
                          </select>
                        </div>
                      </div>
                      <div style="height:10px"></div>
                      <div class="locked-batter">
                        <label class="small">Bateador</label>
                        <?php if ($expectedBatterId): ?><input type="hidden" name="batter_id" value="<?= (int)$expectedBatterId ?>"><?php endif; ?>
                        <?php if ($expectedBatterId): ?><strong><?= h($nextBatterName) ?><?= !empty($gameContext["next_order"]) ? h(" | Turno #" . (int)$gameContext["next_order"]) : "" ?></strong><?php endif; ?>
                        <select <?= $expectedBatterId ? "disabled" : 'name="batter_id"' ?> data-batter-display style="<?= $expectedBatterId ? "display:none" : "" ?>">
                          <optgroup label="<?= h($selectedGame["away_name"]) ?>">
                            <?php foreach ($awayPlayers as $p): ?><option data-team-id="<?= (int)$p["team_id"] ?>" value="<?= (int)$p["player_id"] ?>" <?= $expectedBatterId === (int)$p["player_id"] ? "selected" : "" ?>><?= h(($p["number"] ? "#" . $p["number"] . " " : "") . $p["player_name"]) ?></option><?php endforeach; ?>
                          </optgroup>
                          <optgroup label="<?= h($selectedGame["home_name"]) ?>">
                            <?php foreach ($homePlayers as $p): ?><option data-team-id="<?= (int)$p["team_id"] ?>" value="<?= (int)$p["player_id"] ?>" <?= $expectedBatterId === (int)$p["player_id"] ? "selected" : "" ?>><?= h(($p["number"] ? "#" . $p["number"] . " " : "") . $p["player_name"]) ?></option><?php endforeach; ?>
                          </optgroup>
                        </select>
                        <div class="compact-note"><?= $expectedBatterId ? "Bloqueado por lineup. Al guardar la jugada se activa el próximo bateador." : "Sin lineup guardado: selección manual permitida." ?></div>
                      </div>
                    </div>

                    <div class="step-card result-card">
                      <h3 class="step-title"><span class="step-badge">2</span>Resultado principal</h3>
                      <div class="result-groups" data-result-buttons>
                        <div class="result-group">
                          <div class="result-group-title">Hits</div>
                          <div class="result-group-buttons">
                            <button type="button" class="result-chip hit" data-result-value="1B">1B</button>
                            <button type="button" class="result-chip power" data-result-value="2B">2B</button>
                            <button type="button" class="result-chip power" data-result-value="3B">3B</button>
                            <button type="button" class="result-chip hr" data-result-value="HR">HR</button>
                          </div>
                        </div>
                        <div class="result-group">
                          <div class="result-group-title">Bases y turnos especiales</div>
                          <div class="result-group-buttons">
                            <button type="button" class="result-chip hit" data-result-value="BB">BB</button>
                            <button type="button" class="result-chip hit" data-result-value="BB" data-note="Base por bolas intencional">IBB</button>
                            <button type="button" class="result-chip hit" data-result-value="HBP">HBP</button>
                            <button type="button" class="result-chip hit" data-result-value="E" data-note="Interferencia / obstrucción, bateador a 1B">INT</button>
                            <button type="button" class="result-chip special" data-result-value="PB">PB</button>
                            <button type="button" class="result-chip special" data-result-value="WP">WP</button>
                          </div>
                        </div>
                        <div class="result-group">
                          <div class="result-group-title">Ponches y elevados</div>
                          <div class="result-group-buttons">
                            <button type="button" class="result-chip out" data-result-value="SO" data-out-detail="K">K</button>
                            <button type="button" class="result-chip out" data-result-value="SO" data-out-detail="ꓘ" data-note="Ponche mirando">Kc</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="F7">F7</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="F8">F8</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="F9">F9</button>
                            <button type="button" class="result-chip out" data-result-value="SF" data-out-detail="SF">SF</button>
                          </div>
                        </div>
                        <div class="result-group">
                          <div class="result-group-title">Líneas y popups</div>
                          <div class="result-group-buttons">
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="L4">L4</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="L5">L5</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="L6">L6</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="P2">P2</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="P3">P3</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="P6">P6</button>
                          </div>
                        </div>
                        <div class="result-group">
                          <div class="result-group-title">Roletazos</div>
                          <div class="result-group-buttons">
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="G1-3">1-3</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="G3U">3U</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="G4-3">4-3</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="G5-3">5-3</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="G6-3">6-3</button>
                            <button type="button" class="result-chip out" data-result-value="FC" data-out-detail="FC">FC</button>
                          </div>
                        </div>
                        <div class="result-group">
                          <div class="result-group-title">Forzados y doble play</div>
                          <div class="result-group-buttons">
                            <button type="button" class="result-chip out" data-result-value="FC" data-out-detail="6-4" data-outs="1">6-4</button>
                            <button type="button" class="result-chip out" data-result-value="FC" data-out-detail="4-6" data-outs="1">4-6</button>
                            <button type="button" class="result-chip out" data-result-value="FC" data-out-detail="5-4" data-outs="1">5-4</button>
                            <button type="button" class="result-chip out" data-result-value="FC" data-out-detail="5-2" data-outs="1">5-2</button>
                            <button type="button" class="result-chip out" data-result-value="FC" data-out-detail="1-2" data-outs="1">1-2</button>
                            <button type="button" class="result-chip out" data-result-value="FC" data-out-detail="3-2" data-outs="1">3-2</button>
                            <button type="button" class="result-chip special" data-result-value="OUT" data-out-detail="6-4-3" data-outs="2">DP 6-4-3</button>
                            <button type="button" class="result-chip special" data-result-value="OUT" data-out-detail="4-6-3" data-outs="2">DP 4-6-3</button>
                            <button type="button" class="result-chip special" data-result-value="OUT" data-out-detail="5-4-3" data-outs="2">DP 5-4-3</button>
                            <button type="button" class="result-chip special" data-result-value="OUT" data-out-detail="5-2-3" data-outs="2">DP 5-2-3</button>
                            <button type="button" class="result-chip special" data-result-value="OUT" data-out-detail="1-2-3" data-outs="2">DP 1-2-3</button>
                            <button type="button" class="result-chip special" data-result-value="OUT" data-out-detail="3-2-3" data-outs="2">DP 3-2-3</button>
                          </div>
                        </div>
                        <div class="result-group">
                          <div class="result-group-title">Errores</div>
                          <div class="result-group-buttons">
                            <?php foreach (["E1", "E2", "E3", "E4", "E5", "E6", "E7", "E8", "E9"] as $errorCode): ?>
                              <button type="button" class="result-chip error" data-result-value="E" data-note="<?= h($errorCode) ?>"><?= h($errorCode) ?></button>
                            <?php endforeach; ?>
                          </div>
                        </div>
                        <div class="result-group">
                          <div class="result-group-title">Sacrificios y corredor</div>
                          <div class="result-group-buttons">
                            <button type="button" class="result-chip special" data-result-value="SH" data-out-detail="SH">SH</button>
                            <button type="button" class="result-chip special" data-result-value="SF" data-out-detail="SF">SF</button>
                            <button type="button" class="result-chip special" data-result-value="SB" data-note="Robo de base">SB</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="CS" data-note="Atrapado robando">CS</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="PK" data-note="Pickoff / sorprendido fuera de base">PK</button>
                            <button type="button" class="result-chip out" data-result-value="OUT" data-out-detail="TOOTBLAN" data-note="Corredor out en las bases">OBR</button>
                          </div>
                        </div>
                        <div class="result-group">
                          <div class="result-group-title">Producción</div>
                          <div class="result-group-buttons">
                            <button type="button" class="result-chip production" data-adjust="rbi" data-amount="1">RBI +1</button>
                            <button type="button" class="result-chip production" data-adjust="run" data-amount="1">Carrera +1</button>
                          </div>
                        </div>
                      </div>
                      <label class="small">Resultado completo</label>
                      <select name="result">
                        <?php foreach ($resultOptions as $value => $label): ?>
                          <option value="<?= h($value) ?>"><?= h($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    </div>

                    <div class="step-card">
                      <h3 class="step-title"><span class="step-badge">3</span>Corredores</h3>
                      <div class="advancement-alert" data-advancement-alert></div>
                      <div class="runner-board">
                        <?php foreach ([["1b", "1B", $baseState["1B"] ?? null], ["2b", "2B", $baseState["2B"] ?? null], ["3b", "3B", $baseState["3B"] ?? null]] as $runnerBlock): ?>
                          <?php [$baseKey, $baseLabel, $baseRunner] = $runnerBlock; ?>
                          <div class="runner-row <?= empty($baseRunner) ? "empty" : "" ?>">
                            <div class="runner-base"><?= h($baseLabel) ?></div>
                            <div>
                              <label class="small">Antes de la jugada</label>
                              <select name="runner_<?= h($baseKey) ?>_id">
                                <option value="">Vacía</option>
                                <?php foreach ($scorerRows as $p): ?><option data-team-id="<?= (int)$p["team_id"] ?>" value="<?= (int)$p["player_id"] ?>" <?= (int)($baseRunner["id"] ?? 0) === (int)$p["player_id"] ? "selected" : "" ?>><?= h($p["team_name"] . " - " . $p["player_name"]) ?></option><?php endforeach; ?>
                              </select>
                            </div>
                            <div>
                              <label class="small">Terminó</label>
                              <select name="runner_<?= h($baseKey) ?>_to">
                                <?php foreach ($destinationOptions as $value => $label): ?><option value="<?= h($value) ?>"><?= h($label) ?></option><?php endforeach; ?>
                              </select>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <div class="step-card">
                      <h3 class="step-title"><span class="step-badge">4</span>Confirmación</h3>
                      <div class="play-preview" data-play-preview>Selecciona el resultado y confirma los corredores antes de guardar.</div>
                      <div style="height:10px"></div>
                      <div class="play-grid">
                        <div>
                          <label class="small">Bateador terminó</label>
                          <select name="batter_to">
                            <?php foreach ($batterDestinationOptions as $value => $label): ?><option value="<?= h($value) ?>"><?= h($label) ?></option><?php endforeach; ?>
                          </select>
                          <div class="auto-note" data-batter-destination-note>Destino automático según el resultado.</div>
                        </div>
                        <div>
                          <label class="small">Outs</label>
                          <input name="outs_on_play" type="number" min="0" max="3" value="0">
                        </div>
                        <div>
                          <label class="small">Detalle del out</label>
                          <input name="out_detail" list="outDetailOptions" placeholder="K, F8, G6-3">
                          <datalist id="outDetailOptions">
                            <?php foreach ($outDetailOptions as $value => $label): ?>
                              <option value="<?= h($value) ?>"><?= h($label) ?></option>
                            <?php endforeach; ?>
                          </datalist>
                          <div class="auto-note" data-out-detail-note>Código del cuaderno tradicional.</div>
                        </div>
                        <div>
                          <label class="small">RBI</label>
                          <input name="rbi" type="number" min="0" value="0">
                        </div>
                        <div>
                          <label class="small">Carreras</label>
                          <input name="runs_scored" type="number" min="0" value="0">
                        </div>
                      </div>
                      <div style="height:10px"></div>
                      <label class="small">Notas</label>
                      <input name="notes" placeholder="Detalle opcional de la jugada">
                    </div>
                  </div>
                  <div class="scorer-actions">
                    <span class="compact-note">Revisa el diamante y los avances antes de guardar.</span>
                    <button class="primary">Guardar jugada</button>
                  </div>
                </form>
              </div>

              <div class="scorer-side">
                <?php if ($lastPlayEvent): ?>
                  <?php
                    $lastLabel = ($resultOptions[$lastPlayEvent["result"]] ?? $lastPlayEvent["result"]);
                    if (!empty($lastPlayEvent["out_detail"])) $lastLabel .= " (" . $lastPlayEvent["out_detail"] . ")";
                  ?>
                  <div class="side-panel last-play">
                    <h3>Última jugada</h3>
                    <strong><?= h($lastPlayEvent["batter_name"]) ?></strong>
                    <div class="small"><?= h($lastLabel) ?> | Outs: <?= (int)$lastPlayEvent["outs_on_play"] ?> | C: <?= (int)$lastPlayEvent["runs_scored"] ?></div>
                    <form method="post" class="undo-form" onsubmit="return confirm('¿Deshacer la última jugada guardada?');">
                      <input type="hidden" name="action" value="delete_play">
                      <input type="hidden" name="game_id" value="<?= (int)$selectedGame["id"] ?>">
                      <input type="hidden" name="play_id" value="<?= (int)$lastPlayEvent["id"] ?>">
                      <button class="danger">Deshacer última jugada</button>
                    </form>
                  </div>
                <?php endif; ?>

                <details class="secondary-toggle">
                  <summary>Corredor emergente</summary>
                  <div class="toggle-body">
                    <form method="post" class="grid" id="courtesyRunnerForm">
                      <input type="hidden" name="action" value="save_courtesy_runner">
                      <input type="hidden" name="game_id" value="<?= (int)$selectedGame["id"] ?>">
                      <input type="hidden" name="runner_out_id" value="">
                      <div>
                        <label class="small">Base ocupada</label>
                        <select name="base">
                          <option value="">Seleccionar base</option>
                          <?php foreach (["1B", "2B", "3B"] as $base): ?>
                            <?php if (!empty($baseState[$base]["id"])): ?>
                              <option value="<?= h($base) ?>" data-runner-id="<?= (int)$baseState[$base]["id"] ?>"><?= h($base . " - " . $baseState[$base]["name"]) ?></option>
                            <?php endif; ?>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div>
                        <label class="small">Entra corredor</label>
                        <select name="runner_in_id">
                          <option value="">Seleccionar jugador</option>
                          <?php foreach ($currentBattingPlayers as $p): ?>
                            <option value="<?= (int)$p["player_id"] ?>"><?= h(($p["number"] ? "#" . $p["number"] . " " : "") . $p["player_name"]) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div>
                        <label class="small">Notas</label>
                        <input name="notes" placeholder="Opcional">
                      </div>
                      <button class="primary" <?= empty($baseState["1B"]) && empty($baseState["2B"]) && empty($baseState["3B"]) ? "disabled" : "" ?>>Guardar corredor</button>
                    </form>
                  </div>
                </details>
              </div>
            </div>
            <script src="/scorer/assets/scorer-courtesy.js"></script>
            <script src="/scorer/assets/scorer-plays.js"></script>
          </section>

          <section class="card history-card <?= $activeView === "plays" ? "" : "view-hidden" ?>">
            <h2>Bitácora de jugadas</h2>
            <?php if (!$playEvents): ?>
              <div class="muted-box small">Todavía no hay jugadas registradas. El control empieza cuando guardes la primera jugada.</div>
            <?php else: ?>
              <div class="scroll">
                <table>
                  <thead><tr><th>Inn</th><th>Equipo</th><th>Bateador</th><th>Resultado</th><th>Corredores</th><th>Outs</th><th>RBI</th><th>C</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach ($playEvents as $event): ?>
                      <?php
                        $eventResultLabel = $event["result"] === "CR" ? "Corredor emergente" : ($resultOptions[$event["result"]] ?? $event["result"]);
                        if (!empty($event["out_detail"])) $eventResultLabel .= " (" . $event["out_detail"] . ")";
                      ?>
                      <tr>
                        <td><?= (int)$event["inning"] ?> <?= $event["half"] === "top" ? "Alta" : "Baja" ?></td>
                        <td><?= h($event["batting_team"]) ?></td>
                        <?php if ($event["result"] === "CR"): ?>
                          <td><strong><?= h($event["batter_name"]) ?></strong><br><span class="small">Entra como corredor emergente en <?= h($event["batter_to"]) ?></span></td>
                        <?php elseif (in_array($event["result"], ["WP", "PB"], true)): ?>
                          <td><strong><?= h($event["batter_name"]) ?></strong><br><span class="small">Bateador continúa en turno</span></td>
                        <?php else: ?>
                          <td><strong><?= h($event["batter_name"]) ?></strong><br><span class="small">Bateador: <?= h(base_destination_label($event["batter_to"])) ?></span></td>
                        <?php endif; ?>
                        <td><?= h($eventResultLabel) ?></td>
                        <td class="small">
                          <?php if ($event["result"] === "CR"): ?>
                            <?php if ($event["runner_1b_id"]): ?>Sale de 1B: <?= h($event["runner_1b_name"]) ?><br><?php endif; ?>
                            <?php if ($event["runner_2b_id"]): ?>Sale de 2B: <?= h($event["runner_2b_name"]) ?><br><?php endif; ?>
                            <?php if ($event["runner_3b_id"]): ?>Sale de 3B: <?= h($event["runner_3b_name"]) ?><br><?php endif; ?>
                          <?php else: ?>
                            <?php if ($event["runner_1b_id"]): ?>1B: <?= h($event["runner_1b_name"]) ?> → <?= h(base_destination_label($event["runner_1b_to"])) ?><br><?php endif; ?>
                            <?php if ($event["runner_2b_id"]): ?>2B: <?= h($event["runner_2b_name"]) ?> → <?= h(base_destination_label($event["runner_2b_to"])) ?><br><?php endif; ?>
                            <?php if ($event["runner_3b_id"]): ?>3B: <?= h($event["runner_3b_name"]) ?> → <?= h(base_destination_label($event["runner_3b_to"])) ?><br><?php endif; ?>
                          <?php endif; ?>
                          <?= h($event["notes"] ?: "") ?>
                        </td>
                        <td><?= (int)$event["outs_on_play"] ?></td>
                        <td><?= (int)$event["rbi"] ?></td>
                        <td><?= (int)$event["runs_scored"] ?></td>
                        <td>
                          <form method="post">
                            <input type="hidden" name="action" value="delete_play">
                            <input type="hidden" name="game_id" value="<?= (int)$selectedGame["id"] ?>">
                            <input type="hidden" name="play_id" value="<?= (int)$event["id"] ?>">
                            <button class="danger">X</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </section>
