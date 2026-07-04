<?php
require_once __DIR__ . '/../lib/bootstrap.php';
sm_require_staff();

$id = (int)($_GET['id'] ?? 0);
$stmt = sm_db()->prepare(
    "SELECT p.*, c.name AS client_name, cal.name AS calendar_name
     FROM posts p JOIN clients c ON c.id=p.client_id JOIN calendars cal ON cal.id=p.calendar_id
     WHERE p.id=?"
);
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) { http_response_code(404); exit; }

$latest = sm_db()->prepare("SELECT filename FROM artwork_versions WHERE post_id=? AND deleted_at IS NULL ORDER BY version DESC LIMIT 1");
$latest->execute([$id]);
$artwork_name = $latest->fetchColumn();

$lines = [];
$lines[] = 'POSTING BRIEF — ' . $post['title'];
$lines[] = str_repeat('=', 50);
$lines[] = '';
$lines[] = 'Client:        ' . $post['client_name'];
$lines[] = 'Calendar:      ' . $post['calendar_name'];
$lines[] = 'Platform:      ' . ($post['platform'] ?: '—');
$lines[] = 'Format:        ' . ($post['format'] ?: '—');
$lines[] = 'Specs:         ' . ($post['technical_specs'] ?: '—');
$lines[] = 'Scheduled:     ' . ($post['scheduled_at'] ? date('d M Y H:i', strtotime($post['scheduled_at'])) : '—');
$lines[] = 'Artwork File:  ' . ($artwork_name ?: '— none uploaded —');
$lines[] = '';
$lines[] = 'CAPTION';
$lines[] = str_repeat('-', 50);
$lines[] = $post['caption'] ?: '(no caption)';
$lines[] = '';
$lines[] = 'HASHTAGS';
$lines[] = str_repeat('-', 50);
$lines[] = $post['hashtags'] ?: '(none)';
$lines[] = '';
$lines[] = 'Generated ' . date('d M Y H:i') . ' — G2 SM Calendar Tool';

$body = implode("\n", $lines);
$fname = 'brief_' . preg_replace('/[^a-z0-9]+/i','_', $post['title']) . '.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Content-Length: ' . strlen($body));
echo $body;
