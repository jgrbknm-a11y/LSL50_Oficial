<?php

spl_autoload_register(static function (string $class): void {
  $prefix = "Lsl50\\";
  if (!str_starts_with($class, $prefix)) {
    return;
  }
  $relative = str_replace("\\", "/", substr($class, strlen($prefix)));

  // Domain + Repository live under src/
  $srcPath = __DIR__ . "/" . $relative . ".php";
  if (is_file($srcPath)) {
    require $srcPath;
    return;
  }

  // Scorer HTTP layer lives under scorer/ (Auth, AppRouter, Controllers)
  if (str_starts_with($relative, "Scorer/")) {
    $scorerRelative = substr($relative, strlen("Scorer/"));
    $scorerPath = __DIR__ . "/../scorer/" . $scorerRelative . ".php";
    if (is_file($scorerPath)) {
      require $scorerPath;
    }
  }
});
