<?php
session_start();
require 'config.php';

if (current_user()) { header('Location: ' . BASE_URL . '/'); exit; }

$error = '';
$redirect = $_GET['redirect'] ?? (BASE_URL . '/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if ($email && $pass) {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();
        $pw_field = isset($user['password_hash']) ? 'password_hash' : 'password';
        if ($user && password_verify($pass, $user[$pw_field])) {
            if (isset($user['is_active']) && !$user['is_active']) {
                $error = 'Your account has been deactivated. Please contact IT.';
            } elseif (($user['status'] ?? 'active') === 'pending') {
                $error = 'Your account is pending admin approval.';
            } elseif (($user['status'] ?? 'active') === 'rejected') {
                $error = 'Your registration was not approved. Please contact IT.';
            } else {
                $_SESSION['g2_user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'] ?? '', 'role' => $user['role'] ?? 'user', 'office' => $user['office'] ?? '', 'access_modules' => $user['access_modules'] ?? '[]'];
                $dest = filter_var($redirect, FILTER_VALIDATE_URL) ? BASE_URL . '/' : $redirect;
                header('Location: ' . $dest);
                exit;
            }
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Invalid email or password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — G2 Forms</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f5f7; min-height: 100vh;
         display: flex; flex-direction: column; }

  nav { background: #fff; border-bottom: 1px solid #e8eaee; padding: 0 48px; height: 64px;
        display: flex; align-items: center; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
  .logo { display: flex; align-items: flex-end; text-decoration: none; line-height: 1; }
  .logo-g2  { font-size: 28px; font-weight: 900; color: #FF3D33; letter-spacing: -2px; }
  .logo-dot { font-size: 28px; font-weight: 900; color: #bbb; }
  .logo-sub { font-size: 10px; color: #bbb; letter-spacing: 1.5px; text-transform: uppercase;
               margin-left: 3px; margin-bottom: 4px; font-weight: 500; }

  main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 24px; }

  .login-card { background: #fff; border-radius: 16px; border: 1px solid #e8eaee;
                box-shadow: 0 4px 24px rgba(0,0,0,.07); padding: 44px 48px; width: 100%; max-width: 420px; }

  .card-badge { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: 2px;
                text-transform: uppercase; color: #ff8070; background: rgba(255,61,51,.1);
                border: 1px solid rgba(255,61,51,.25); padding: 4px 14px; border-radius: 20px; margin-bottom: 18px; }

  .login-card h1 { font-size: 24px; font-weight: 800; color: #1a1a1a; margin-bottom: 6px; }
  .login-card p  { font-size: 14px; color: #999; margin-bottom: 32px; }

  label { display: block; font-size: 12px; font-weight: 600; color: #555; letter-spacing: 0.4px;
          text-transform: uppercase; margin-bottom: 6px; }
  input[type=email], input[type=password] {
    width: 100%; padding: 11px 14px; border: 1.5px solid #dde1e7; border-radius: 8px;
    font-size: 14px; color: #1a1a1a; outline: none; transition: border-color .2s;
    font-family: inherit; background: #fff;
  }
  input:focus { border-color: #FF3D33; }
  .field { margin-bottom: 20px; }

  .error-box { background: #fff5f5; border: 1px solid #fca5a5; border-radius: 8px;
               padding: 10px 14px; font-size: 13px; color: #dc2626; margin-bottom: 20px; }

  button { width: 100%; padding: 13px; background: #FF3D33; color: #fff; border: none;
           border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer;
           transition: background .2s; margin-top: 4px; }
  button:hover { background: #e8302a; }

  footer { text-align: center; padding: 16px; font-size: 12px; color: #c8c8c8;
           border-top: 1px solid #e8eaee; background: #fff; }
  footer b { color: #FF3D33; }
</style>
</head>
<body>

<nav>
  <a class="logo" href="<?= BASE_URL ?>/">
    <img src="/g2forms/logo.png" style="height:36px;display:block">
  </a>
</nav>

<main>
  <div class="login-card">
    <div class="card-badge">Internal Portal</div>
    <h1>Sign in</h1>
    <p>Access the G2 Forms system</p>

    <?php if ($error): ?>
      <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" placeholder="you@g2group.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit">Sign in →</button>
    </form>
    <p style="text-align:center;margin-top:20px;font-size:13px;color:#aaa">
      Don't have an account?
      <a href="<?= BASE_URL ?>/register.php" style="color:#FF3D33;text-decoration:none;font-weight:600">Request access</a>
    </p>
  </div>
</main>

<footer><b>G2.</b> Group &nbsp;—&nbsp; Internal Use Only</footer>
</body>
</html>
