<?php
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$cmd = isset($_GET['cmd']) ? trim($_GET['cmd']) : 'config:clear';

echo "<pre>Ejecutando comando interno: php artisan {$cmd}\n\n";

try {
    $status = $kernel->call($cmd);
    echo $kernel->output();
    echo "\nComando finalizado con código: {$status}\n";
} catch (Throwable $e) {
    echo "Error ejecutando comando:\n";
    echo $e->getMessage();
    echo "\n\nTrace:\n".$e->getTraceAsString();
}

echo "</pre>";