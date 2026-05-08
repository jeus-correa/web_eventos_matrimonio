<?php
/**
 * Comentarios opcionales sobre un recuerdo.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    $data = $_POST;
}

$mediaId = (int) ($data['media_id'] ?? 0);
$eventId = (int) ($data['event_id'] ?? 0);
$token = (string) ($data['token'] ?? '');
$guestName = trim((string) ($data['guest_name'] ?? ''));
$body = trim((string) ($data['body'] ?? ''));

if ($mediaId < 1 || $eventId < 1 || !upload_token_valid($eventId, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

if ($guestName === '' || $body === '' || mb_strlen($body) > 500) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Nombre y comentario requeridos (máx. 500 caracteres).']);
    exit;
}

$pdo = db();
$check = $pdo->prepare('SELECT m.id FROM media m JOIN events e ON e.id = m.event_id WHERE m.id = ? AND m.event_id = ? AND e.active = 1');
$check->execute([$mediaId, $eventId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ins = $pdo->prepare('INSERT INTO comments (media_id, guest_name, body, ip_address) VALUES (?,?,?,?)');
$ins->execute([$mediaId, $guestName, $body, $ip]);

echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
