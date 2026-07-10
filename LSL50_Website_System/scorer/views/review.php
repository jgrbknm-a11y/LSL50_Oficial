          <?php
            $scorebookPdfPath = "/output/pdf/lsl50_scorebook_game_" . (int)$selectedGame["id"] . ".pdf";
            $scorebookPdfFile = __DIR__ . "/../.." . $scorebookPdfPath;
            $resultTypeLabelsForReview = scorer_result_types();
          ?>
          <section class="card <?= $activeView === "review" ? "" : "view-hidden" ?>" id="scorebookReview">
            <div class="review-hero">
              <div>
                <h2>Consulta oficial del cuaderno</h2>
                <div class="small">Resumen auditable del juego, lineup, jugadores prestados, jugada por jugada y estadísticas guardadas.</div>
              </div>
              <div class="actions">
                <form method="post">
                  <input type="hidden" name="action" value="generate_scorebook_pdf">
                  <input type="hidden" name="game_id" value="<?= (int)$selectedGame["id"] ?>">
                  <button class="primary">Generar PDF oficial</button>
                </form>
                <?php if (file_exists($scorebookPdfFile)): ?>
                  <a class="btn soft" href="<?= h($scorebookPdfPath) ?>" target="_blank">Abrir PDF</a>
                <?php endif; ?>
              </div>
            </div>
            <div class="review-grid">
              <div class="review-tile"><span>Juego</span><strong><?= h($selectedGame["home_name"]) ?> vs <?= h($selectedGame["away_name"]) ?></strong></div>
              <div class="review-tile"><span>Marcador</span><strong><?= (int)$selectedGame["final_home"] ?> - <?= (int)$selectedGame["final_away"] ?></strong></div>
              <div class="review-tile"><span>Resultado</span><strong><?= h($resultTypeLabelsForReview[(string)($selectedGame["result_type"] ?? "pending")] ?? "Pendiente") ?></strong></div>
              <div class="review-tile"><span>Legal</span><strong><?= !empty($selectedGame["is_legal_game"]) ? "Sí" : "No" ?></strong></div>
            </div>
            <div class="audit-note">
              Motivo / nota oficial: <?= h(trim((string)($selectedGame["official_result_note"] ?? "")) !== "" ? $selectedGame["official_result_note"] : "Sin motivo registrado") ?>
            </div>

            <div class="card">
              <h3>Lineup registrado</h3>
              <div class="scroll">
                <table>
                  <thead><tr><th>Equipo</th><th>Turno</th><th>Jugador</th><th>Pos.</th><th>Nota</th></tr></thead>
                  <tbody>
                    <?php if (!$lineups): ?>
                      <tr><td colspan="5" class="small">No hay lineup guardado todavía.</td></tr>
                    <?php else: ?>
                      <?php foreach ($lineups as $teamLineup): ?>
                        <?php foreach ($teamLineup as $row): ?>
                          <tr>
                            <td><?= h($row["team_name"]) ?></td>
                            <td><?= (int)$row["batting_order"] ?></td>
                            <td><?= h(($row["number"] ? "#" . $row["number"] . " " : "") . $row["player_name"]) ?></td>
                            <td><?= h($row["field_position"] ?: "-") ?></td>
                            <td class="small"><?= !empty($row["borrowed_from_team_name"]) ? h("Prestado de " . $row["borrowed_from_team_name"]) : "-" ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <?php if ($borrowedPlayers): ?>
              <div class="card">
                <h3>Jugadores prestados</h3>
                <div class="scroll">
                  <table>
                    <thead><tr><th>Jugador</th><th>Origen</th><th>Juega con</th><th>Motivo</th><th>Aprobado por</th></tr></thead>
                    <tbody>
                      <?php foreach ($borrowedPlayers as $bp): ?>
                        <tr>
                          <td><?= h(($bp["number"] ? "#" . $bp["number"] . " " : "") . $bp["player_name"]) ?></td>
                          <td><?= h($bp["original_team_name"] ?? "-") ?></td>
                          <td><?= h($bp["borrowed_team_name"] ?? "-") ?></td>
                          <td><?= h($bp["reason"] ?? "-") ?></td>
                          <td><?= h($bp["approved_by"] ?? "-") ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endif; ?>

            <div class="card">
              <h3>Bitácora para revisión</h3>
              <div class="scroll">
                <table>
                  <thead><tr><th>#</th><th>Inn</th><th>Equipo</th><th>Bateador</th><th>Jugada</th><th>Corredores</th><th>Outs</th><th>RBI</th><th>C</th></tr></thead>
                  <tbody>
                    <?php if (!$playEvents): ?>
                      <tr><td colspan="9" class="small">Todavía no hay jugadas registradas.</td></tr>
                    <?php else: ?>
                      <?php foreach ($playEvents as $idx => $event): ?>
                        <tr>
                          <td><?= $idx + 1 ?></td>
                          <td><?= (int)$event["inning"] ?> <?= $event["half"] === "top" ? "Alta" : "Baja" ?></td>
                          <td><?= h($event["batting_team"]) ?></td>
                          <td>
                            <strong><?= h($event["batter_name"]) ?></strong>
                            <div class="small">
                              <?= in_array($event["result"], ["WP", "PB"], true) ? "Bateador continúa" : h("Destino: " . base_destination_label($event["batter_to"])) ?>
                            </div>
                          </td>
                          <td><?= h(scorer_result_label((string)$event["result"], $event["out_detail"] ?? "")) ?></td>
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
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="card scroll">
              <h3>Box score guardado</h3>
              <table>
                <thead>
                  <tr><th>Turno</th><th>Jugador</th><th>Equipo</th><th>PA</th><?php foreach ($statLabels as $label): ?><th><?= h($label) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                  <?php if (!$scorerRows): ?>
                    <tr><td colspan="<?= 4 + count($statLabels) ?>" class="small">No hay estadísticas registradas.</td></tr>
                  <?php else: ?>
                    <?php foreach ($scorerRows as $row): ?>
                      <?php
                        $hasStatLine = false;
                        foreach ($statKeys as $key) if ((int)($row[$key] ?? 0) > 0) $hasStatLine = true;
                        if (!$hasStatLine && $row["lineup_order"] === null) continue;
                      ?>
                      <tr>
                        <td><?= $row["lineup_order"] !== null ? (int)$row["lineup_order"] : "-" ?></td>
                        <td><?= h($row["player_name"]) ?></td>
                        <td><?= h($row["team_name"]) ?></td>
                        <td><?= scorer_plate_appearances($row) ?></td>
                        <?php foreach ($statKeys as $key): ?><td><?= (int)($row[$key] ?? 0) ?></td><?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
