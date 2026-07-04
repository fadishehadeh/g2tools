<?php
session_start();
require '../config.php';
require_login();

$sub_id = (int)($_GET['id'] ?? 0);
if (!$sub_id) { header('Location: /g2forms/'); exit; }

$user = current_user();
$sub  = db()->prepare("SELECT * FROM form_submissions WHERE id = ?");
$sub->execute([$sub_id]);
$sub  = $sub->fetch();

if (!$sub || (!is_admin() && (int)$sub['user_id'] !== (int)$user['id'])) {
    http_response_code(403); echo 'Access denied.'; exit;
}

$data     = json_decode($sub['form_data'], true);
$dl_name  = $data['dl_name'] ?? 'AMEX_Authorization.pdf';
$merchant = $data['merchant'] ?? '';

// ── Handle email send ─────────────────────────────────────────────────────────
$email_sent = false;
$email_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'email') {
    $to1     = trim($_POST['email1'] ?? '');
    $to2     = trim($_POST['email2'] ?? '');
    $to3     = trim($_POST['email3'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $recipients = array_filter([$to1, $to2, $to3], fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));

    if (empty($recipients)) {
        $email_error = 'Please enter at least one valid email address.';
    } else {
        $pdf_path = STORAGE_PATH . $sub['pdf_filename'];
        if (!file_exists($pdf_path)) {
            $email_error = 'PDF file not found on server.';
        } else {
            $pdf_content = file_get_contents($pdf_path);
            $encoded     = base64_encode($pdf_content);
            $boundary    = '----=_Part_' . uniqid();

            $from_name  = $user['name'];
            $from_email = $user['email'];
            $subject    = 'AMEX Credit Card Authorization — ' . $merchant;

            $body_text = "Please find attached the AMEX Credit Card Authorization form for: {$merchant}.\n\n";
            if ($message) $body_text .= "Message from {$from_name}:\n{$message}\n\n";
            $body_text .= "This email was sent via G2 Tools.";

            $headers  = "From: {$from_name} <{$from_email}>\r\n";
            $headers .= "Reply-To: {$from_email}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $body .= $body_text . "\r\n\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/pdf; name=\"{$dl_name}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$dl_name}\"\r\n\r\n";
            $body .= chunk_split($encoded) . "\r\n";
            $body .= "--{$boundary}--";

            $to_str = implode(', ', $recipients);
            $ok = mail($to_str, $subject, $body, $headers);
            if ($ok) {
                $email_sent = true;
            } else {
                $email_error = 'Failed to send email. Check your server mail configuration.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Form Ready — G2 Tools</title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<link rel="stylesheet" href="/g2forms/form.css">
<style>
  .confirm-wrap { max-width: 620px; margin: 0 auto; padding: 48px 24px 80px; }

  .success-badge {
    width: 64px; height: 64px; background: #f0fdf4; border: 2px solid #bbf7d0;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 28px; margin: 0 auto 20px;
  }
  .confirm-title { font-size: 22px; font-weight: 800; color: #1a1a1a; text-align: center; margin-bottom: 6px; }
  .confirm-sub   { font-size: 14px; color: #888; text-align: center; margin-bottom: 36px; }

  .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px; }

  .action-card {
    background: #fff; border-radius: 14px; border: 2px solid #e8eaee;
    padding: 24px 22px; cursor: pointer; transition: border-color .15s;
    text-decoration: none; color: inherit; display: flex; flex-direction: column; align-items: flex-start; gap: 10px;
  }
  .action-card:hover { border-color: #FF3D33; }
  .action-card.active { border-color: #FF3D33; background: #fff8f8; }

  .ac-icon { font-size: 28px; }
  .ac-title { font-size: 15px; font-weight: 700; color: #1a1a1a; }
  .ac-desc  { font-size: 12px; color: #999; line-height: 1.5; }

  .email-panel {
    background: #fff; border-radius: 14px; border: 1px solid #e8eaee;
    padding: 28px; margin-bottom: 24px; display: none;
  }
  .email-panel.show { display: block; }
  .email-panel h3 { font-size: 14px; font-weight: 700; color: #1a1a1a; margin-bottom: 18px; }

  .field { margin-bottom: 14px; }
  .field label { display: block; font-size: 12px; font-weight: 600; color: #666;
                 text-transform: uppercase; letter-spacing: .3px; margin-bottom: 5px; }
  .field input, .field textarea {
    width: 100%; padding: 10px 14px; border: 1.5px solid #e8eaee; border-radius: 8px;
    font-size: 14px; font-family: inherit; color: #1a1a1a; transition: border-color .18s;
  }
  .field input:focus, .field textarea:focus { outline: none; border-color: #FF3D33; box-shadow: 0 0 0 3px rgba(255,61,51,.1); }
  .field textarea { resize: vertical; min-height: 72px; }

  .send-btn {
    width: 100%; padding: 13px; background: #FF3D33; color: #fff; border: none;
    border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .2s;
  }
  .send-btn:hover { background: #c0170e; }

  .msg { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
  .msg-ok  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
  .msg-err { background: #fff5f5; border: 1px solid #fca5a5; color: #dc2626; }

  .back-link { display: block; text-align: center; font-size: 13px; color: #aaa; text-decoration: none; margin-top: 8px; }
  .back-link:hover { color: #FF3D33; }
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>

<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/g2forms/amex/">New Form</a>
  <span class="topbar-title">Form Ready</span>
</div>

<div class="confirm-wrap">
  <div class="success-badge">✓</div>
  <div class="confirm-title">Form Generated Successfully</div>
  <div class="confirm-sub">
    <strong><?= htmlspecialchars($dl_name) ?></strong><br>
    Choose what to do with it:
  </div>

  <?php if ($email_sent): ?>
  <div class="msg msg-ok">Email sent successfully to the specified recipients.</div>
  <?php endif; ?>
  <?php if ($email_error): ?>
  <div class="msg msg-err"><?= htmlspecialchars($email_error) ?></div>
  <?php endif; ?>

  <div class="action-grid">
    <a class="action-card" href="/g2forms/download.php?id=<?= $sub_id ?>">
      <span class="ac-icon">⬇️</span>
      <span class="ac-title">Download PDF</span>
      <span class="ac-desc">Save the PDF directly to your device.</span>
    </a>
    <div class="action-card" id="emailToggle" onclick="toggleEmail()">
      <span class="ac-icon">✉️</span>
      <span class="ac-title">Send by Email</span>
      <span class="ac-desc">Send the PDF to one or more recipients.</span>
    </div>
  </div>

  <div class="email-panel" id="emailPanel">
    <h3>Send PDF by Email</h3>
    <?php if ($email_sent): ?>
      <div class="msg msg-ok">Email sent! You can send again if needed.</div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="email">
      <div class="field">
        <label>Recipient 1 <span style="color:#FF3D33">*</span></label>
        <input type="email" name="email1" placeholder="name@example.com" required>
      </div>
      <div class="field">
        <label>Recipient 2</label>
        <input type="email" name="email2" placeholder="name@example.com">
      </div>
      <div class="field" id="email3field" style="display:none">
        <label>Recipient 3</label>
        <input type="email" name="email3" placeholder="name@example.com">
      </div>
      <div style="margin-bottom:14px">
        <a href="#" onclick="document.getElementById('email3field').style.display='block';this.style.display='none';return false"
           style="font-size:12px;color:#FF3D33;text-decoration:none">+ Add another recipient</a>
      </div>
      <div class="field">
        <label>Message (optional)</label>
        <textarea name="message" placeholder="Add a note to the email…"></textarea>
      </div>
      <button type="submit" class="send-btn">✉ Send Email</button>
    </form>
  </div>

  <a class="back-link" href="/g2forms/">← Back to G2 Tools</a>
</div>
</div>

<script>
function toggleEmail() {
  const panel  = document.getElementById('emailPanel');
  const toggle = document.getElementById('emailToggle');
  const show   = !panel.classList.contains('show');
  panel.classList.toggle('show', show);
  toggle.classList.toggle('active', show);
}
<?php if ($email_error): ?>
document.addEventListener('DOMContentLoaded', () => toggleEmail());
<?php endif; ?>
</script>
</body>
</html>
