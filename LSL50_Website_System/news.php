<?php
$id = (int)($_GET["id"] ?? 0);
if ($id > 0) {
  header("Location: /noticias/noticia-{$id}", true, 301);
  exit;
}
header("Location: /noticias");
exit;
