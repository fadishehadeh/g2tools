<?php
session_start();
require '../../config.php';
require_login();
$f = basename($_GET['f'] ?? '');
if (!$f) exit;
$path = __DIR__ . '/../../storage/petty-cash/' . $f;
if (!file_exists($path)) { http_response_code(404); exit; }
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$mime = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'][$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $f . '"');
readfile($path);
