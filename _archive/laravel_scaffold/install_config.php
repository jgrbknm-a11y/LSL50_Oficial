<?php
error_reporting(E_ALL); ini_set('display_errors',1);

$zipPath = __DIR__ . '/config_base_laravel.zip';
$target  = __DIR__ . '/config';

if (!file_exists($zipPath)) {
  exit('<p style="color:red">❌ No se encontró config_base_laravel.zip en /public_html/</p>');
}
if (!is_dir($target)) { @mkdir($target, 0755, true); }

$zip = new ZipArchive;
if ($zip->open($zipPath) === TRUE) {
  for ($i = 0; $i < $zip->numFiles; $i++) {
    $entry = $zip->getNameIndex($i);
    if (substr($entry, -4) === '.php') {
      $contents = $zip->getFromIndex($i);
      file_put_contents($target . '/' . basename($entry), $contents);
      @chmod($target . '/' . basename($entry), 0644);
      echo "<p>✅ Copiado: ".htmlspecialchars(basename($entry))."</p>";
    }
  }
  $zip->close();
  echo "<h3 style='color:green'>✅ Config instalado en /config</h3>";
} else {
  echo '<p style="color:red">❌ No se pudo abrir el ZIP.</p>';
}