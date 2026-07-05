<?php
session_start();
require '../config.php';
require_login();

$sub_id = (int)($_GET['id'] ?? 0);
if (!$sub_id) { header('Location: /'); exit; }

$user = current_user();
$sub  = db()->prepare("SELECT * FROM form_submissions WHERE id = ?");
$sub->execute([$sub_id]);
$sub  = $sub->fetch();

if (!$sub || (!is_admin() && (int)$sub['user_id'] !== (int)$user['id'])) {
    http_response_code(403); echo 'Access denied.'; exit;
}

$data    = json_decode($sub['form_data'], true);
$dl_name = $data['dl_name'] ?? ($data['serial'] ?? 'document') . '.pdf';
$serial  = $data['serial'] ?? '';

$type_labels = [
    'debit_note'   => 'Debit Note',
    'credit_note'  => 'Credit Note',
    'vendor_recon' => 'Vendor Payable Reconciliation',
    'amex'         => 'AMEX Credit Card Authorization',
];
$type_label = $type_labels[$sub['form_type']] ?? $sub['form_type'];

$email_sent = false;
$email_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'email') {
    $pdf_path = STORAGE_PATH . $sub['pdf_filename'];
    $emails   = get_finance_emails();
    if (empty($emails)) {
        $email_error = 'No finance emails configured. Please ask an IT Admin to set them in Settings.';
    } elseif (!file_exists($pdf_path)) {
        $email_error = 'PDF file not found.';
    } else {
        $subject   = $type_label . ' — ' . $serial;
        $body_text = "Please find attached the {$type_label} ({$serial}).\n\nSubmitted by: {$user['name']}\nDate: " . date('d M Y H:i') . "\n\nThis email was sent via G2 Tools.";
        $ok = send_pdf_to_finance($subject, $body_text, $pdf_path, $dl_name);
        if ($ok) $email_sent = true;
        else $email_error = 'Failed to send email. Check mail server configuration or finance email settings.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Document Ready — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
  .confirm-wrap { max-width: 560px; margin: 0 auto; padding: 48px 24px 80px; }
  .success-badge { width: 64px; height: 64px; background: #f0fdf4; border: 2px solid #bbf7d0;
                   border-radius: 50%; display: flex; align-items: center; justify-content: center;
                   font-size: 28px; margin: 0 auto 20px; }
  .confirm-title { font-size: 22px; font-weight: 800; color: #1a1a1a; text-align: center; margin-bottom: 6px; }
  .confirm-sub   { font-size: 14px; color: #888; text-align: center; margin-bottom: 32px; }
  .confirm-sub strong { color: #555; }

  .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 24px; }
  .action-card { background: #fff; border-radius: 14px; border: 2px solid #e8eaee;
                 padding: 22px 20px; cursor: pointer; transition: border-color .15s;
                 text-decoration: none; color: inherit;
                 display: flex; flex-direction: column; align-items: flex-start; gap: 8px; }
  .action-card:hover { border-color: #FF3D33; }
  .action-card.active { border-color: #FF3D33; background: #fff8f8; }
  .ac-icon  { font-size: 26px; }
  .ac-title { font-size: 15px; font-weight: 700; color: #1a1a1a; }
  .ac-desc  { font-size: 12px; color: #999; line-height: 1.5; }

  .msg { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
  .msg-ok  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
  .msg-err { background: #fff5f5; border: 1px solid #fca5a5; color: #dc2626; }

  .send-btn { width: 100%; padding: 13px; background: #FF3D33; color: #fff; border: none;
              border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .2s; }
  .send-btn:hover { background: #c0170e; }
  .email-panel { background: #fff; border-radius: 14px; border: 1px solid #e8eaee;
                 padding: 22px; margin-bottom: 20px; display: none; }
  .email-panel.show { display: block; }
  .email-panel h3 { font-size: 14px; font-weight: 700; color: #1a1a1a; margin-bottom: 14px; }
  .email-to  { background: #f8f9fb; border-radius: 8px; padding: 10px 14px; font-size: 13px;
               color: #555; margin-bottom: 16px; border: 1px solid #e8eaee; }
  .email-to span { font-weight: 600; color: #1a1a1a; }
  .back-link { display: block; text-align: center; font-size: 13px; color: #aaa;
               text-decoration: none; margin-top: 8px; }
  .back-link:hover { color: #FF3D33; }
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/">G2 Tools</a>
  <span class="topbar-title">Document Ready</span>
</div>
<div class="confirm-wrap">
  <div class="success-badge">✓</div>
  <div class="confirm-title"><?= htmlspecialchars($type_label) ?> Generated</div>
  <div class="confirm-sub">
    <strong><?= htmlspecialchars($serial) ?></strong><br>
    Choose what to do with it:
  </div>

  <?php if ($email_sent): ?>
    <div class="msg msg-ok">✓ Sent to finance team successfully.</div>
  <?php endif; ?>
  <?php if ($email_error): ?>
    <div class="msg msg-err"><?= htmlspecialchars($email_error) ?></div>
  <?php endif; ?>

  <div class="action-grid">
    <a class="action-card" href="/download.php?id=<?= $sub_id ?>">
      <span class="ac-icon">⬇️</span>
      <span class="ac-title">Download PDF</span>
      <span class="ac-desc">Save the PDF directly to your device.</span>
    </a>
    <div class="action-card" id="emailToggle" onclick="toggleEmail()">
      <span class="ac-icon">✉️</span>
      <span class="ac-title">Send to Finance</span>
      <span class="ac-desc">Email the PDF to the finance team.</span>
    </div>
  </div>

  <div class="email-panel" id="emailPanel">
    <h3>Send to Finance Team</h3>
    <?php
      $emails = get_finance_emails();
      if ($emails):
    ?>
    <div class="email-to">Will be sent to: <span><?= htmlspecialchars(implode(', ', $emails)) ?></span></div>
    <?php else: ?>
    <div class="msg msg-err">No finance emails configured. Go to Admin → Settings.</div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="email">
      <button type="submit" class="send-btn" <?= empty($emails) ? 'disabled' : '' ?>>✉ Send to Finance</button>
    </form>
  </div>

  <a class="back-link" href="/">← Back to G2 Tools</a>
</div>
</div>
<script>
function toggleEmail() {
  const p = document.getElementById('emailPanel');
  const t = document.getElementById('emailToggle');
  const show = !p.classList.contains('show');
  p.classList.toggle('show', show);
  t.classList.toggle('active', show);
}
<?php if ($email_error): ?>document.addEventListener('DOMContentLoaded', toggleEmail);<?php endif; ?>
</script>
</body>
</html>
