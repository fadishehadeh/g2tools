<?php
session_start();
require 'config.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$err   = $msg = '';

$stmt = db()->prepare("SELECT t.*, u.name, u.email FROM password_reset_tokens t JOIN users u ON u.id=t.user_id WHERE t.token=? AND t.used=0 AND t.expires_at > NOW()");
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$token || !$row) {
    $err = 'This reset link is invalid or has expired. <a href="/forgot-password.php">Request a new one</a>.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {
    $pw  = $_POST['password'] ?? '';
    $pw2 = $_POST['password2'] ?? '';
    if (strlen($pw) < 6) {
        $err = 'Password must be at least 6 characters.';
    } elseif ($pw !== $pw2) {
        $err = 'Passwords do not match.';
    } else {
        $hash = password_hash($pw, PASSWORD_BCRYPT);
        db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $row['user_id']]);
        db()->prepare("UPDATE password_reset_tokens SET used=1 WHERE token=?")->execute([$token]);
        $_SESSION['flash'] = ['type'=>'ok','msg'=>'Password updated. Please log in.'];
        header('Location: /login.php'); exit;
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password — G2 Tools</title>
<link rel="stylesheet" href="/form.css">
<style>
body{background:#f2f3f6;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.login-card{background:#fff;border-radius:14px;padding:40px;width:100%;max-width:380px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.login-logo{font-size:22px;font-weight:900;color:#1a1a1a;margin-bottom:6px}
.login-logo span{color:#FF3D33}
.login-sub{font-size:13px;color:#aaa;margin-bottom:28px}
.field label{display:block;font-size:12px;font-weight:700;color:#888;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px}
.field input{width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid #e8eaee;border-radius:8px;font-size:14px;outline:none}
.field input:focus{border-color:#FF3D33}
.field{margin-bottom:16px}
.btn-full{width:100%;padding:12px;background:#FF3D33;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;margin-top:4px}
.btn-full:hover{background:#c0170e}
.err{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;background:#fff5f5;border:1px solid #fca5a5;color:#dc2626}
.back{text-align:center;margin-top:18px;font-size:13px;color:#aaa}
.back a{color:#FF3D33;text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">G2<span>Tools</span></div>
  <div class="login-sub">Set your new password</div>
  <?php if ($err): ?>
  <div class="err"><?= $err ?></div>
  <?php endif; ?>
  <?php if ($row && !$err): ?>
  <form method="POST">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <div class="field">
      <label>New Password</label>
      <input type="password" name="password" required minlength="6" autofocus>
    </div>
    <div class="field">
      <label>Confirm Password</label>
      <input type="password" name="password2" required minlength="6">
    </div>
    <button type="submit" class="btn-full">Set New Password</button>
  </form>
  <?php endif; ?>
  <div class="back"><a href="/login.php">← Back to Login</a></div>
</div>
</body>
</html>
