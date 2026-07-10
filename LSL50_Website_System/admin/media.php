<?php
require __DIR__ . "/../config.php"; require_admin(); require __DIR__ . "/_upload.php";
$pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (post('action')==='create') {
    $type = post('type'); $title = post('title'); $url = post('url'); $thumb = post('thumbnail_url');
    if (!empty($_FILES['upload_file']['name'])) {
      [$u,$err] = save_upload($_FILES['upload_file'], $type==='video'?'vid':'img');
      if(!$err){ $url = $u; if ($type!=='video') $type='image'; } else { flash($err); }
    }
    try {
      $stmt=$pdo->prepare("INSERT INTO media (type,title,url,thumbnail_url,featured,week_start,week_end,tags,order_index)
                           VALUES (?,?,?,?,?,?,?,?,?)");
      $stmt->execute([$type,$title,$url,$thumb, post('featured')?1:0, post('week_start')?:null, post('week_end')?:null, post('tags')?:null, (int)(post('order_index')?:0)]);
    } catch (Throwable $e) {
      // fallback sin campos extra
      $stmt=$pdo->prepare("INSERT INTO media (type,title,url,thumbnail_url,featured) VALUES (?,?,?,?,?)");
      $stmt->execute([$type,$title,$url,$thumb, post('featured')?1:0]);
    }
    flash("Media agregada");
  } elseif (post('action')==='delete') {
    $pdo->prepare("DELETE FROM media WHERE id=?")->execute([post('id')]);
    flash("Elemento eliminado");
  } elseif (post('action')==='update_order') {
    foreach ($_POST['ord'] ?? [] as $id=>$ord) {
      $pdo->prepare("UPDATE media SET order_index=? WHERE id=?")->execute([(int)$ord,(int)$id]);
    }
    flash("Orden actualizado");
  }
  header("Location: /admin/media.php"); exit;
}

$media = $pdo->query("SELECT * FROM media ORDER BY COALESCE(week_start, created_at) DESC, order_index ASC, id DESC")->fetchAll();
include __DIR__ . "/../partials/header.php"; ?>

<h1 class="text-2xl font-bold mb-4">Media</h1>
<?php flashes(); ?>

<div class="grid md:grid-cols-2 gap-4">
  <div class="card">
    <h2 class="font-semibold mb-2">Agregar</h2>
    <form method="post" enctype="multipart/form-data" class="space-y-2">
      <input type="hidden" name="action" value="create"/>
      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="block mb-1">Tipo</label>
          <select name="type" class="w-full"><option value="image">Imagen</option><option value="video">Video</option></select>
        </div>
        <div>
          <label class="block mb-1">Destacado</label>
          <select name="featured" class="w-full"><option value="0">No</option><option value="1">Sí</option></select>
        </div>
      </div>
      <div><label class="block mb-1">Título</label><input name="title" required class="w-full"/></div>

      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="block mb-1">Subir archivo</label>
          <input type="file" name="upload_file" accept="image/*,video/*" class="w-full"/>
        </div>
        <div>
          <label class="block mb-1">o URL (YouTube/Imagen)</label>
          <input name="url" class="w-full" placeholder="https://..."/>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <div><label class="block mb-1">Semana inicio</label><input type="date" name="week_start" class="w-full"/></div>
        <div><label class="block mb-1">Semana fin</label><input type="date" name="week_end" class="w-full"/></div>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <div><label class="block mb-1">Tags</label><input name="tags" class="w-full" placeholder="jornada1, equipoA"/></div>
        <div><label class="block mb-1">Orden</label><input name="order_index" type="number" value="0" class="w-full"/></div>
      </div>

      <div><label class="block mb-1">Thumbnail URL (opcional)</label><input name="thumbnail_url" class="w-full"/></div>
      <button class="btn-primary">Guardar</button>
    </form>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">Listado / Orden</h2>
    <form method="post">
      <input type="hidden" name="action" value="update_order"/>
      <table class="table">
        <thead><tr><th>Título</th><th>Semana</th><th>Tipo</th><th>Orden</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($media as $m): ?>
          <tr>
            <td><?= h($m['title']) ?></td>
            <td class="small"><?= h($m['week_start'] ?? '—') ?> → <?= h($m['week_end'] ?? '—') ?></td>
            <td><?= h($m['type']) ?></td>
            <td style="width:90px"><input name="ord[<?= (int)$m['id'] ?>]" class="w-full" type="number" value="<?= (int)($m['order_index'] ?? 0) ?>"/></td>
            <td>
              <form method="post" style="display:inline-block">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>"/>
                <button class="btn" onclick="return confirm('Eliminar?')">Eliminar</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <button class="btn mt-2">Actualizar orden</button>
    </form>
  </div>
</div>

<?php include __DIR__ . "/../partials/footer.php"; ?>