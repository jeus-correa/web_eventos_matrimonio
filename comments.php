<?php
/**
 * Lista comentarios de un recuerdo (para mostrar en el modal).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$mediaId = (int) ($_GET['media_id'] ?? 0);
$eventId = (int) ($_GET['event_id'] ?? 0);
$token = (string) ($_GET['token'] ?? '');

if ($mediaId < 1 || $eventId < 1 || !upload_token_valid($eventId, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = db();
$q = $pdo->prepare(
    'SELECT c.guest_name, c.body, c.created_at FROM comments c
     JOIN media m ON m.id = c.media_id
     WHERE c.media_id = ? AND m.event_id = ? ORDER BY c.id ASC'
);
$q->execute([$mediaId, $eventId]);
echo json_encode(['ok' => true, 'comments' => $q->fetchAll()]);
