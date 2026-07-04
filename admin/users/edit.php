<?php
session_start();
require '../../config.php';
require_login();
if (!is_finance_admin()) { header('Location: /g2forms/'); exit; }

$id = (int)($_GET['id'] ?? 0);
$u  = db()->prepare("SELECT * FROM users WHERE id=?");
$u->execute([$id]); $u = $u->fetch();
if (!$u) { header('Location: index.php'); exit; }

// Only superadmin can edit superadmins
if ($u['role'] === 'superadmin' && !is_superadmin()) { header('Location: index.php'); exit; }

$error = '';
$MODULES = [
    'finance'    => 'Finance Forms (Credit Card, Accountability, Debit/Credit Note, Vendor Recon)',
    'petty_cash' => 'Petty Cash',
    'vendor'     => 'Vendor Registration',
    'assets'     => 'Asset Management',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim(strip_tags($_POST['name'] ?? ''));
    $email   = trim($_POST['email'] ?? '');
    $role    = $_POST['role'] ?? $u['role'];
    $office  = $_POST['office'] ?? '';
    $modules = $_POST['modules'] ?? [];
    $newpw   = $_POST['new_password'] ?? '';

    if ($role === 'superadmin' && !is_superadmin()) $role = $u['role'];
    if (!array_key_exists($role, ROLES)) $role = 'user';

    if (!$name) { $error = 'Name is required.'; }
    elseif ($newpw && strlen($newpw) < 6) { $error = 'New password must be at least 6 characters.'; }
    else {
        $mods = ($role === 'user') ? json_encode(array_values(array_filter($modules))) : null;
        if ($newpw) {
            $hash = password_hash($newpw, PASSWORD_BCRYPT);
            db()->prepare("UPDATE users SET name=?,email=?,role=?,office=?,access_modules=?,password=? WHERE id=?")
               ->execute([$name,$email,$role,$office?:null,$mods,$hash,$id]);
        } else {
            db()->prepare("UPDATE users SET name=?,email=?,role=?,office=?,access_modules=? WHERE id=?")
               ->execute([$name,$email,$role,$office?:null,$mods,$id]);
        }
        $_SESSION['flash'] = ['type'=>'ok','msg'=>"User '$name' updated."];
        header('Location: index.php'); exit;
    }
}

$sel_modules = json_decode($u['access_modules'] ?? '[]', true) ?: [];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit User — G2 Tools</title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<link rel="stylesheet" href="/g2forms/form.css">
<style>
.form-card{max-width:600px}
.modules-grid{display:grid;gap:10px;margin-top:6px}
.mod-item{display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border:1.5px solid #e8eaee;border-radius:8px;cursor:pointer}
.mod-item:hover{border-color:#ccc}
.mod-item input[type=checkbox]{margin-top:2px;accent-color:#FF3D33;width:15px;height:15px;flex-shrink:0}
.mod-item label{font-size:13px;color:#444;cursor:pointer;line-height:1.4}
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Users</a>
  <span class="topbar-title">Edit User</span>
</div>
<div class="form-page-wrap">
<div class="form-card">
  <div class="form-header">
    <div class="fh-text"><h1>Edit <?= htmlspecialchars($u['name']) ?></h1><p>Update account details and access</p></div>
    <div class="fh-accent">✏️</div>
  </div>
  <div class="form-accent-bar"></div>

  <?php if ($error): ?>
  <div style="margin:16px 24px;padding:12px 16px;background:#fff5f5;border:1px solid #fca5a5;border-radius:8px;font-size:13px;color:#dc2626"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
  <div class="form-body">
    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Account Details</h2></div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Full Name <span style="color:#FF3D33">*</span></label>
          <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? $u['name']) ?>">
        </div>
        <div class="field">
          <label class="field-label">Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $u['email'] ?? '') ?>">
        </div>
      </div>
      <div class="field" style="max-width:280px">
        <label class="field-label">Username</label>
        <input type="text" value="<?= htmlspecialchars($u['username']) ?>" disabled style="background:#f5f6f8;color:#aaa">
        <span style="font-size:11px;color:#aaa">Username cannot be changed</span>
      </div>
      <div class="field" style="max-width:280px">
        <label class="field-label">New Password <span style="font-size:11px;color:#aaa">(leave blank to keep current)</span></label>
        <input type="password" name="new_password" autocomplete="new-password" minlength="6" placeholder="Leave blank to keep">
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Role & Access</h2></div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Role</label>
          <select name="role" id="roleSelect" onchange="toggleModules()">
            <?php foreach (ROLES as $k => $v):
              if ($k === 'superadmin' && !is_superadmin()) continue; ?>
            <option value="<?= $k ?>" <?= ($_POST['role'] ?? $u['role'])===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" id="officeField">
          <label class="field-label">Petty Cash Office <span id="officeReq" style="color:#FF3D33;display:none">*</span></label>
          <select name="office" id="officeSelect">
            <option value="">— Not assigned —</option>
            <?php foreach (OFFICES as $k => $o): ?>
            <option value="<?= $k ?>" <?= ($_POST['office'] ?? $u['office']??'')===$k?'selected':'' ?>><?= $o['flag'] ?> <?= $o['label'] ?></option>
            <?php endforeach; ?>
          </select>
          <div style="font-size:11px;color:#aaa;margin-top:4px">Required if user has Petty Cash access</div>
        </div>
      </div>

      <div id="modulesSection" style="display:none;margin-top:4px">
        <label class="field-label" style="margin-bottom:8px;display:block">Module Access</label>
        <div class="modules-grid">
          <?php foreach ($MODULES as $mk => $ml): ?>
          <div class="mod-item">
            <input type="checkbox" name="modules[]" value="<?= $mk ?>" id="mod_<?= $mk ?>"
              onchange="<?= $mk==='petty_cash' ? 'checkOfficeReq()' : '' ?>"
              <?= in_array($mk, $sel_modules) ? 'checked' : '' ?>>
            <label for="mod_<?= $mk ?>"><?= $ml ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="form-footer">
    <button type="submit" class="submit-btn">Save Changes</button>
    <a href="index.php" style="margin-left:14px;font-size:13px;color:#aaa;text-decoration:none">Cancel</a>
  </div>
  </form>
</div>
</div>
</div>
<script>
function toggleModules(){
  const isUser = document.getElementById('roleSelect').value === 'user';
  document.getElementById('modulesSection').style.display = isUser ? 'block' : 'none';
  checkOfficeReq();
}
function checkOfficeReq(){
  const isUser = document.getElementById('roleSelect').value === 'user';
  const pcChecked = document.getElementById('mod_petty_cash')?.checked;
  const req = isUser && pcChecked;
  document.getElementById('officeReq').style.display = req ? 'inline' : 'none';
  document.getElementById('officeSelect').required = req;
}
toggleModules();
</script>
</body>
</html>
