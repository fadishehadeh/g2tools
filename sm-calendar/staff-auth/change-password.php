<?php
require_once __DIR__ . '/../lib/bootstrap.php';
sm_require_staff();
$staff = sm_current_staff();
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = sm_db()->prepare("SELECT password_hash FROM " . g2_users_table() . " WHERE id=?");
    $stmt->execute([$staff['id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'Current password is incorrect.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } elseif (!sm_password_valid($new)) {
        $error = 'Password must have: ' . implode(', ', sm_password_errors($new)) . '.';
    } else {
        sm_db()->prepare("UPDATE " . g2_users_table() . " SET password_hash=? WHERE id=?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $staff['id']]);
        $success = 'Password updated successfully. This also updates your G2 Tools login.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Change Password — G2 SM Calendar Tool</title>
<link rel="stylesheet" href="/g2forms/sm-calendar/sm.css">
</head>
<body class="sm-auth-bg">
<div class="sm-auth-card">
  <div class="sm-auth-brand"><span class="sm-auth-dot"></span> G2 SM Calendar Tool</div>
  <h1>Change Password</h1>
  <p class="sm-auth-sub">This updates your shared G2 Tools password.</p>

  <?php if ($error): ?><div class="sm-msg sm-msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="sm-msg sm-msg-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST">
    <div class="sm-field"><label>Current Password</label><input type="password" name="current_password" required></div>
    <div class="sm-field"><label>New Password</label><input type="password" name="new_password" required></div>
    <div class="sm-field"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
    <p style="font-size:11px;color:#aaa;margin:-6px 0 14px">Min 10 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character.</p>
    <button type="submit" class="sm-btn-primary">Update Password</button>
  </form>
  <a class="sm-auth-alt" href="<?= SM_BASE_URL ?>/">← Back to dashboard</a>
</div>
</body>
</html>
