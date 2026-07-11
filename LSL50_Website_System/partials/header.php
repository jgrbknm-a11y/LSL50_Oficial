<?php
require_once __DIR__ . "/../config.php";
$leagueLogoUrl = "";
try { $leagueLogoUrl = lsl_setting(db(), "league_logo_url", ""); } catch (Throwable $e) { $leagueLogoUrl = ""; }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LSL50 Admin Local</title>
  <style>
    :root{--bg:#f4f6f8;--ink:#142033;--muted:#667085;--line:#d7dde5;--navy:#061b3b;--gold:#d7a72f;--card:#fff}
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}
    .top{background:var(--navy);color:white;border-bottom:4px solid var(--gold)}
    .bar{max-width:1180px;margin:0 auto;padding:14px 18px;display:flex;align-items:center;gap:18px;flex-wrap:wrap}
    .brand{font-size:24px;font-weight:900;font-style:italic;display:flex;align-items:center;gap:10px}.brand img{width:42px;height:42px;object-fit:contain;background:white;border-radius:6px;padding:2px}.nav{display:flex;gap:8px;flex-wrap:wrap}
    .nav a{color:white;text-decoration:none;font-weight:700;padding:8px 10px;border-radius:6px}.nav a:hover{background:rgba(255,255,255,.12)}
    main{max-width:1180px;margin:0 auto;padding:22px 18px}.card{background:var(--card);border:1px solid var(--line);border-radius:8px;padding:16px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
    .grid{display:grid;gap:16px}.md\:grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}
    .gap-4{gap:16px}.gap-2{gap:8px}.space-y-2>*+*{margin-top:8px}.mb-1{margin-bottom:4px}.mb-2{margin-bottom:8px}.mb-4{margin-bottom:16px}.mt-2{margin-top:8px}
    h1,h2{margin-top:0}.text-2xl{font-size:26px}.text-xl{font-size:21px}.font-bold,.font-semibold{font-weight:800}
    label{font-size:13px;color:var(--muted);font-weight:700} input,select,textarea{border:1px solid var(--line);border-radius:6px;padding:9px 10px;font:inherit;background:white}.w-full{width:100%}.w-64{width:16rem}
    .btn,.btn-primary,button{border:0;border-radius:6px;padding:9px 12px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-block}
    .btn{background:#e8edf3;color:#142033}.btn-primary{background:var(--navy);color:white}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid var(--line);padding:9px;text-align:left;vertical-align:top}.table th{font-size:12px;color:var(--muted);text-transform:uppercase}
    .small{font-size:13px;color:var(--muted)}.notice{background:#ecfdf3;border:1px solid #abefc6;color:#067647;border-radius:6px;padding:10px;margin-bottom:12px}.warning{background:#fff7e6;border:1px solid #f3c267;color:#7a4b00;border-radius:6px;padding:10px;margin-bottom:12px}.flex{display:flex}.overflow-x-auto{overflow-x:auto}hr{border:0;border-top:1px solid var(--line);margin:24px 0}
    .badge{display:inline-block;border-radius:999px;padding:4px 8px;font-size:12px;font-weight:800}.badge-ok{background:#dcfae6;color:#067647}.badge-bad{background:#fee4e2;color:#b42318}
    @media (max-width:760px){.md\:grid-cols-2{grid-template-columns:1fr}.bar{align-items:flex-start}.nav{width:100%}.w-64{width:100%}}
  </style>
</head>
<body>
  <header class="top">
    <div class="bar">
      <div class="brand"><?php if ($leagueLogoUrl): ?><img src="<?= h($leagueLogoUrl) ?>" alt="LSL50"><?php endif; ?>LSL50</div>
      <nav class="nav">
        <a href="/admin/index.php">Inicio</a>
        <a href="/admin/teams.php">Equipos</a>
        <a href="/admin/players.php">Jugadores</a>
        <a href="/admin/games.php">Juegos</a>
        <a href="/admin/leaders.php">Líderes</a>
        <a href="/admin/scorer.php">Anotador</a>
        <a href="/admin/schedule.php">Calendario</a>
        <a href="/admin/ai-publisher.php">Publicador IA</a>
        <a href="/admin/settings.php">Configuración</a>
        <a href="/admin/seasons.php">Temporadas</a>
        <a href="/admin/media.php">Media</a>
        <a href="/admin/awards.php">Reconocimientos</a>
        <?php if (function_exists("admin_logged_in") && admin_logged_in()): ?>
          <a href="/admin/account.php">Cuenta</a>
          <form method="post" action="/admin/account.php" style="display:inline;margin:0">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn" style="background:rgba(255,255,255,.12);color:white;padding:8px 10px">Salir</button>
          </form>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main>
