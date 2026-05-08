<?php
/**
 * Autenticación simple por sesión (contraseña en settings.php).
 */
declare(strict_types=1);

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_ok']) && $_SESSION['admin_ok'] === true;
}

function admin_require_login(): void
{
    if (!admin_logged_in()) {
        redirect('login.php');
    }
}

function admin_attempt_login(string $password): bool
{
    $stored = ADMIN_PASS;
    $ok = false;
    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon')) {
        $ok = password_verify($password, $stored);
    } else {
        $ok = hash_equals($stored, $password);
    }
    if ($ok) {
        $_SESSION['admin_ok'] = true;
        session_regenerate_id(true);
    }
    return $ok;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
