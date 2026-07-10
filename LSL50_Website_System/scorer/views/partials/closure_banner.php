      <?php
        $resultTypeLabels = scorer_result_types();
        $closureLabel = $resultTypeLabels[(string)($selectedGame["result_type"] ?? "pending")] ?? "Pendiente / sin cerrar";
        $closureReason = trim((string)($selectedGame["official_result_note"] ?? ""));
      ?>
      <div class="closure-banner <?= $gameClosed ? "closed" : "open" ?>">
        <div>
          <strong><?= $gameClosed ? "Juego cerrado" : "Juego abierto" ?></strong>
          <span class="small">
            <?= h($closureLabel) ?> · Marcador <?= h($selectedGame["home_name"]) ?> <?= (int)$selectedGame["final_home"] ?> - <?= (int)$selectedGame["final_away"] ?> <?= h($selectedGame["away_name"]) ?>
            <?= !empty($selectedGame["completed_innings"]) ? h(" · " . (int)$selectedGame["completed_innings"] . " innings completos") : "" ?>
          </span>
          <?php if ($gameClosed): ?>
            <span class="closure-reason">Motivo del cierre: <?= h($closureReason !== "" ? $closureReason : "Sin motivo registrado") ?></span>
          <?php endif; ?>
        </div>
        <a class="btn <?= $gameClosed ? "danger" : "primary" ?>" href="/scorer/?game_id=<?= (int)$selectedGame["id"] ?>&view=lineups#officialValidation">
          <?= $gameClosed ? "Ver/Reabrir cierre" : "Ir a cierre oficial" ?>
        </a>
      </div>
