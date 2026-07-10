<?php

/**
 * Load KEY=VALUE pairs from .env files into the process environment.
 * Does not override variables already set in the real environment.
 */
function lsl_load_env_files(array $paths): void
{
  foreach ($paths as $path) {
    if (!is_string($path) || $path === "" || !is_file($path) || !is_readable($path)) {
      continue;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
      continue;
    }
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === "" || str_starts_with($line, "#")) {
        continue;
      }
      if (str_starts_with($line, "export ")) {
        $line = trim(substr($line, 7));
      }
      $eq = strpos($line, "=");
      if ($eq === false) {
        continue;
      }
      $key = trim(substr($line, 0, $eq));
      $value = trim(substr($line, $eq + 1));
      if ($key === "" || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
        continue;
      }
      if ($value !== "" && ($value[0] === '"' || $value[0] === "'")) {
        $quote = $value[0];
        if (str_ends_with($value, $quote) && strlen($value) >= 2) {
          $value = substr($value, 1, -1);
          if ($quote === '"') {
            $value = stripcslashes($value);
          }
        }
      } else {
        $hash = strpos($value, " #");
        if ($hash !== false) {
          $value = rtrim(substr($value, 0, $hash));
        }
      }
      $existing = getenv($key);
      if ($existing !== false && $existing !== "") {
        continue;
      }
      putenv($key . "=" . $value);
      $_ENV[$key] = $value;
      $_SERVER[$key] = $value;
    }
  }
}

function lsl_env(string $key, ?string $default = null): ?string
{
  $value = getenv($key);
  if ($value === false || $value === "") {
    return $default;
  }
  return (string)$value;
}
