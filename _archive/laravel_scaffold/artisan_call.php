<?php
// Ejecuta comandos Artisan desde PHP sin depender del archivo "artisan".
set_time_limit(0);
chdir(__DIR__);

// Carga Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';

// Kernel de consola
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Comando recibido por query string, p. ej.: ?cmd=config:clear
$cmd = $_GET['cmd'] ?? '';
if (!$cmd) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Usa: ?cmd=nombre_del_comando\nEjemplos:\n  ?cmd=config:clear\n  ?cmd=cache:clear\n  ?cmd=route:clear\n  ?cmd=view:clear\n  ?cmd=config:cache\n";
    exit;
}

// Ejecuta y muestra salida
header('Content-Type: text/plain; charset=utf-8');
echo "> php artisan {$cmd}\n";
$status = $kernel->call($cmd);
echo $kernel->output();
echo "\nEstado: {$status}\n";