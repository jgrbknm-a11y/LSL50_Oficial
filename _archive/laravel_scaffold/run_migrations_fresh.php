<?php
// public_html/run_migrate_fresh.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "== Limpiando caches ==\n";
$kernel->call('config:clear');
$kernel->call('route:clear');
$kernel->call('cache:clear');

echo "== Migrando desde cero ==\n";
// Si NO quieres seeders, cambia este array por ['--force' => true]
$exitCode = $kernel->call('migrate:fresh', [
    '--seed'  => true,     // ponlo en false o elimina si no tienes seeders
    '--force' => true
]);
echo $kernel->output();
echo "\nExit code: $exitCode\n";

echo "== Optimizando ==\n";
$kernel->call('config:cache');
$kernel->call('route:cache');
echo $kernel->output();

echo "\nListo.\n";