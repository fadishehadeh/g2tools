<?php
// Run once at: http://localhost/g2forms/setup.php
// Creates the database, tables, and default admin accounts.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');

$ok = []; $errors = [];

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS g2forms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE g2forms");
    $ok[] = "Database <b>g2forms</b> ready";

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(100)  NOT NULL,
        email         VARCHAR(150)  NOT NULL UNIQUE,
        password_hash VARCHAR(255)  NOT NULL,
        role          ENUM('user','finance_admin','it_admin') NOT NULL DEFAULT 'user',
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS form_submissions (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        user_id      INT NOT NULL,
        form_type    ENUM('amex','accountability') NOT NULL,
        form_data    JSON NOT NULL,
        pdf_filename VARCHAR(255),
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $ok[] = "Tables created";

    $seeds = [
        ['IT Admin',      'admin@g2group.com',   'Admin@123',   'it_admin'],
        ['Finance Admin', 'finance@g2group.com', 'Finance@123', 'finance_admin'],
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO users (name,email,password_hash,role) VALUES (?,?,?,?)");
    foreach ($seeds as [$name, $email, $pass, $role]) {
        $ins->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
        if ($ins->rowCount()) $ok[] = "Created account: <b>$email</b>";
    }

    $dir = __DIR__ . '/storage/pdfs';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ok[] = "Storage directory ready";

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}
?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>G2 Forms — Setup</title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background:#f4f5f7; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
  .box { background:#fff; border-radius:14px; padding:40px 48px; box-shadow:0 4px 24px rgba(0,0,0,.08); max-width:480px; width:100%; }
  h2 { color:#1a1a1a; margin-bottom:24px; }
  .item { padding:8px 0; border-bottom:1px solid #f0f0f0; font-size:14px; color:#444; }
  .item:last-child { border:none; }
  .ok::before  { content:'✓ '; color:#22c55e; font-weight:700; }
  .err::before { content:'✗ '; color:#ef4444; font-weight:700; }
  .accounts { background:#f8f9fb; border-radius:8px; padding:16px 20px; margin:20px 0; font-size:13px; }
  .accounts h4 { margin:0 0 10px; color:#555; font-size:11px; letter-spacing:1px; text-transform:uppercase; }
  .accounts p { margin:4px 0; color:#333; }
  .btn { display:inline-block; margin-top:20px; background:#FF3D33; color:#fff; padding:10px 24px; border-radius:8px; text-decoration:none; font-weight:600; font-size:14px; }
  .logo-g2 { color:#FF3D33; font-size:26px; font-weight:900; }
</style>
</head>
<body>
<div class="box">
  <div style="margin-bottom:20px"><span class="logo-g2">G2</span><span style="color:#bbb;font-size:26px;font-weight:900">.</span></div>
  <h2>Setup Complete</h2>
  <?php foreach ($ok as $m): ?>
    <div class="item ok"><?= $m ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $e): ?>
    <div class="item err"><?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>
  <?php if (!$errors): ?>
  <div class="accounts">
    <h4>Default Accounts</h4>
    <p><b>IT Admin:</b> admin@g2group.com / Admin@123</p>
    <p><b>Finance Admin:</b> finance@g2group.com / Finance@123</p>
    <p style="margin-top:8px;color:#999;font-size:12px">Change these passwords after first login.</p>
  </div>
  <a class="btn" href="/login.php">Go to Login →</a>
  <?php endif; ?>
</div>
</body>
</html>
