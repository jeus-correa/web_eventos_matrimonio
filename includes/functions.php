<?php
/**
 * Utilidades: sanitización, slugs, rutas, compresión imagen, MIME.
 */
declare(strict_types=1);

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect(string $url, int $code = 302): never
{
    header('Location: ' . $url, true, $code);
    exit;
}

/** Raíz del proyecto en disco (public_html) */
function base_path(): string
{
    static $p = null;
    if ($p === null) {
        $p = dirname(__DIR__);
    }
    return $p;
}

function uploads_dir(int $eventId): string
{
    $dir = base_path() . '/uploads/' . $eventId;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function qrcodes_dir(): string
{
    $dir = base_path() . '/qrcodes';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/** Slug URL seguro */
/**
 * Valida ruta pública relativa (portadas / medios servidos desde /uploads).
 */
function safe_asset_path(?string $path): string
{
    if ($path === null || $path === '') {
        return '';
    }
    $path = str_replace('\\', '/', $path);
    if (!preg_match('#^uploads/[a-zA-Z0-9_./-]+$#', $path) || str_contains($path, '..')) {
        return '';
    }
    return $path;
}

function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower((string) $text));
    $text = trim((string) $text, '-');
    return $text !== '' ? substr($text, 0, 100) : 'evento';
}

/** Código corto único alfanumérico */
function random_code(int $length = 10): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

/** Hash visitante para likes (cookie + IP, sin PII fuerte) */
function visitor_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120);
    if (empty($_COOKIE['ev_vid'])) {
        $token = bin2hex(random_bytes(16));
        setcookie('ev_vid', $token, [
            'expires' => time() + 3600 * 24 * 400,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['ev_vid'] = $token;
    }
    return hash('sha256', $_COOKIE['ev_vid'] . '|' . $ip . '|' . $ua);
}

/** Valida MIME permitido; retorna 'image'|'video' o null */
function detect_upload_kind(string $tmpPath, string $clientMime): ?string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $real = $finfo->file($tmpPath) ?: '';

    $imageMimes = [
        'image/jpeg' => true,
        'image/png' => true,
        'image/webp' => true,
    ];
    $videoMimes = [
        'video/mp4' => true,
    ];

    $mime = $real !== '' ? $real : $clientMime;

    if (isset($imageMimes[$mime])) {
        return 'image';
    }
    if (isset($videoMimes[$mime])) {
        return 'video';
    }
    return null;
}

/**
 * Redimensiona y comprime imagen (GD). JPEG/WebP → JPEG; PNG → PNG optimizado.
 * Retorna ruta final del archivo (puede cambiar extensión).
 */
function compress_image_file(string $path, string $mime): string
{
    if (!function_exists('imagecreatetruecolor')) {
        return $path;
    }

    $data = @file_get_contents($path);
    if ($data === false) {
        return $path;
    }

    $img = @imagecreatefromstring($data);
    if ($img === false) {
        return $path;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $maxW = 2400;
    if ($w > $maxW) {
        $nh = (int) round($h * ($maxW / $w));
        $nw = $maxW;
        $dst = imagecreatetruecolor($nw, $nh);
        if ($mime === 'image/png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
        }
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    }

    $base = preg_replace('/\.[^.]+$/', '', $path);
    if ($mime === 'image/png') {
        $newPath = $base . '.png';
        imagesavealpha($img, true);
        imagepng($img, $newPath, 6);
    } else {
        $newPath = $base . '.jpg';
        imagejpeg($img, $newPath, 82);
    }
    imagedestroy($img);

    if ($newPath !== $path && is_file($path)) {
        @unlink($path);
    }
    return $newPath;
}

/** Token HMAC para formularios de subida (sin depender de sesión del invitado). */
function upload_token_for_event(int $eventId): string
{
    return hash_hmac('sha256', (string) $eventId, APP_SECRET);
}

function upload_token_valid(int $eventId, string $token): bool
{
    return hash_equals(upload_token_for_event($eventId), $token);
}

/** URL pública del evento para QR y compartir */
function event_public_url(array $event): string
{
    $base = rtrim(SITE_URL, '/');
    if (!empty($event['slug'])) {
        return $base . '/event.php?s=' . rawurlencode((string) $event['slug']);
    }
    return $base . '/event.php?c=' . rawurlencode((string) $event['unique_code']);
}

/** CSRF token */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
