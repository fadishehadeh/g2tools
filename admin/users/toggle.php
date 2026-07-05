<?php
session_start();
require '../../config.php';
require_login();
if (!is_finance_admin()) { header('Location: /'); exit; }

$id = (int)($_POST['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Can't deactivate yourself
if ($id === (int)($_SESSION['g2_user']['id'] ?? 0)) {
    $_SESSION['flash'] = ['type'=>'err','msg'=>'You cannot deactivate your own account.'];
    header('Location: index.php'); exit;
}

$u = db()->prepare("SELECT role, is_active FROM users WHERE id=?");
$u->execute([$id]); $u = $u->fetch();
if (!$u) { header('Location: index.php'); exit; }

// Only superadmin can deactivate superadmins
if ($u['role'] === 'superadmin' && !is_superadmin()) {
    $_SESSION['flash'] = ['type'=>'err','msg'=>'Only a Super Admin can deactivate another Super Admin.'];
    header('Location: index.php'); exit;
}

$new = $u['is_active'] ? 0 : 1;
db()->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$new, $id]);
$_SESSION['flash'] = ['type'=>'ok','msg' => $new ? 'User activated.' : 'User deactivated.'];
header('Location: index.php'); exit;
