<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$k = $app->make(Illuminate\Contracts\Console\Kernel::class);
$k->bootstrap();
use Illuminate\Support\Facades\Artisan;
Artisan::call('config:cache'); echo nl2br(Artisan::output());
Artisan::call('route:cache');  echo nl2br(Artisan::output());
Artisan::call('view:cache');   echo nl2br(Artisan::output());
echo "<p>✅ Cachés listas.</p>";