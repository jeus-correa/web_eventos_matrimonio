<?php
/**
 * Descarga ZIP con todos los archivos del evento (solo admin).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth_admin.php';

admin_require_login();

$id = (int) ($_GET['id'] ?? 0);
$tok = (string) ($_GET['csrf'] ?? '');
if ($id < 1 || !csrf_verify($tok)) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$pdo = db();
$st = $pdo->prepare('SELECT name, slug FROM events WHERE id = ?');
$st->execute([$id]);
$ev = $st->fetch();
if (!$ev) {
    http_response_code(404);
    exit;
}

$dir = dirname(__DIR__) . '/uploads/' . $id;
if (!is_dir($dir)) {
    http_response_code(404);
    echo 'Sin archivos';
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo 'ZipArchive no disponible en el servidor.';
    exit;
}

$zipName = 'evento-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $ev['slug']) . '.zip';
$tmp = sys_get_temp_dir() . '/' . bin2hex(random_bytes(8)) . '.zip';

$z = new ZipArchive();
if ($z->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit;
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    $rel = substr($file->getPathname(), strlen($dir) + 1);
    $z->addFile($file->getPathname(), $rel);
}
$z->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . (string) filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
