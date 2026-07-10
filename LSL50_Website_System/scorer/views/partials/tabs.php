      <nav class="scorer-tabs" id="scorerTabs" aria-label="Vistas del anotador">
        <?php foreach ($viewTabs as $viewKey => $tab): ?>
          <a class="scorer-tab <?= $activeView === $viewKey ? "active" : "" ?>" href="/scorer/?game_id=<?= (int)$selectedGame["id"] ?>&view=<?= h($viewKey) ?>#scorerTabs">
            <?= h($tab[0]) ?>
            <span><?= h($tab[1]) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
