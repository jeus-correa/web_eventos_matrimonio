<?php
/**
 * Página del evento: hero, cuenta regresiva, música, subida, galería, TV y álbum protegido.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();

/** @var array<string,mixed>|null $event */
$event = null;
$slug = isset($_GET['s']) ? trim((string) $_GET['s']) : '';
$code = isset($_GET['c']) ? trim((string) $_GET['c']) : '';

if ($slug !== '') {
    $st = $pdo->prepare('SELECT * FROM events WHERE slug = ? AND active = 1 LIMIT 1');
    $st->execute([$slug]);
    $event = $st->fetch() ?: null;
} elseif ($code !== '') {
    $st = $pdo->prepare('SELECT * FROM events WHERE unique_code = ? AND active = 1 LIMIT 1');
    $st->execute([$code]);
    $event = $st->fetch() ?: null;
}

if (!$event) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Evento no encontrado</title><link rel="stylesheet" href="assets/css/style.css?v=' . h((string) ASSET_VERSION) . '"></head><body class="page-simple"><div class="wrap card-glass" style="margin:4rem auto;max-width:480px;text-align:center"><h1>Evento no disponible</h1><p>Revisa el código o el enlace. Si el organizador desactivó el evento, vuelve más tarde.</p><a class="btn btn-primary" href="index.php">Volver al inicio</a></div></body></html>';
    exit;
}

$eid = (int) $event['id'];
$albumLocked = !empty($event['album_password_hash']);
$sessionKey = 'album_unlock_' . $eid;
$unlockError = '';

if ($albumLocked && empty($_SESSION[$sessionKey])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['album_password'])) {
        $try = (string) $_POST['album_password'];
        if ($event['album_password_hash'] && password_verify($try, (string) $event['album_password_hash'])) {
            $_SESSION[$sessionKey] = true;
            redirect('event.php?' . ($slug !== '' ? 's=' . rawurlencode($slug) : 'c=' . rawurlencode($code)) . (isset($_GET['tv']) ? '&tv=1' : ''));
        }
        $unlockError = 'Contraseña incorrecta.';
    }
    if (empty($_SESSION[$sessionKey])) {
        // Pantalla de desbloqueo
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Álbum privado — <?= h((string) $event['name']) ?></title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= h((string) ASSET_VERSION) ?>">
</head>
<body class="page-simple">
  <div class="wrap card-glass album-gate" style="margin:4rem auto;max-width:420px">
    <h1 class="section-title" style="font-size:1.5rem">Álbum privado</h1>
    <p>Ingresa la clave que te compartió el organizador.</p>
    <?php if ($unlockError !== ''): ?><p class="form-error"><?= h($unlockError) ?></p><?php endif; ?>
    <form method="post">
      <label class="field"><span>Contraseña</span><input type="password" name="album_password" required autocomplete="current-password"></label>
      <button class="btn btn-primary btn-block" type="submit">Desbloquear</button>
    </form>
    <p style="margin-top:1rem"><a href="index.php">← Inicio</a></p>
  </div>
</body>
</html>
        <?php
        exit;
    }
}

$isTv = isset($_GET['tv']) && $_GET['tv'] === '1';
$coverPath = safe_asset_path($event['cover_image'] ?? null);
$coverUrl = $coverPath !== '' ? h($coverPath) : '';
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $event['accent_color']) ? (string) $event['accent_color'] : '#c9a962';
$eventDate = $event['event_date'] ? (string) $event['event_date'] : '';
$musicUrl = trim((string) $event['music_url']);
$countdownOn = (int) $event['countdown_enabled'] === 1 && $eventDate !== '';
$uploadTok = upload_token_for_event($eid);
$shareUrl = event_public_url($event);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= h((string) $event['name']) ?> — Galería en vivo</title>
  <meta name="description" content="<?= h(mb_substr(strip_tags((string) ($event['description'] ?? '')), 0, 160)) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= h((string) ASSET_VERSION) ?>">
  <link rel="icon" href="assets/img/icon.svg" type="image/svg+xml">
  <link rel="manifest" href="manifest.webmanifest">
  <meta name="theme-color" content="<?= h($accent) ?>">
  <style>:root { --accent: <?= h($accent) ?>; }</style>
