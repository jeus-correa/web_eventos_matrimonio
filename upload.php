<?php
/**
 * Subida AJAX de fotos/videos con validación MIME, límites y compresión de imagen.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$eventId = (int) ($_POST['event_id'] ?? 0);
$token = (string) ($_POST['token'] ?? '');
$guestName = trim((string) ($_POST['guest_name'] ?? ''));

if ($eventId < 1 || !upload_token_valid($eventId, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token o evento inválido.']);
    exit;
}

if ($guestName === '' || mb_strlen($guestName) > 120) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Indica un nombre válido.']);
    exit;
}

$pdo = db();
$st = $pdo->prepare('SELECT id, active FROM events WHERE id = ? LIMIT 1');
$st->execute([$eventId]);
$ev = $st->fetch();
if (!$ev || (int) $ev['active'] !== 1) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Evento no disponible.']);
    exit;
}

if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se recibió ningún archivo.']);
    exit;
}

$f = $_FILES['file'];
if ((int) $f['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Error al subir el archivo.']);
    exit;
}

$clientMime = (string) ($f['type'] ?? '');
$kind = detect_upload_kind($f['tmp_name'], $clientMime);
if ($kind === null) {
    http_response_code(415);
    echo json_encode(['ok' => false, 'error' => 'Tipo de archivo no permitido. Usa JPG, PNG, WebP o MP4.']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($f['tmp_name']) ?: $clientMime;

$maxBytes = $kind === 'image' ? MAX_IMAGE_BYTES : MAX_VIDEO_BYTES;
if ((int) $f['size'] > $maxBytes) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => $kind === 'image' ? 'La imagen supera 10 MB.' : 'El video supera 100 MB.']);
    exit;
}

$extMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'video/mp4' => 'mp4',
];
$ext = $extMap[$mime] ?? ($kind === 'image' ? 'jpg' : 'mp4');
$base = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$destName = $base . '.' . $ext;
$dir = uploads_dir($eventId);
$destPath = $dir . '/' . $destName;

if (!move_uploaded_file($f['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el archivo en el servidor.']);
    exit;
}

$finalPath = $destPath;
$finalName = $destName;
$finalMime = $mime;

if ($kind === 'image') {
    $finalPath = compress_image_file($destPath, $mime);
    $finalName = basename($finalPath);
    $finfo2 = new finfo(FILEINFO_MIME_TYPE);
    $finalMime = $finfo2->file($finalPath) ?: 'image/jpeg';
}

$rel = 'uploads/' . $eventId . '/' . $finalName;
$ip = $_SERVER['REMOTE_ADDR'] ?? null;

$ins = $pdo->prepare(
    'INSERT INTO media (event_id, guest_name, stored_filename, original_name, mime_type, file_kind, ip_address)
     VALUES (?,?,?,?,?,?,?)'
);
$ins->execute([
    $eventId,
    $guestName,
    $finalName,
    substr((string) $f['name'], 0, 250),
    $finalMime,
    $kind,
    $ip,
]);

echo json_encode([
    'ok' => true,
    'item' => [
        'id' => (int) $pdo->lastInsertId(),
        'guest_name' => $guestName,
        'url' => $rel,
        'mime' => $finalMime,
        'kind' => $kind,
        'likes' => 0,
        'created_at' => date('c'),
    ],
]);
