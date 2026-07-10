<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
@ini_set('memory_limit', '768M');
@ini_set('max_execution_time', '0');

echo "<h3>🧹 Limpieza manual de cachés Laravel</h3>";

$autoload = __DIR__ . '/vendor/autoload.php';
$bootstrap = __DIR__ . '/bootstrap/app.php';

if (!file_exists($autoload) || !file_exists($bootstrap)) {
    exit("<p style='color:red;'>❌ Archivos base de Laravel no encontrados.</p>");
}

require $autoload;
$app = require $bootstrap;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

try {
    Artisan::call('config:clear'); echo "<pre>".Artisan::output()."</pre>";
    Artisan::call('route:clear');  echo "<pre>".Artisan::output()."</pre>";
    Artisan::call('view:clear');   echo "<pre>".Artisan::output()."</pre>";
    Artisan::call('cache:clear');  echo "<pre>".Artisan::output()."</pre>";
    echo "<h4 style='color:green;'>✅ Cachés limpiadas correctamente.</h4>";
} catch (Throwable $e) {
    echo "<pre style='color:red;'>$e</pre>";
}