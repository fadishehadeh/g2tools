<?php
session_start();
require '../config.php';
require_login();
if (!is_finance_admin()) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }

header('Content-Type: application/json');

$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$notes  = trim(strip_tags($_POST['notes'] ?? ''));

if (!$id || !in_array($action, ['approved','rejected'])) {
    echo json_encode(['error' => 'Invalid request']); exit;
}

$row = db()->prepare("SELECT id, form_type, form_data, approval_status FROM form_submissions WHERE id=?");
$row->execute([$id]);
$sub = $row->fetch();

if (!$sub) { echo json_encode(['error' => 'Submission not found']); exit; }
if ($sub['approval_status'] !== 'pending') { echo json_encode(['error' => 'Already actioned']); exit; }

$uid = current_user()['id'];
db()->prepare("UPDATE form_submissions SET approval_status=?, approved_by=?, approved_at=NOW(), approval_notes=? WHERE id=?")
   ->execute([$action, $uid, $notes ?: null, $id]);

// Notify the submitter if they have an email in form_data
$fd = json_decode($sub['form_data'], true) ?? [];
$notify_email = $fd['rep_email'] ?? $fd['cs_email'] ?? $fd['fin_email'] ?? null;

if ($notify_email && filter_var($notify_email, FILTER_VALIDATE_EMAIL)) {
    $type_labels = [
        'amex'        => 'Credit Card Authorization',
        'debit_note'  => 'Debit Note',
        'credit_note' => 'Credit Note',
        'vendor_recon'=> 'Vendor Payable Reconciliation',
        'vendor_reg'  => 'Vendor Registration',
        'client_reg'  => 'Client Registration',
    ];
    $label   = $type_labels[$sub['form_type']] ?? ucwords(str_replace('_',' ',$sub['form_type']));
    $status  = $action === 'approved' ? 'Approved ✓' : 'Rejected ✗';
    $colour  = $action === 'approved' ? '#15803d' : '#b91c1c';
    $subject = "[$status] Your $label submission — G2 Tools";
    $msg     = "Your submission ({$label}) has been <strong style='color:{$colour}'>" . ucfirst($action) . "</strong> by the Finance team.";
    if ($notes) $msg .= "<br><br><strong>Note:</strong> " . htmlspecialchars($notes);

    require_once '../mailer.php';
    $body = mail_template($subject, "<p>{$msg}</p><p>Reference: #{$id}</p>");
    send_mail(['email'=>$notify_email,'name'=>$fd['rep_name']??$fd['company_name']??''], $subject, $body);
}

echo json_encode(['ok' => true, 'status' => $action]);
