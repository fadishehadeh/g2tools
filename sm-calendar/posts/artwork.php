<?php
require_once __DIR__ . '/../lib/bootstrap.php';
sm_require_staff();

$id = (int)($_GET['id'] ?? 0);
$stmt = sm_db()->prepare("SELECT * FROM artwork_versions WHERE id=? AND deleted_at IS NULL");
$stmt->execute([$id]);
$art = $stmt->fetch();
if (!$art) { http_response_code(404); exit; }

$path = SM_ARTWORK_PATH . $art['asset_path'];
if (!file_exists($path)) { http_response_code(404); exit; }

header('Content-Type: ' . ($art['mime_type'] ?: 'application/octet-stream'));
if (!empty($_GET['download'])) {
    header('Content-Disposition: attachment; filename="' . $art['filename'] . '"');
} else {
    header('Content-Disposition: inline; filename="' . $art['filename'] . '"');
}
header('Content-Length: ' . filesize($path));
readfile($path);
