<?php
@set_time_limit(0);
@ini_set('display_errors', 1);
echo "<pre>Instalando dependencias con Composer...</pre>";

chdir(__DIR__);

$cmd = 'php -d allow_url_fopen=1 -r "copy(\'https://getcomposer.org/installer\', \'composer-setup.php\');"';
echo shell_exec($cmd);
echo "<pre>Descargando Composer...</pre>";

echo shell_exec('php composer-setup.php');
echo "<pre>Ejecutando Composer Install...</pre>";

echo shell_exec('php composer.phar install --no-dev --optimize-autoloader');

echo "<pre>Instalación completada.</pre>";
?>