<?php
session_start();
require '../../config.php';
require_login();

header('Content-Type: application/json');

$url = trim($_POST['url'] ?? '');
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid URL.']);
    exit;
}

$ytdlp = __DIR__ . '/../../bin/yt-dlp.exe';
if (!file_exists($ytdlp)) {
    echo json_encode(['ok'=>false,'error'=>'yt-dlp not found on server.']);
    exit;
}

$cmd = escapeshellarg($ytdlp)
     . ' --no-playlist --dump-json --no-warnings'
     . ' --socket-timeout 15'
     . ' ' . escapeshellarg($url)
     . ' 2>&1';

$output = shell_exec($cmd);

if (!$output) {
    echo json_encode(['ok'=>false,'error'=>'No response from downloader. The URL may not be supported.']);
    exit;
}

// yt-dlp may output multiple JSON lines (playlist) — take first
$lines = array_filter(explode("\n", trim($output)));
$info  = null;
foreach ($lines as $line) {
    $decoded = json_decode($line, true);
    if ($decoded && isset($decoded['title'])) { $info = $decoded; break; }
}

if (!$info) {
    // Capture error message from output
    $err = strip_tags(substr($output, 0, 300));
    echo json_encode(['ok'=>false,'error'=>'Could not extract video info. ' . $err]);
    exit;
}

// Format duration
$dur = (int)($info['duration'] ?? 0);
$dur_str = $dur > 0 ? sprintf('%d:%02d', intdiv($dur, 60), $dur % 60) : null;

// Detect platform from URL
$host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
$platform = match(true) {
    str_contains($host, 'instagram') => 'Instagram',
    str_contains($host, 'facebook') || str_contains($host, 'fb.watch') => 'Facebook',
    str_contains($host, 'youtube') || str_contains($host, 'youtu.be') => 'YouTube',
    str_contains($host, 'tiktok')   => 'TikTok',
    str_contains($host, 'twitter') || str_contains($host, 'x.com') => 'Twitter / X',
    default => $info['extractor_key'] ?? null
};

echo json_encode([
    'ok'          => true,
    'title'       => $info['title'] ?? 'Untitled',
    'thumbnail'   => $info['thumbnail'] ?? null,
    'duration_str'=> $dur_str,
    'uploader'    => $info['uploader'] ?? $info['channel'] ?? null,
    'platform'    => $platform,
    'ext'         => $info['ext'] ?? 'mp4',
]);
