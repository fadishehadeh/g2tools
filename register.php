<?php
session_start();
require 'config.php';

if (current_user()) { header('Location: ' . BASE_URL . '/'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']     ?? '');
    $email = trim($_POST['email']    ?? '');
    $pass  = $_POST['password']      ?? '';
    $pass2 = $_POST['password2']     ?? '';

    if (!$name || !$email || !$pass) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $stmt = db()->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES (?,?,?,'user','pending')");
            $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $success = true;
        } catch (PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate')
                ? 'An account with this email already exists.'
                : 'Registration failed. Please try again.';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — G2 Forms</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f5f7; min-height: 100vh;
         display: flex; flex-direction: column; }

  nav { background: #fff; border-bottom: 1px solid #e8eaee; padding: 0 48px; height: 64px;
        display: flex; align-items: center; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
  .logo { display: flex; align-items: center; text-decoration: none; }

  main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 24px; }

  .card { background: #fff; border-radius: 16px; border: 1px solid #e8eaee;
          box-shadow: 0 4px 24px rgba(0,0,0,.07); padding: 44px 48px; width: 100%; max-width: 440px; }

  .card-badge { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: 2px;
                text-transform: uppercase; color: #ff8070; background: rgba(255,61,51,.1);
                border: 1px solid rgba(255,61,51,.25); padding: 4px 14px; border-radius: 20px; margin-bottom: 18px; }
  .card h1 { font-size: 24px; font-weight: 800; color: #1a1a1a; margin-bottom: 6px; }
  .card p  { font-size: 14px; color: #999; margin-bottom: 28px; }

  .field { margin-bottom: 18px; }
  label { display: block; font-size: 12px; font-weight: 600; color: #555; letter-spacing: 0.4px;
          text-transform: uppercase; margin-bottom: 6px; }
  input { width: 100%; padding: 11px 14px; border: 1.5px solid #dde1e7; border-radius: 8px;
          font-size: 14px; color: #1a1a1a; outline: none; transition: border-color .2s; font-family: inherit; }
  input:focus { border-color: #FF3D33; }

  .msg { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; }
  .msg-err { background: #fff5f5; border: 1px solid #fca5a5; color: #dc2626; }
  .msg-ok  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }

  button { width: 100%; padding: 13px; background: #FF3D33; color: #fff; border: none;
           border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; margin-top: 4px; }
  button:hover { background: #e8302a; }

  .bottom-link { text-align: center; margin-top: 20px; font-size: 13px; color: #aaa; }
  .bottom-link a { color: #FF3D33; text-decoration: none; font-weight: 600; }

  .success-icon { font-size: 48px; text-align: center; margin-bottom: 16px; }
  .success-title { font-size: 22px; font-weight: 800; color: #1a1a1a; text-align: center; margin-bottom: 10px; }
  .success-text  { font-size: 14px; color: #888; text-align: center; line-height: 1.7; margin-bottom: 28px; }
  .back-btn { display: block; text-align: center; padding: 12px; background: #1a1a1a; color: #fff;
              border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px; }

  footer { text-align: center; padding: 16px; font-size: 12px; color: #c8c8c8;
           border-top: 1px solid #e8eaee; background: #fff; }
  footer b { color: #FF3D33; }
</style>
</head>
<body>
<nav>
  <a class="logo" href="<?= BASE_URL ?>/login.php">
    <img src="/logo.png" style="height:36px;display:block">
  </a>
</nav>

<main>
  <div class="card">
    <?php if ($success): ?>
      <div class="success-icon">📬</div>
      <div class="success-title">Registration Submitted</div>
      <p class="success-text">
        Your account is pending admin approval.<br>
        You'll be able to sign in once an administrator activates your account.
      </p>
      <a class="back-btn" href="<?= BASE_URL ?>/login.php">Back to Sign In</a>

    <?php else: ?>
      <div class="card-badge">New Account</div>
      <h1>Create account</h1>
      <p>Request access to the G2 Forms portal</p>

      <?php if ($error): ?>
        <div class="msg msg-err"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="field">
          <label>Full Name</label>
          <input type="text" name="name" placeholder="Your full name"
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autofocus>
        </div>
        <div class="field">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="you@company.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" placeholder="Min. 6 characters" required>
        </div>
        <div class="field">
          <label>Confirm Password</label>
          <input type="password" name="password2" placeholder="Repeat password" required>
        </div>
        <button type="submit">Request Access →</button>
      </form>

      <div class="bottom-link">Already have an account? <a href="<?= BASE_URL ?>/login.php">Sign in</a></div>
    <?php endif; ?>
  </div>
</main>

<footer><b>G2.</b> Group &nbsp;—&nbsp; Internal Use Only</footer>
</body>
</html>
