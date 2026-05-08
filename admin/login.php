<?php
/**
 * Acceso al panel de organizadores.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth_admin.php';

if (admin_logged_in()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = (string) ($_POST['password'] ?? '');
    if (admin_attempt_login($p)) {
        redirect('index.php');
    }
    $error = 'Contraseña incorrecta.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Organizadores — Acceso</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Outfit:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css?v=<?= h((string) ASSET_VERSION) ?>">
</head>
<body class="page-simple">
  <div class="wrap card-glass admin-login">
    <h1 class="section-title">Panel organizadores</h1>
    <?php if ($error !== ''): ?><p class="form-error"><?= h($error) ?></p><?php endif; ?>
    <form method="post" autocomplete="current-password">
      <label class="field"><span>Contraseña</span><input type="password" name="password" required></label>
      <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
    <p style="margin-top:1rem"><a href="../index.php">← Volver al sitio</a></p>
  </div>
</body>
</html>
