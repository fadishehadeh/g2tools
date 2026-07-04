<?php
session_start();
require '../../config.php';
require_login();

$url     = trim($_GET['url'] ?? '');
$quality = trim($_GET['quality'] ?? 'best');

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400); echo 'Invalid URL.'; exit;
}

// Whitelist quality options
$allowed = [
    'best',
    'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
    'worstvideo+worstaudio/worst',
    'bestaudio/best',
];
if (!in_array($quality, $allowed)) $quality = 'best';

$ytdlp   = __DIR__ . '/../../bin/yt-dlp.exe';
$outdir  = rtrim(STORAGE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'downloads';
if (!is_dir($outdir)) mkdir($outdir, 0755, true);

$uid     = uniqid('vdl_', true);
$outTpl  = $outdir . DIRECTORY_SEPARATOR . $uid . '.%(ext)s';

// For audio-only, post-process to mp3
$isAudio = ($quality === 'bestaudio/best');
$audioFlags = $isAudio
    ? ' --extract-audio --audio-format mp3 --audio-quality 0'
    : '';

$ffmpeg = 'C:\\Users\\Fadi\\AppData\\Local\\Microsoft\\WinGet\\Packages\\Gyan.FFmpeg.Essentials_Microsoft.Winget.Source_8wekyb3d8bbwe\\ffmpeg-8.1-essentials_build\\bin\\ffmpeg.exe';
$ffmpegFlag = file_exists($ffmpeg) ? ' --ffmpeg-location ' . escapeshellarg($ffmpeg) : '';

$cmd = escapeshellarg($ytdlp)
     . ' --no-playlist --no-warnings'
     . ' -f ' . escapeshellarg($quality)
     . $audioFlags
     . $ffmpegFlag
     . ' -o ' . escapeshellarg($outTpl)
     . ' --socket-timeout 30'
     . ' ' . escapeshellarg($url)
     . ' 2>&1';

$output = shell_exec($cmd);

// Find the downloaded file
$files = glob($outdir . DIRECTORY_SEPARATOR . $uid . '.*');
if (!$files) {
    http_response_code(500);
    echo '<pre>Download failed. yt-dlp output:<br>' . htmlspecialchars($output) . '</pre>';
    exit;
}

$filepath = $files[0];
$ext      = pathinfo($filepath, PATHINFO_EXTENSION);
$filename = 'g2tools_video_' . date('Ymd_His') . '.' . $ext;

// Stream to browser
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache');

readfile($filepath);

// Cleanup
@unlink($filepath);
exit;
