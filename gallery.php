<?php
/**
 * API JSON: listado de medios para galería (polling / carga inicial).
 * GET: event_id (requerido), token (HMAC), since_id opcional.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$eventId = (int) ($_GET['event_id'] ?? 0);
$token = (string) ($_GET['token'] ?? '');
$sinceId = (int) ($_GET['since_id'] ?? 0);

if ($eventId < 1 || !upload_token_valid($eventId, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$pdo = db();
$st = $pdo->prepare('SELECT id, active FROM events WHERE id = ? LIMIT 1');
$st->execute([$eventId]);
$ev = $st->fetch();
if (!$ev || (int) $ev['active'] !== 1) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Evento no encontrado']);
    exit;
}

$sql = 'SELECT id, guest_name, stored_filename, mime_type, file_kind, likes_count, created_at
        FROM media WHERE event_id = ?';
$args = [$eventId];
if ($sinceId > 0) {
    $sql .= ' AND id > ?';
    $args[] = $sinceId;
}
$sql .= ' ORDER BY id ASC';

$q = $pdo->prepare($sql);
$q->execute($args);
$rows = $q->fetchAll();

$items = [];
$maxId = $sinceId;
foreach ($rows as $r) {
    $id = (int) $r['id'];
    if ($id > $maxId) {
        $maxId = $id;
    }
    $items[] = [
        'id' => $id,
        'guest_name' => $r['guest_name'],
        'url' => 'uploads/' . $eventId . '/' . $r['stored_filename'],
        'mime' => $r['mime_type'],
        'kind' => $r['file_kind'],
        'likes' => (int) $r['likes_count'],
        'created_at' => $r['created_at'],
    ];
}

echo json_encode([
    'ok' => true,
    'items' => $items,
    'last_id' => $maxId,
]);
