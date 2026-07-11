<?php

function lsl_public_fmt_record(int $wins, int $losses, int $ties = 0): string
{
  $record = "{$wins}-{$losses}";
  if ($ties > 0) {
    $record .= "-{$ties}";
  }
  return $record;
}

function lsl_public_fmt_avg(float $value): string
{
  $formatted = number_format($value, 3, ".", "");
  return str_starts_with($formatted, "0") ? substr($formatted, 1) : $formatted;
}

function lsl_public_fmt_pct(int $wins, int $losses, int $ties = 0): string
{
  $gp = $wins + $losses + $ties;
  if ($gp === 0) {
    return ".000";
  }
  return lsl_public_fmt_avg($wins / $gp);
}

function lsl_public_fmt_date(?string $date, string $pattern = "M j, Y"): string
{
  if (!$date) {
    return "";
  }
  try {
    return (new DateTimeImmutable($date))->format($pattern);
  } catch (Throwable $e) {
    return (string)$date;
  }
}

function lsl_public_fmt_date_es(?string $date): string
{
  if (!$date) {
    return "";
  }
  try {
    $dt = new DateTimeImmutable($date);
    $days = ["dom.", "lun.", "mar.", "mié.", "jue.", "vie.", "sáb."];
    $months = ["ene.", "feb.", "mar.", "abr.", "may.", "jun.", "jul.", "ago.", "sep.", "oct.", "nov.", "dic."];
    $dow = $days[(int)$dt->format("w")];
    $mon = $months[(int)$dt->format("n") - 1];
    return "{$dow} {$dt->format("j")} {$mon} {$dt->format("Y")}";
  } catch (Throwable $e) {
    return (string)$date;
  }
}

function lsl_public_leader_initial(string $name): string
{
  $name = trim($name);
  return $name !== "" ? mb_strtoupper(mb_substr($name, 0, 1)) : "-";
}

function lsl_public_active_nav(string $path): string
{
  $uri = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/";
  $uri = rtrim($uri, "/") ?: "/";
  $path = rtrim($path, "/") ?: "/";
  return $uri === $path ? " is-active" : "";
}

function lsl_public_news_slug(string $title, int $id): string
{
  $slug = strtolower(trim($title));
  $slug = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $slug) ?: $slug;
  $slug = preg_replace("/[^a-z0-9]+/", "-", $slug) ?: "noticia";
  return trim($slug, "-") . "-" . $id;
}
