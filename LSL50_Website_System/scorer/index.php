<?php

require __DIR__ . "/bootstrap.php";

use Lsl50\Scorer\AppRouter;

$pdo = db();
$season = active_season($pdo);

(new AppRouter($pdo, $season, __DIR__))->run();
