<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

set_time_limit(0);
chdir(__DIR__);

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$cmd = $_GET['cmd'] ?? 'about';

echo "<pre>> php artisan {$cmd}\n";
try {
    $status = $kernel->call($cmd);
    echo $kernel->output();
    echo "\nEstado: {$status}\n";
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n\n";
    echo $e->getTraceAsString();
}