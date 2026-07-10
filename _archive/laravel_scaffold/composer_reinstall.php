<?php
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '1024M');

chdir(__DIR__);

function out($m){ echo $m."\n"; @ob_flush(); @flush(); }

echo "<pre>== REINSTALAR DEPENDENCIAS (VENDOR) ==\n\n";

// 1) Descargar composer.phar si no existe
if (!file_exists('composer.phar')) {
    out("Descargando composer.phar...");
    $installer = 'https://getcomposer.org/installer';
    $setup = file_get_contents($installer);
    if(!$setup){ out("ERROR: No se pudo descargar el instalador."); exit; }
    file_put_contents('composer-setup.php', $setup);
    @unlink('composer.phar');
    // Ejecutar instalador
    $res = @passthru('php composer-setup.php 2>&1');
    @unlink('composer-setup.php');
    if (!file_exists('composer.phar')) {
        out("ERROR: No se creó composer.phar. Tu hosting podría bloquear exec/passthru.");
    } else {
        out("composer.phar OK");
    }
} else {
    out("composer.phar ya existe");
}

// 2) Borrar vendor actual (si está corrupto)
if (is_dir('vendor')) {
    out("Renombrando vendor -> vendor_old ...");
    @rename('vendor', 'vendor_old_'.date('Ymd_His'));
} else {
    out("No había vendor, continuamos.");
}

// 3) Intentar instalar dependencias
if (file_exists('composer.phar')) {
    out("\nEjecutando: php -d allow_url_fopen=1 composer.phar install --no-dev --optimize-autoloader");
    @passthru('php -d allow_url_fopen=1 composer.phar install --no-dev --optimize-autoloader 2>&1');
} else {
    out("\nNo tengo composer.phar para ejecutar install.");
}

echo "\n\n== FIN ==</pre>";