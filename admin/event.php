<?php
/**
 * Edición de un evento: portada, colores, moderación, QR y ZIP.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth_admin.php';
require_once dirname(__DIR__) . '/includes/qr_generator.php';

admin_require_login();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    redirect('index.php');
}

$st = $pdo->prepare('SELECT * FROM events WHERE id = ?');
$st->execute([$id]);
$event = $st->fetch();
if (!$event) {
    $_SESSION['flash'] = 'Evento no encontrado.';
    redirect('index.php');
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

/** POST acciones */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $_SESSION['flash'] = 'Sesión expirada.';
        redirect('event.php?id=' . $id);
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        $eventDate = trim((string) ($_POST['event_date'] ?? '')) ?: null;
        $music = trim((string) ($_POST['music_url'] ?? ''));
        $accent = trim((string) ($_POST['accent_color'] ?? '#c9a962'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            $accent = '#c9a962';
        }
        $countdown = isset($_POST['countdown_enabled']) ? 1 : 0;
        $slugIn = trim((string) ($_POST['slug'] ?? ''));
        $slug = $slugIn !== '' ? slugify($slugIn) : slugify($name);
        if ($slug !== $event['slug']) {
            for ($i = 0; $i < 20; $i++) {
                $chk = $pdo->prepare('SELECT id FROM events WHERE slug = ? AND id != ?');
                $chk->execute([$slug, $id]);
                if (!$chk->fetch()) {
                    break;
                }
                $slug = slugify($name) . '-' . random_code(4);
            }
        }

        $albumPass = trim((string) ($_POST['album_password'] ?? ''));
        $clearAlbum = isset($_POST['clear_album_password']);
        $albumHash = $event['album_password_hash'];
        if ($clearAlbum) {
            $albumHash = null;
        } elseif ($albumPass !== '') {
            $albumHash = password_hash($albumPass, PASSWORD_DEFAULT);
        }

        if ($name === '') {
            $_SESSION['flash'] = 'El nombre es obligatorio.';
            redirect('event.php?id=' . $id);
        }

        $pdo->prepare(
            'UPDATE events SET name=?, slug=?, event_date=?, description=?, music_url=?, countdown_enabled=?, accent_color=?, album_password_hash=?
             WHERE id=?'
        )->execute([$name, $slug, $eventDate, $desc, $music, $countdown, $accent, $albumHash, $id]);

        if (!empty($_FILES['cover']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
            $f = $_FILES['cover'];
            $kind = detect_upload_kind($f['tmp_name'], (string) $f['type']);
            if ($kind === 'image' && (int) $f['size'] <= MAX_IMAGE_BYTES) {
                $fn = 'cover_' . bin2hex(random_bytes(4)) . '.jpg';
                $dest = uploads_dir($id) . '/' . $fn;
                if (move_uploaded_file($f['tmp_name'], $dest)) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($dest) ?: 'image/jpeg';
                    $dest = compress_image_file($dest, $mime);
                    $coverPath = 'uploads/' . $id . '/' . basename($dest);
                    $pdo->prepare('UPDATE events SET cover_image = ? WHERE id = ?')->execute([$coverPath, $id]);
                }
            }
        }

        $row = $pdo->prepare('SELECT * FROM events WHERE id = ?');
        $row->execute([$id]);
        $event = $row->fetch();

        try {
            $pub = event_public_url($event);
            $qrRel = generate_event_qr_png($id, $pub);
            $pdo->prepare('UPDATE events SET qr_path = ? WHERE id = ?')->execute([$qrRel, $id]);
            $event['qr_path'] = $qrRel;
        } catch (Throwable $e) {
            $_SESSION['flash'] = 'Guardado. Advertencia QR: ' . $e->getMessage();
            redirect('event.php?id=' . $id);
        }

        $_SESSION['flash'] = 'Cambios guardados y QR actualizado.';
        redirect('event.php?id=' . $id);
    }

    if ($action === 'toggle_active') {
        $pdo->prepare('UPDATE events SET active = 1 - active WHERE id = ?')->execute([$id]);
        redirect('event.php?id=' . $id);
    }

    if ($action === 'delete_media') {
        $mid = (int) ($_POST['media_id'] ?? 0);
        $del = $pdo->prepare('SELECT stored_filename FROM media WHERE id = ? AND event_id = ?');
        $del->execute([$mid, $id]);
        $m = $del->fetch();
        if ($m) {
            $path = uploads_dir($id) . '/' . $m['stored_filename'];
            if (is_file($path)) {
                @unlink($path);
            }
            $pdo->prepare('DELETE FROM media WHERE id = ?')->execute([$mid]);
        }
        redirect('event.php?id=' . $id);
    }

    if ($action === 'regen_qr') {
        $row = $pdo->prepare('SELECT * FROM events WHERE id = ?');
        $row->execute([$id]);
        $event = $row->fetch();
        try {
            $pub = event_public_url($event);
            $qrRel = generate_event_qr_png($id, $pub);
            $pdo->prepare('UPDATE events SET qr_path = ? WHERE id = ?')->execute([$qrRel, $id]);
            $event['qr_path'] = $qrRel;
            $_SESSION['flash'] = 'QR regenerado.';
        } catch (Throwable $e) {
            $_SESSION['flash'] = 'Error QR: ' . $e->getMessage();
        }
        redirect('event.php?id=' . $id);
    }
}

$stats = $pdo->prepare('SELECT COUNT(*) AS c, COUNT(DISTINCT guest_name) AS g, COALESCE(SUM(likes_count),0) AS l FROM media WHERE event_id = ?');
$stats->execute([$id]);
$s = $stats->fetch();

