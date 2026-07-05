<?php
session_start();
require 'config.php';
require 'mailer.php';

// Create token table if not exists
db()->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token VARCHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0
)");

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $stmt  = db()->prepare("SELECT id, name, username FROM users WHERE email=? AND is_active=1");
    $stmt->execute([$email]);
    $user  = $stmt->fetch();

    // Always show success to prevent user enumeration
    if ($user) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        db()->prepare("DELETE FROM password_reset_tokens WHERE user_id=?")->execute([$user['id']]);
        db()->prepare("INSERT INTO password_reset_tokens (token,user_id,expires_at) VALUES (?,?,?)")
           ->execute([$token, $user['id'], $expires]);

        $link = 'https://g2tools.greydoha.com/reset-password.php?token='.$token;
        $body = mail_template('Reset Your Password', "
            <p>Hi <strong>".htmlspecialchars($user['name'])."</strong>,</p>
            <p>We received a request to reset your G2 Tools password. Click the button below to set a new password. This link expires in <strong>1 hour</strong>.</p>
            <a class='btn' href='".htmlspecialchars($link)."'>Reset Password</a>
            <p style='margin-top:20px;font-size:12px;color:#aaa'>If you didn't request this, you can safely ignore this email.</p>");
        send_mail(['email'=>$email,'name'=>$user['name']], 'Reset Your G2 Tools Password', $body);
    }
    $msg = 'If that email is registered, a reset link has been sent.';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password — G2 Tools</title>
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
.msg{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.back{text-align:center;margin-top:18px;font-size:13px;color:#aaa}
.back a{color:#FF3D33;text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">G2<span>Tools</span></div>
  <div class="login-sub">Enter your email to receive a reset link</div>
  <?php if ($msg): ?>
  <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="field">
      <label>Email Address</label>
      <input type="email" name="email" required autofocus placeholder="you@greydoha.com">
    </div>
    <button type="submit" class="btn-full">Send Reset Link</button>
  </form>
  <div class="back"><a href="/login.php">← Back to Login</a></div>
</div>
</body>
</html>
