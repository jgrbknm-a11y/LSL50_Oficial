<?php
require_once __DIR__ . "/../config.php";

function save_upload($file, $prefix='file'){
  global $UPLOAD_DIR;
  $ALLOWED = [
    'image/jpeg','image/png','image/webp',
    'video/mp4','video/webm','video/ogg'
  ];
  if (empty($file['tmp_name']) || $file['error']!==UPLOAD_ERR_OK) return [null,"Subida inválida"];
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $file['tmp_name']); finfo_close($finfo);
  if (!in_array($mime, $ALLOWED)) return [null, "Tipo no permitido ($mime)"];
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: (
    str_contains($mime,'webp')?'webp' : (str_contains($mime,'jpeg')?'jpg':'bin')
  ));
  $name = $prefix . "_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;
  $dest = $UPLOAD_DIR . "/" . $name;
  if (!move_uploaded_file($file['tmp_name'], $dest)) return [null, "No se pudo guardar"];
  return ["/public/uploads/" . $name, null];
}
