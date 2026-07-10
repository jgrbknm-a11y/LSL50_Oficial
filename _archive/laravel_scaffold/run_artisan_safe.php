<?php
// Ejecuta comandos Artisan sin pasar por el Kernel de Laravel (modo seguro)
set_time_limit(0);
chdir(__DIR__);
if (!file_exists('artisan')) die("No se encuentra el archivo artisan\n");
$cmd = $_GET['cmd'] ?? '';
if (!$cmd) die("Usa: ?cmd=comando\nEjemplo: ?cmd=config:clear");
echo "<pre>> php artisan $cmd\n";
passthru("php artisan $cmd 2>&1");
echo "\nListo.</pre>";