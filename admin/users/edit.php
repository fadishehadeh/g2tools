<?php
session_start();
require '../../config.php';
require_login();
if (!is_finance_admin()) { header('Location: /'); exit; }

$id = (int)($_GET['id'] ?? 0);
$u  = db()->prepare("SELECT * FROM users WHERE id=?");
$u->execute([$id]); $u = $u->fetch();
if (!$u) { header('Location: index.php'); exit; }

// Only superadmin can edit superadmins
if ($u['role'] === 'superadmin' && !is_superadmin()) { header('Location: index.php'); exit; }

$error = '';
$PERM_GROUPS = [
    'Finance Forms' => [
        'finance_cc'             => '💳 Credit Card Auth',
        'finance_accountability' => '📦 Accountability',
        'finance_debit_note'     => '📄 Debit Note',
        'finance_credit_note'    => '📋 Credit Note',
        'finance_vendor_recon'   => '📊 Vendor Recon',
    ],
    'Office — Petty Cash' => [
        'petty_cash_doha'   => '🇶🇦 Doha (QAR)',
        'petty_cash_beirut' => '🇱🇧 Beirut (USD)',
    ],
    'Other' => [
        'vendor' => '🏢 Vendor Registration',
        'assets' => '🖥️ Asset Management',
    ],
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
        $valid_keys = array_keys(array_merge(...array_values($PERM_GROUPS)));
        $clean_mods = array_values(array_filter($modules, fn($m) => in_array($m, $valid_keys)));
        $mods = ($role === 'user') ? json_encode($clean_mods) : null;
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
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
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
        <label class="field-label" style="margin-bottom:8px;display:block">Feature Access</label>
        <?php foreach ($PERM_GROUPS as $grpLabel => $grpPerms): ?>
        <div style="margin-bottom:14px">
          <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#aaa;margin-bottom:6px;display:flex;align-items:center;gap:8px">
            <?= $grpLabel ?>
            <span onclick="toggleGroup(this)" style="cursor:pointer;font-size:11px;color:#FF3D33;font-weight:600;text-transform:none;letter-spacing:0">All</span>
          </div>
          <div class="modules-grid">
            <?php foreach ($grpPerms as $mk => $ml): ?>
            <div class="mod-item">
              <input type="checkbox" name="modules[]" value="<?= $mk ?>" id="mod_<?= $mk ?>"
                onchange="checkOfficeReq()"
                <?= in_array($mk, $sel_modules) ? 'checked' : '' ?>>
              <label for="mod_<?= $mk ?>"><?= $ml ?></label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
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
  const doha   = document.getElementById('mod_petty_cash_doha')?.checked;
  const beirut = document.getElementById('mod_petty_cash_beirut')?.checked;
  const req    = isUser && (doha || beirut);
  const oneOnly = (doha && !beirut) || (!doha && beirut);
  document.getElementById('officeReq').style.display = req && oneOnly ? 'inline' : 'none';
  document.getElementById('officeSelect').required = req && oneOnly;
  if (doha && !beirut)  document.getElementById('officeSelect').value = 'doha';
  if (beirut && !doha)  document.getElementById('officeSelect').value = 'beirut';
  if (doha && beirut)   document.getElementById('officeSelect').value = '';
}
function toggleGroup(span){
  const grid = span.closest('div').nextElementSibling;
  const cbs  = grid.querySelectorAll('input[type=checkbox]');
  const allChecked = [...cbs].every(c => c.checked);
  cbs.forEach(c => c.checked = !allChecked);
  checkOfficeReq();
}
toggleModules();
</script>
</body>
</html>