</head>
<body class="page-event<?= $isTv ? ' mode-tv' : '' ?>" data-event-id="<?= $eid ?>" data-poll-ms="<?= (int) GALLERY_POLL_MS ?>" data-upload-token="<?= h($uploadTok) ?>" data-share-url="<?= h($shareUrl) ?>" data-accent="<?= h($accent) ?>">

  <?php if ($musicUrl !== '' && !$isTv):
    $ytId = '';
    if (preg_match('%(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([a-zA-Z0-9_-]{11})%', $musicUrl, $m)) {
        $ytId = $m[1];
    }
  ?>
  <div class="music-bar" id="musicBar">
    <?php if ($ytId !== ''): ?>
      <button type="button" class="btn btn-ghost btn-sm" id="btnMusic" aria-pressed="false" data-youtube="<?= h($ytId) ?>">♪ Música del evento</button>
      <div id="ytWrap" class="yt-wrap hidden" aria-hidden="true"></div>
    <?php elseif (preg_match('/\.mp3($|\?)/i', $musicUrl)): ?>
      <audio id="bgAudio" src="<?= h($musicUrl) ?>" loop preload="none"></audio>
      <button type="button" class="btn btn-ghost btn-sm" id="btnAudioToggle" aria-pressed="false">♪ Música</button>
    <?php else: ?>
      <a class="btn btn-ghost btn-sm" href="<?= h($musicUrl) ?>" target="_blank" rel="noopener">♪ Abrir música</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <header class="event-hero reveal"<?= $coverUrl !== '' ? ' style="--cover:url(' . h($coverUrl) . ')"' : '' ?>>
    <div class="event-hero__overlay"></div>
    <div class="wrap event-hero__content">
      <p class="eyebrow gold"><?= $eventDate !== '' ? h(date('d M Y', strtotime($eventDate))) : 'Celebración' ?></p>
      <h1 class="event-title"><?= h((string) $event['name']) ?></h1>
      <?php if (!empty($event['description'])): ?>
        <p class="event-desc"><?= nl2br(h((string) $event['description'])) ?></p>
      <?php endif; ?>

      <?php if ($countdownOn): ?>
        <div class="countdown" id="countdown" data-target="<?= h($eventDate) ?>T16:00:00">
          <div><span class="n" data-u="days">00</span><span class="l">días</span></div>
          <div><span class="n" data-u="hours">00</span><span class="l">hrs</span></div>
          <div><span class="n" data-u="minutes">00</span><span class="l">min</span></div>
          <div><span class="n" data-u="seconds">00</span><span class="l">seg</span></div>
        </div>
      <?php endif; ?>

      <div class="hero-actions">
        <button type="button" class="btn btn-primary" data-scroll="#uploadSection">Subir recuerdo</button>
        <button type="button" class="btn btn-outline" data-scroll="#gallerySection">Ver galería</button>
        <a class="btn btn-outline" href="event.php?<?= $slug !== '' ? 's=' . rawurlencode($slug) . '&tv=1' : 'c=' . rawurlencode($code) . '&tv=1' ?>">Modo TV</a>
      </div>
    </div>
  </header>

  <main class="wrap event-main">
    <section id="uploadSection" class="section-block card-glass reveal">
      <h2 class="section-title">Sube tu foto o video</h2>
      <form id="uploadForm" class="upload-form" enctype="multipart/form-data">
        <input type="hidden" name="event_id" value="<?= $eid ?>">
        <input type="hidden" name="token" value="<?= h($uploadTok) ?>">
        <label class="field">
          <span>Tu nombre (aparece en la galería)</span>
          <input type="text" name="guest_name" required maxlength="120" placeholder="Ej: María José">
        </label>
        <div class="dropzone" id="dropzone">
          <input type="file" name="file" id="fileInput" accept="image/jpeg,image/png,image/webp,video/mp4" class="visually-hidden">
          <p>Arrastra aquí o <button type="button" class="link-like" id="pickFile">elige archivo</button></p>
          <p class="hint">JPG, PNG, WebP hasta 10&nbsp;MB · MP4 hasta 100&nbsp;MB</p>
          <button type="button" class="btn btn-outline btn-sm" id="btnCamera">Usar cámara</button>
          <video id="cameraPreview" class="camera-preview hidden" playsinline muted></video>
          <canvas id="cameraCanvas" class="hidden"></canvas>
        </div>
        <div class="progress-wrap hidden" id="progressWrap">
          <div class="progress-bar"><div class="progress-bar__fill" id="progressFill"></div></div>
          <p class="progress-text" id="progressText">0%</p>
        </div>
        <p class="form-msg hidden" id="uploadMsg"></p>
        <button type="submit" class="btn btn-primary btn-block" id="btnUpload">Enviar recuerdo</button>
      </form>
    </section>

    <section id="gallerySection" class="section-block reveal">
      <div class="gallery-toolbar flex-between">
        <h2 class="section-title" style="margin:0">Galería en vivo</h2>
        <div class="toolbar-actions">
          <button type="button" class="btn btn-ghost btn-sm" id="btnSlideshow" title="Slideshow">Slideshow</button>
          <button type="button" class="btn btn-ghost btn-sm" id="btnShareEvent">Compartir evento</button>
        </div>
      </div>
      <div class="masonry" id="masonry" aria-live="polite"></div>
      <p class="gallery-empty hidden" id="galleryEmpty">Aún no hay fotos. ¡Sé el primero en subir!</p>
    </section>
  </main>

  <div class="tv-stage" id="tvStage" aria-hidden="true"></div>

  <div class="modal hidden" id="lightbox" role="dialog" aria-modal="true" aria-label="Vista ampliada">
    <button type="button" class="modal-close" id="modalClose" aria-label="Cerrar">×</button>
    <div class="modal-body" id="modalBody"></div>
  </div>

  <div class="modal hidden" id="commentModal" role="dialog" aria-modal="true">
    <div class="modal-inner card-glass">
      <h3>Comentar</h3>
      <form id="commentForm">
        <input type="hidden" name="media_id" id="commentMediaId">
        <label class="field"><span>Tu nombre</span><input type="text" name="guest_name" required maxlength="120"></label>
        <label class="field"><span>Mensaje</span><textarea name="body" rows="3" maxlength="500" required></textarea></label>
        <button type="submit" class="btn btn-primary btn-block">Publicar</button>
      </form>
      <button type="button" class="btn btn-ghost btn-block" id="commentCancel">Cancelar</button>
    </div>
  </div>

  <footer class="site-footer">
    <div class="wrap flex-between">
      <span>Invitados: comparte con cuidado.</span>
      <a href="index.php" class="link-subtle">Inicio</a>
    </div>
  </footer>

  <script src="assets/js/script.js?v=<?= h((string) ASSET_VERSION) ?>" defer></script>
</body>
</html>
