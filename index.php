<?php
/**
 * Portada principal: acceso por código/slug y presentación premium.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string) ($_POST['code'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? ''));
    if ($slug !== '') {
        redirect('event.php?s=' . rawurlencode(slugify($slug)));
    }
    if ($code !== '') {
        redirect('event.php?c=' . rawurlencode($code));
    }
    $err = 'Ingresa un código o el final de tu enlace personalizado.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuerdos en vivo — Tu celebración, tu galería</title>
  <meta name="description" content="Sube fotos y videos del evento, galería en tiempo real y descarga de recuerdos. Ideal para matrimonios y celebraciones.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= h((string) ASSET_VERSION) ?>">
  <link rel="icon" href="assets/img/icon.svg" type="image/svg+xml">
  <link rel="manifest" href="manifest.webmanifest">
  <meta name="theme-color" content="#0c0c0c">
</head>
<body class="page-home">
  <header class="site-header">
    <div class="wrap flex-between">
      <a href="index.php" class="logo">Recuerdos<span class="gold">.</span></a>
      <a href="admin/login.php" class="link-subtle">Organizadores</a>
    </div>
  </header>

  <main>
    <section class="hero-home reveal">
      <div class="hero-home__bg" aria-hidden="true"></div>
      <div class="wrap hero-home__inner">
        <p class="eyebrow gold">Galería en vivo · Bodas &amp; celebraciones</p>
        <h1 class="hero-title">Cada momento, <em>compartido</em> al instante</h1>
        <p class="hero-lead">Escanea el código QR del evento o ingresa tu enlace para subir fotos, videos y revivir la fiesta en una galería elegante y responsive.</p>

        <form class="access-card card-glass reveal delay-1" method="post" action="index.php" autocomplete="off">
          <?php if ($err !== ''): ?>
            <p class="form-error"><?= h($err) ?></p>
          <?php endif; ?>
          <label class="field">
            <span>Código del evento</span>
            <input type="text" name="code" placeholder="Ej: ab12cd34ef" maxlength="40">
          </label>
          <p class="access-or"><span>o</span></p>
          <label class="field">
            <span>URL personalizada (slug)</span>
            <input type="text" name="slug" placeholder="Ej: boda-camila-y-diego" maxlength="120">
          </label>
          <button type="submit" class="btn btn-primary btn-block">Entrar al evento</button>
        </form>
      </div>
    </section>

    <section class="features wrap reveal">
      <h2 class="section-title">Todo lo que necesitas</h2>
      <div class="feature-grid">
        <article class="feature-card">
          <h3>QR instantáneo</h3>
          <p>Enlace y PNG listos para imprimir o compartir por WhatsApp.</p>
        </article>
        <article class="feature-card">
          <h3>Cámara y galería</h3>
          <p>Sube desde el celular con captura directa o archivos MP4, JPG, PNG y WebP.</p>
        </article>
        <article class="feature-card">
          <h3>Tiempo real</h3>
          <p>Grid estilo Pinterest con actualización automática y modal fullscreen.</p>
        </article>
        <article class="feature-card">
          <h3>Descarga y ZIP</h3>
          <p>Invitados descargan recuerdos; administración puede bajar todo en un ZIP.</p>
        </article>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="wrap">
      <p>Hecho con cuidado para celebraciones inolvidables.</p>
    </div>
  </footer>

  <script src="assets/js/script.js?v=<?= h((string) ASSET_VERSION) ?>" defer></script>
</body>
</html>
