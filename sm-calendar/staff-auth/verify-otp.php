<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$pendingId = $_SESSION['sm_pending_staff_id'] ?? null;
if (!$pendingId) { header('Location: ' . SM_BASE_URL . '/staff-auth/login.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    if (sm_otp_verify('staff', (int)$pendingId, 'login', $code)) {
        $token = sm_create_staff_session((int)$pendingId);
        setcookie('sm_staff_token', $token, time() + 7 * 86400, SM_BASE_URL, '', false, true);

        $redirect = $_SESSION['sm_pending_redirect'] ?? (SM_BASE_URL . '/');
        unset($_SESSION['sm_pending_staff_id'], $_SESSION['sm_pending_redirect'], $_SESSION['sm_dev_otp']);
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Invalid or expired code. Please try again.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify Code — G2 SM Calendar Tool</title>
<link rel="stylesheet" href="/sm-calendar/sm.css">
</head>
<body class="sm-auth-bg">
<div class="sm-auth-card">
  <div class="sm-auth-brand"><span class="sm-auth-dot"></span> G2 SM Calendar Tool</div>
  <h1>Enter Verification Code</h1>
  <p class="sm-auth-sub">We've sent a 6-digit code to your email. It expires in 10 minutes.</p>

  <?php if ($error): ?>
  <div class="sm-msg sm-msg-err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['sm_dev_otp'])): ?>
  <div class="sm-msg" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e">
    <strong style="display:block;font-size:10px;letter-spacing:1px;text-transform:uppercase;margin-bottom:4px">Dev Mode — Email Not Configured</strong>
    Your code is: <strong style="font-size:16px;letter-spacing:2px"><?= htmlspecialchars($_SESSION['sm_dev_otp']) ?></strong>
  </div>
  <?php endif; ?>

  <form method="POST">
    <div class="sm-field">
      <label>Verification Code</label>
      <input type="text" name="code" inputmode="numeric" maxlength="6" placeholder="000000" required autofocus style="letter-spacing:6px;font-size:20px;text-align:center;font-weight:700">
    </div>
    <button type="submit" class="sm-btn-primary">Verify &amp; Continue</button>
  </form>

  <a class="sm-auth-alt" href="/sm-calendar/staff-auth/login.php">← Back to login</a>
</div>
</body>
</html>
