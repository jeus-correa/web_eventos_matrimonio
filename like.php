<?php
/**
 * Toggle reacción ❤️ por medio (un like por visitante por archivo).
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

if ($mediaId < 1 || $eventId < 1 || !upload_token_valid($eventId, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$pdo = db();
$check = $pdo->prepare('SELECT m.id, m.likes_count FROM media m JOIN events e ON e.id = m.event_id WHERE m.id = ? AND m.event_id = ? AND e.active = 1');
$check->execute([$mediaId, $eventId]);
$row = $check->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$vh = visitor_hash();
$pdo->beginTransaction();
try {
    $ex = $pdo->prepare('SELECT id FROM media_likes WHERE media_id = ? AND visitor_hash = ?');
    $ex->execute([$mediaId, $vh]);
    if ($ex->fetch()) {
        $pdo->prepare('DELETE FROM media_likes WHERE media_id = ? AND visitor_hash = ?')->execute([$mediaId, $vh]);
        $pdo->prepare('UPDATE media SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?')->execute([$mediaId]);
        $liked = false;
    } else {
        $pdo->prepare('INSERT INTO media_likes (media_id, visitor_hash) VALUES (?,?)')->execute([$mediaId, $vh]);
        $pdo->prepare('UPDATE media SET likes_count = likes_count + 1 WHERE id = ?')->execute([$mediaId]);
        $liked = true;
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}

$cnt = $pdo->prepare('SELECT likes_count FROM media WHERE id = ?');
$cnt->execute([$mediaId]);
$n = (int) $cnt->fetchColumn();

echo json_encode(['ok' => true, 'liked' => $liked, 'likes' => $n]);
