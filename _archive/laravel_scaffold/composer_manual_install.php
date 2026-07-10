<?php
@ini_set('memory_limit', '1024M');
@ini_set('max_execution_time', '0');
@set_time_limit(0);
header('Content-Type: text/plain; charset=utf-8');

function line($msg = '') { echo $msg . PHP_EOL; @ob_flush(); @flush(); }
function has($fn) { return function_exists($fn) && stripos(ini_get('disable_functions'), $fn) === false; }
function run($cmd, $cwd = null) {
    $cwd = $cwd ?: getcwd();
    line("> " . $cmd);
    if (has('proc_open')) {
        $spec = [0 => ["pipe","r"], 1 => ["pipe","w"], 2 => ["pipe","w"]];
        $p = proc_open($cmd, $spec, $pipes, $cwd);
        if (is_resource($p)) {
            fclose($pipes[0]);
            while (!feof($pipes[1])) { line(trim(fgets($pipes[1]))); }
            while (!feof($pipes[2])) { line(trim(fgets($pipes[2]))); }
            $code = proc_close($p);
            line("Exit code: $code");
            return $code === 0;
        }
    }
    if (has('system'))  { system($cmd, $code); return $code === 0; }
    if (has('exec'))    { exec($cmd, $out, $code); foreach ($out as $l) line($l); return $code === 0; }
    if (has('passthru')){ passthru($cmd, $code); return $code === 0; }
    if (has('shell_exec')) { line(shell_exec($cmd . ' 2>&1')); return true; }
    line("No hay funciones de ejecución habilitadas en este servidor.");
    return false;
}

$root = getcwd();
line("== Instalador Laravel (modo seguro Hostinger) ==");
line("Ruta actual: $root");
line(str_repeat('=', 60));

// 1) Composer local
$composer = $root . '/composer.phar';
if (!file_exists($composer)) {
    line("Descargando composer.phar...");
    $data = @file_get_contents('https://getcomposer.org/download/latest-stable/composer.phar');
    if ($data === false) { line("❌ No se pudo descargar composer.phar."); exit(1); }
    file_put_contents($composer, $data);
    @chmod($composer,0755);
}
line("✔ composer.phar listo.");

// 2) Crear directorio temporal local
$tmpDir = $root . '/.composer_tmp';
if (!is_dir($tmpDir)) mkdir($tmpDir,0755,true);
putenv('COMPOSER_HOME=' . $tmpDir);
putenv('HOME=' . $tmpDir);
line("✔ Directorio temporal definido: $tmpDir");

// 3) Ejecutar composer install con variable inline
$cmdInstall = 'HOME="'.$tmpDir.'" COMPOSER_HOME="'.$tmpDir.'" php composer.phar install --no-dev --prefer-dist --no-interaction --optimize-autoloader';
line("Ejecutando composer install (modo local)...");
if (!run($cmdInstall, $root)) {
    line("❌ Composer falló nuevamente. Este servidor tiene funciones PHP restringidas.");
    line("➡️ Opción alternativa: instala manualmente las dependencias en tu PC y sube la carpeta /vendor.");
    exit(1);
}
line("✅ Dependencias instaladas correctamente.");

// 4) Artisan setup
if (file_exists($root.'/artisan')) {
    run('php artisan key:generate --force', $root);
    run('php artisan config:cache', $root);
    run('php artisan migrate --force', $root);
}

line(str_repeat('=', 60));
line("🎉 Instalación completa (modo manual). Elimina este archivo por seguridad.");
