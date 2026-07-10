<?php
// Muestra las últimas 150 líneas del log de Laravel
$log = __DIR__ . '/storage/logs/laravel.log';
if (!file_exists($log)) { http_response_code(404); exit("No existe laravel.log"); }
$lines = 150;
$fp = fopen($log, "r"); fseek($fp, -1, SEEK_END);
$pos = ftell($fp); $buffer = '';
while($pos > 0 && substr_count($buffer, "\n") <= $lines){
  $seek = max($pos - 2048, 0); $read = $pos - $seek;
  fseek($fp, $seek, SEEK_SET); $buffer = fread($fp, $read) . $buffer; $pos = $seek;
}
fclose($fp);
header('Content-Type: text/plain; charset=utf-8');
echo preg_replace('/password=.*?(\s|$)/i', 'password=*** ', $buffer);