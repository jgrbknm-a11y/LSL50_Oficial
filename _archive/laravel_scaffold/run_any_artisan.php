<?php
// Script seguro para ejecutar comandos Artisan sin terminal
// Guarda este archivo como run_any_artisan.php en public_html/

set_time_limit(0);

// Cargar Laravel (ajustado a tu estructura actual)
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

// Inicializar el kernel
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Obtener comando por parámetro
$cmd = $_GET['cmd'] ?? '';
if (!$cmd) {
    echo "Uso: ?cmd=nombre_del_comando<br>Ej: ?cmd=config:clear";
    exit;
}

// Ejecutar el comando
echo "<pre>> php artisan {$cmd}\n";
$status = $kernel->call($cmd);
echo $kernel->output();
echo "\nEstado: {$status}\n</pre>";

// Terminar el kernel
$kernel->terminate($app['request'], new Illuminate\Http\Response());