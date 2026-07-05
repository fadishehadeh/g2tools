<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if (sm_current_staff()) { header('Location: ' . SM_BASE_URL . '/'); exit; }

$error = '';
$redirect = $_GET['redirect'] ?? (SM_BASE_URL . '/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email && $pass) {
        $stmt = sm_db()->prepare("SELECT * FROM " . g2_users_table() . " WHERE email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif (($user['status'] ?? 'active') !== 'active') {
            $error = 'Your G2 Tools account is not active.';
        } else {
            $a = sm_db()->prepare("SELECT sm_role FROM sm_user_access WHERE user_id=?");
            $a->execute([$user['id']]);
            $access = $a->fetch();

            if (!$access) {
                $error = "You don't have access to G2 SM Calendar Tool. Contact your IT Admin to request access.";
            } else {
                $code = sm_otp_create('staff', $user['id'], 'login');
                $sent = sm_otp_send_email($user['email'], $user['name'], $code);

                $_SESSION['sm_pending_staff_id'] = $user['id'];
                $_SESSION['sm_pending_redirect']  = $redirect;
                // Dev convenience only: email isn't configured yet, so surface the code on screen
                // instead of silently requiring a log lookup. Cleared as soon as it's verified.
                if (!$sent) $_SESSION['sm_dev_otp'] = $code;
                else unset($_SESSION['sm_dev_otp']);
                header('Location: ' . SM_BASE_URL . '/staff-auth/verify-otp.php');
                exit;
            }
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Login — G2 SM Calendar Tool</title>
<link rel="stylesheet" href="/sm-calendar/sm.css">
</head>
<body class="sm-auth-bg">
<div class="sm-auth-card">
  <div class="sm-auth-brand">
    <span class="sm-auth-dot"></span> G2 SM Calendar Tool
  </div>
  <h1>Staff Sign In</h1>
  <p class="sm-auth-sub">Use your existing G2 Tools credentials. You'll need calendar access granted by an IT Admin.</p>

  <?php if ($error): ?>
  <div class="sm-msg sm-msg-err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="sm-field">
      <label>Email</label>
      <input type="email" name="email" placeholder="you@g2group.com" required autofocus>
    </div>
    <div class="sm-field">
      <label>Password</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="sm-btn-primary">Continue</button>
  </form>

  <a class="sm-auth-alt" href="/sm-calendar/client-auth/login.php">Client? Sign in to the review portal →</a>
</div>
</body>
</html>
