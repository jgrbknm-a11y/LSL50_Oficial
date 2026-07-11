<?php
/** @var string $pageTitle */
/** @var string $pageDescription */
$pageTitle = $pageTitle ?? "Legends Softball League 50+";
$pageDescription = $pageDescription ?? "Resultados, posiciones, calendario y estadísticas oficiales LSL50.";
$leagueLogoUrl = $leagueLogoUrl ?? lsl_setting(db(), "league_logo_url", "");
?>
<!doctype html>
<html lang="es" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($pageDescription) ?>">
  <title><?= h($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ["Inter", "system-ui", "sans-serif"] },
          colors: {
            lsl: {
              bg: "#0F0F11",
              card: "#1A1A1E",
              border: "#2A2A32",
              muted: "#8B8B96",
              accent: "#F5C518",
              blue: "#3B82F6",
              green: "#22C55E",
              red: "#EF4444",
            }
          }
        }
      }
    };
  </script>
  <link rel="stylesheet" href="/public/assets/css/lsl50-public.css">
</head>
<body class="min-h-full bg-lsl-bg text-zinc-100 font-sans antialiased">
