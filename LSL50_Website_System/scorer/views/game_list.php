      <aside class="card">
        <h2>Juegos</h2>
        <?php if ($gameDateSearchEnabled): ?>
          <form method="get" class="game-search">
            <input type="hidden" name="view" value="review">
            <label class="small">Buscar por fecha</label>
            <div class="game-search-row">
              <input name="game_date" value="<?= h($gameDateFilter ? scorer_format_short_date($gameDateFilter) : $gameDateFilterRaw) ?>" placeholder="MM/DD/AA" inputmode="numeric">
              <button class="primary">Buscar</button>
            </div>
            <div class="actions" style="margin-top:8px">
              <span class="small">Formato: MM/DD/AA</span>
              <?php if ($gameDateFilterRaw !== ""): ?>
                <a class="btn soft" href="/scorer/?view=review#scorerTabs">Limpiar</a>
              <?php endif; ?>
            </div>
            <?php if ($gameDateFilterMessage): ?>
              <div class="warning" style="margin-top:8px"><?= h($gameDateFilterMessage) ?></div>
            <?php elseif ($gameDateFilter): ?>
              <div class="notice" style="margin-top:8px">Mostrando juegos del <?= h(scorer_format_short_date($gameDateFilter)) ?></div>
            <?php endif; ?>
          </form>
        <?php endif; ?>
        <?php if (!$games): ?>
          <div class="warning"><?= $gameDateFilter ? "No hay juegos para esa fecha." : "No hay juegos creados en la temporada activa." ?></div>
        <?php endif; ?>
        <div class="grid">
          <?php foreach ($games as $g): ?>
            <?php
              $listedClosed = scorer_game_is_closed($g);
              $gameListView = $gameDateSearchEnabled ? "review" : "plays";
              $gameListAnchor = $gameDateSearchEnabled ? "scorebookReview" : "plays";
              $gameDateQuery = $gameDateFilter ? "&game_date=" . rawurlencode(scorer_format_short_date($gameDateFilter)) : "";
            ?>
            <a class="btn <?= $selectedGame && (int)$selectedGame["id"] === (int)$g["id"] ? "primary" : "soft" ?>" href="/scorer/?game_id=<?= (int)$g["id"] ?>&view=<?= h($gameListView) ?><?= $gameDateQuery ?>#<?= h($gameListAnchor) ?>">
              <span class="game-card-date"><?= h(scorer_format_short_date($g["game_date"])) ?></span>
              <span class="game-card-match">
                <?= h($g["home_name"]) ?> vs <?= h($g["away_name"]) ?>
                <span class="game-card-time"><?= h(scorer_game_time_label($g["location"] ?? "")) ?></span>
              </span>
              <span class="small game-card-status"><?= $listedClosed ? "Cerrado · " : "Abierto · " ?><?= (int)$g["final_home"] ?> - <?= (int)$g["final_away"] ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </aside>
