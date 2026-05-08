<?php
/**
 * Panel admin: listado de eventos, alta rápida y accesos.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth_admin.php';
require_once dirname(__DIR__) . '/includes/qr_generator.php';

admin_require_login();

$pdo = db();
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

/** Crear evento (POST) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $_SESSION['flash'] = 'Sesión expirada. Intenta nuevamente.';
        redirect('index.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $desc = trim((string) ($_POST['description'] ?? ''));
    $eventDate = trim((string) ($_POST['event_date'] ?? '')) ?: null;
    $slugIn = trim((string) ($_POST['slug'] ?? ''));
    $music = trim((string) ($_POST['music_url'] ?? ''));
    $accent = trim((string) ($_POST['accent_color'] ?? '#c9a962'));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
        $accent = '#c9a962';
    }
    $countdown = isset($_POST['countdown_enabled']) ? 1 : 0;
    $albumPass = trim((string) ($_POST['album_password'] ?? ''));
    $albumHash = $albumPass !== '' ? password_hash($albumPass, PASSWORD_DEFAULT) : null;

    if ($name === '') {
        $_SESSION['flash'] = 'El nombre del evento es obligatorio.';
        redirect('index.php');
    }

    $baseSlug = $slugIn !== '' ? slugify($slugIn) : slugify($name);
    $slug = $baseSlug;
    for ($i = 0; $i < 20; $i++) {
        $chk = $pdo->prepare('SELECT id FROM events WHERE slug = ?');
        $chk->execute([$slug]);
        if (!$chk->fetch()) {
            break;
        }
        $slug = $baseSlug . '-' . random_code(4);
    }

    $code = random_code(10);
    for ($j = 0; $j < 30; $j++) {
        $chk = $pdo->prepare('SELECT id FROM events WHERE unique_code = ?');
        $chk->execute([$code]);
        if (!$chk->fetch()) {
            break;
        }
        $code = random_code(10);
    }

    $ins = $pdo->prepare(
        'INSERT INTO events (name, slug, unique_code, event_date, description, music_url, countdown_enabled, accent_color, album_password_hash, active)
         VALUES (?,?,?,?,?,?,?,?,?,1)'
    );
    $ins->execute([$name, $slug, $code, $eventDate, $desc, $music, $countdown, $accent, $albumHash]);
    $newId = (int) $pdo->lastInsertId();
    uploads_dir($newId);

    $coverPath = null;
    if (!empty($_FILES['cover']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
        $f = $_FILES['cover'];
        $kind = detect_upload_kind($f['tmp_name'], (string) $f['type']);
        if ($kind === 'image' && (int) $f['size'] <= MAX_IMAGE_BYTES) {
            $ext = 'jpg';
            $fn = 'cover_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = uploads_dir($newId) . '/' . $fn;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($dest) ?: 'image/jpeg';
                $dest = compress_image_file($dest, $mime);
                $coverPath = 'uploads/' . $newId . '/' . basename($dest);
            }
        }
    }

    if ($coverPath) {
        $pdo->prepare('UPDATE events SET cover_image = ? WHERE id = ?')->execute([$coverPath, $newId]);
    }

    $row = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $row->execute([$newId]);
    $ev = $row->fetch();
    if ($ev) {
        try {
            $pub = event_public_url($ev);
            $qrRel = generate_event_qr_png($newId, $pub);
            $pdo->prepare('UPDATE events SET qr_path = ? WHERE id = ?')->execute([$qrRel, $newId]);
        } catch (Throwable $e) {
            $_SESSION['flash'] = 'Evento creado, pero el QR falló. ¿Ejecutaste composer install? ' . $e->getMessage();
            redirect('event.php?id=' . $newId);
        }
    }

    $_SESSION['flash'] = 'Evento creado correctamente.';
    redirect('event.php?id=' . $newId);
}

$events = $pdo->query('SELECT e.*,
  (SELECT COUNT(*) FROM media m WHERE m.event_id = e.id) AS media_count,
  (SELECT COUNT(DISTINCT guest_name) FROM media m WHERE m.event_id = e.id) AS guest_count
  FROM events e ORDER BY e.created_at DESC')->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Organizadores — Eventos</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Outfit:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css?v=<?= h((string) ASSET_VERSION) ?>">
</head>
<body class="page-admin">
  <header class="admin-header">
    <div class="wrap flex-between">
      <h1 class="logo">Panel <span class="gold">organizadores</span></h1>
      <nav class="admin-nav">
        <a href="../index.php" class="link-subtle">Sitio público</a>
        <a href="logout.php" class="btn btn-outline btn-sm">Salir</a>
      </nav>
    </div>
  </header>

  <main class="wrap admin-main">
    <?php if ($flash !== ''): ?><p class="banner-ok"><?= h($flash) ?></p><?php endif; ?>

    <section class="card-glass admin-section">
      <h2 class="section-title">Nuevo evento</h2>
      <form method="post" enctype="multipart/form-data" class="admin-form-grid">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <label class="field"><span>Nombre *</span><input type="text" name="name" required maxlength="255"></label>
        <label class="field"><span>Fecha</span><input type="date" name="event_date"></label>
        <label class="field full"><span>Descripción</span><textarea name="description" rows="3"></textarea></label>
        <label class="field"><span>Slug URL (opcional)</span><input type="text" name="slug" placeholder="auto desde nombre"></label>
        <label class="field">
          <span>Color acento</span>
          <div style="display: flex; gap: 0.5rem; align-items: center;">
            <input type="color" id="accent_picker" value="#c9a962" style="width: 44px; height: 44px; padding: 2px; background: none; border: 1px solid var(--border); border-radius: 8px; cursor: pointer;">
            <input type="text" name="accent_color" id="accent_hex" value="#c9a962" pattern="#[0-9a-fA-F]{6}" placeholder="#hex" style="flex: 1;">
          </div>
        </label>
        <label class="field full"><span>Música (URL mp3 o YouTube)</span><input type="url" name="music_url" placeholder="https://..."></label>
        <label class="field checkbox"><input type="checkbox" name="countdown_enabled" value="1" checked> <span>Cuenta regresiva</span></label>
        <label class="field"><span>Clave álbum (opcional)</span><input type="password" name="album_password" autocomplete="new-password" placeholder="vacío = público"></label>
        <label class="field full"><span>Portada</span><input type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label>
        <div class="full"><button type="submit" class="btn btn-primary">Crear evento y QR</button></div>
      </form>
    </section>

    <section class="admin-section">
      <h2 class="section-title">Tus eventos</h2>
      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Enlace / código</th>
              <th>Recursos</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($events as $e): ?>
              <?php
                $url = event_public_url($e);
                $active = (int) $e['active'] === 1;
              ?>
              <tr>
                <td><strong><?= h((string) $e['name']) ?></strong><br><small><?= h((string) ($e['event_date'] ?? '')) ?></small></td>
                <td>
                  <a href="<?= h($url) ?>" target="_blank" rel="noopener"><?= h($url) ?></a><br>
                  <code><?= h((string) $e['unique_code']) ?></code>
                </td>
                <td><?= (int) $e['media_count'] ?> archivos · <?= (int) $e['guest_count'] ?> invitados (únicos)</td>
                <td><?= $active ? 'Activo' : 'Pausado' ?></td>
                <td class="nowrap">
                  <a class="btn btn-outline btn-sm" href="event.php?id=<?= (int) $e['id'] ?>">Gestionar</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (count($events) === 0): ?>
              <tr><td colspan="5">Aún no hay eventos. Crea el primero arriba.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
  <script>
    const picker = document.getElementById('accent_picker');
    const hex = document.getElementById('accent_hex');
    if (picker && hex) {
      picker.addEventListener('input', () => { hex.value = picker.value; });
      hex.addEventListener('input', () => {
        if(/^#[0-9A-F]{6}$/i.test(hex.value)) { picker.value = hex.value; }
      });
    }
  </script>
</body>
</html>
