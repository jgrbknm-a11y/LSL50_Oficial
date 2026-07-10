<?php
require __DIR__ . "/../config.php";
require_admin();
$season = active_season(db());
include __DIR__ . "/../partials/header.php";
?>

<h1 class="text-2xl font-bold mb-4">Panel de Administración</h1>
<div class="notice">Temporada activa: <strong><?= h($season['name']) ?></strong></div>
<div class="grid md:grid-cols-2 gap-4">
  <a class="card block" href="/admin/teams.php">
    <div class="font-semibold">Equipos</div>
    <div class="small">Crear/editar equipos</div>
  </a>
  <a class="card block" href="/admin/players.php">
    <div class="font-semibold">Jugadores</div>
    <div class="small">Crear/editar jugadores</div>
  </a>
  <a class="card block" href="/admin/media.php">
    <div class="font-semibold">Media</div>
    <div class="small">Fotos y videos</div>
  </a>
  <a class="card block" href="/admin/awards.php">
    <div class="font-semibold">Reconocimientos</div>
    <div class="small">Jugador/Equipo de la semana</div>
  </a>
  <a class="card block" href="/admin/games.php">
    <div class="font-semibold">Juegos y Anotación</div>
    <div class="small">Crear juegos y registrar caja</div>
  </a>
  <a class="card block" href="/admin/scorer.php">
    <div class="font-semibold">App del Anotador</div>
    <div class="small">PIN y cuaderno separado para tablet</div>
  </a>
  <a class="card block" href="/admin/leaders.php">
    <div class="font-semibold">Líderes</div>
    <div class="small">Ofensiva y pitchers ganadores</div>
  </a>
  <a class="card block" href="/admin/schedule.php">
    <div class="font-semibold">Calendario Automático</div>
    <div class="small">Generar regular, semifinales, final y PDF</div>
  </a>
  <a class="card block" href="/admin/seasons.php">
    <div class="font-semibold">Temporadas</div>
    <div class="small">Cerrar temporada, guardar historial e iniciar una nueva</div>
  </a>
  <a class="card block" href="/admin/account.php">
    <div class="font-semibold">Cuenta</div>
    <div class="small">Contraseña y cierre de sesión del panel</div>
  </a>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>
