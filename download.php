<?php
session_start();
require 'config.php';
require_login();

$id   = (int)($_GET['id'] ?? 0);
$user = current_user();

$stmt = db()->prepare("SELECT * FROM form_submissions WHERE id = ?");
$stmt->execute([$id]);
$sub  = $stmt->fetch();

if (!$sub) { http_response_code(404); exit('Not found.'); }
if (!is_admin() && $sub['user_id'] != $user['id']) { http_response_code(403); exit('Forbidden.'); }

$path = STORAGE_PATH . basename($sub['pdf_filename']);
if (!$sub['pdf_filename'] || !file_exists($path)) { http_response_code(404); exit('File not found.'); }

$data = json_decode($sub['form_data'], true);
$ts   = date('Ymd', strtotime($sub['created_at']));
if (!empty($data['dl_name'])) {
    $dl = $data['dl_name'];
} elseif ($sub['form_type'] === 'amex') {
    $safe = preg_replace('/[<>:"\/\\\\|?*]/', '', $data['merchant'] ?? '');
    $dl   = 'AMEX_Credit Card Authorization - ' . trim($safe) . '.pdf';
} else {
    $who = preg_replace('/[^a-zA-Z0-9 _-]/', '', $data['received_by'] ?? 'form');
    $dl  = 'Accountability - ' . $who . ' - ' . $ts . '.pdf';
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $dl . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