$media = $pdo->prepare('SELECT * FROM media WHERE event_id = ? ORDER BY id DESC');
$media->execute([$id]);
$items = $media->fetchAll();

$publicUrl = event_public_url($event);
$qrPath = $event['qr_path'] ?? '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h((string) $event['name']) ?> — Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=<?= h((string) ASSET_VERSION) ?>">
</head>
<body class="page-admin">
  <header class="admin-header">
    <div class="wrap flex-between">
      <h1 class="section-title" style="margin:0;font-size:1.25rem"><?= h((string) $event['name']) ?></h1>
      <nav class="admin-nav">
        <a href="index.php" class="btn btn-ghost btn-sm">← Todos los eventos</a>
        <a href="logout.php" class="btn btn-outline btn-sm">Salir</a>
      </nav>
    </div>
  </header>

  <main class="wrap admin-main">
    <?php if ($flash !== ''): ?><p class="banner-ok"><?= h($flash) ?></p><?php endif; ?>

    <div class="admin-stats">
      <div class="stat-pill"><?= (int) $s['c'] ?> archivos</div>
      <div class="stat-pill"><?= (int) $s['g'] ?> invitados únicos</div>
      <div class="stat-pill"><?= (int) $s['l'] ?> reacciones</div>
      <div class="stat-pill"><?= (int) $event['active'] === 1 ? 'Activo' : 'Pausado' ?></div>
    </div>

    <section class="card-glass admin-section">
      <h2 class="section-title">Compartir y QR</h2>
      <p><a href="<?= h($publicUrl) ?>" target="_blank" rel="noopener"><?= h($publicUrl) ?></a></p>
      <p>Código: <code><?= h((string) $event['unique_code']) ?></code></p>
      <?php if ($qrPath !== '' && is_file(dirname(__DIR__) . '/' . $qrPath)): ?>
        <p><img src="../<?= h($qrPath) ?>" alt="Código QR" class="admin-qr-img"></p>
        <p><a class="btn btn-outline btn-sm" href="../<?= h($qrPath) ?>" download>Descargar PNG</a></p>
      <?php else: ?>
        <p class="form-error">QR no generado aún.</p>
      <?php endif; ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="regen_qr">
        <button type="submit" class="btn btn-ghost btn-sm">Regenerar QR</button>
      </form>
      <a class="btn btn-primary btn-sm" href="download_zip.php?id=<?= $id ?>&csrf=<?= h(csrf_token()) ?>">Descargar ZIP del evento</a>
    </section>

    <section class="card-glass admin-section">
      <h2 class="section-title">Editar evento</h2>
      <form method="post" enctype="multipart/form-data" class="admin-form-grid">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <label class="field"><span>Nombre</span><input type="text" name="name" value="<?= h((string) $event['name']) ?>" required></label>
        <label class="field"><span>Fecha</span><input type="date" name="event_date" value="<?= h((string) ($event['event_date'] ?? '')) ?>"></label>
        <label class="field"><span>Slug</span><input type="text" name="slug" value="<?= h((string) $event['slug']) ?>"></label>
        <label class="field"><span>Color acento</span><input type="text" name="accent_color" value="<?= h((string) $event['accent_color']) ?>"></label>
        <label class="field full"><span>Descripción</span><textarea name="description" rows="3"><?= h((string) ($event['description'] ?? '')) ?></textarea></label>
        <label class="field full"><span>Música URL</span><input type="url" name="music_url" value="<?= h((string) ($event['music_url'] ?? '')) ?>"></label>
        <label class="field checkbox"><input type="checkbox" name="countdown_enabled" value="1" <?= (int) $event['countdown_enabled'] === 1 ? 'checked' : '' ?>> Cuenta regresiva</label>
        <label class="field"><span>Nueva clave álbum</span><input type="password" name="album_password" autocomplete="new-password" placeholder="dejar vacío para no cambiar"></label>
        <label class="field checkbox"><input type="checkbox" name="clear_album_password" value="1"> Quitar contraseña de álbum</label>
        <label class="field full"><span>Nueva portada</span><input type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label>
        <div class="full flex gap-sm">
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
      <form method="post" class="inline-form" onsubmit="return confirm('¿Pausar o reactivar el evento?');">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="toggle_active">
        <button type="submit" class="btn btn-outline"><?= (int) $event['active'] === 1 ? 'Desactivar evento' : 'Activar evento' ?></button>
      </form>
    </section>

    <section class="admin-section">
      <h2 class="section-title">Moderación de archivos</h2>
      <div class="admin-media-grid">
        <?php foreach ($items as $m): ?>
          <?php $u = '../uploads/' . $id . '/' . $m['stored_filename']; ?>
          <div class="admin-media-card">
            <?php if ($m['file_kind'] === 'video'): ?>
              <video src="<?= h($u) ?>" controls muted playsinline></video>
            <?php else: ?>
              <a href="<?= h($u) ?>" target="_blank" rel="noopener"><img src="<?= h($u) ?>" alt=""></a>
            <?php endif; ?>
            <div class="meta">
              <small><?= h((string) $m['guest_name']) ?> · <?= h((string) $m['created_at']) ?></small>
              <form method="post" onsubmit="return confirm('¿Eliminar este archivo?');">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_media">
                <input type="hidden" name="media_id" value="<?= (int) $m['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm">Eliminar</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($items) === 0): ?><p>No hay archivos aún.</p><?php endif; ?>
    </section>
  </main>
</body>
</html>
