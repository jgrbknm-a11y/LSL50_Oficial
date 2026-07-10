<?php
/**
 * Composer Web Installer (fix HOME/COMPOSER_HOME) para hosting compartido.
 * Requiere: PHP 8.1+ con Phar y allow_url_fopen ON.
 */
@ini_set('display_errors', 1);
@ini_set('memory_limit', '1024M');
@set_time_limit(0);

echo "<pre>== Composer Web Installer (HOME fix) ==\n\n";

$base = __DIR__;
$home = $base . '/.composer';
$cache = $home . '/cache';

if (!is_dir($home)) { @mkdir($home, 0755, true); }
if (!is_dir($cache)) { @mkdir($cache, 0755, true); }

// 🔧 Variables de entorno necesarias para Composer
putenv('HOME=' . $home);
putenv('COMPOSER_HOME=' . $home);
putenv('COMPOSER_CACHE_DIR=' . $cache);

// Comprobaciones
if (!extension_loaded('phar')) exit("ERROR: Extensión Phar no habilitada.\n");
if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) exit("ERROR: allow_url_fopen debe estar ON.\n");

// Descargar composer.phar si no existe
$phar = $base . '/composer.phar';
if (!file_exists($phar)) {
  echo "Descargando composer.phar...\n";
  $url = 'https://getcomposer.org/composer-stable.phar';
  $data = @file_get_contents($url);
  if ($data === false) exit("ERROR: No se pudo descargar $url\n");
  if (@file_put_contents($phar, $data) === false) exit("ERROR: No se pudo escribir composer.phar\n");
  @chmod($phar, 0755);
  echo "OK: composer.phar descargado.\n\n";
} else {
  echo "composer.phar ya existe. Usándolo...\n\n";
}

// Cargar Composer embebido
echo "Cargando Composer...\n";
require 'phar://composer.phar/src/bootstrap.php';

use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

chdir($base);

// Comando: composer install
echo "Ejecutando: composer install --no-dev --optimize-autoloader\n";
$app = new Application();
$app->setAutoExit(false);

$input = new ArrayInput([
  'command' => 'install',
  '--no-dev' => true,
  '--optimize-autoloader' => true,
]);

$output = new BufferedOutput();
$status = $app->run($input, $output);

// Mostrar salida
echo $output->fetch();

if ($status !== 0) {
  echo "\n⚠️ Composer devolvió un código de salida $status.\n";
} else {
  echo "\n✅ Composer finalizado correctamente.\n";
}

if (is_dir($base . '/vendor') && file_exists($base . '/vendor/autoload.php')) {
  echo "✅ Se creó vendor/autoload.php.\n";
} else {
  echo "❌ No se encuentra vendor/autoload.php. Revisa el log anterior.\n";
}

echo "\n== Fin ==</pre>";