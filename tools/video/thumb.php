<?php
// Proxy thumbnail images to avoid CORS/mixed-content issues
session_start();
require '../../config.php';
require_login();

$url = trim($_GET['url'] ?? '');
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400); exit;
}

// Only allow http/https
$scheme = strtolower(parse_url($url, PHP_URL_SCHEME));
if (!in_array($scheme, ['http', 'https'])) { http_response_code(400); exit; }

$ctx = stream_context_create(['http' => [
    'timeout' => 8,
    'header'  => "User-Agent: Mozilla/5.0\r\n",
]]);
$data = @file_get_contents($url, false, $ctx);
if (!$data) { http_response_code(404); exit; }

// Detect content type from response headers
$ct = 'image/jpeg';
foreach ($http_response_header as $h) {
    if (stripos($h, 'content-type:') === 0) {
        $ct = trim(substr($h, 13));
        break;
    }
}

header('Content-Type: ' . $ct);
header('Cache-Control: max-age=3600');
echo $data;
