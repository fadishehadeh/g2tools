<?php
session_start();
require '../config.php';
require_login();
if (!is_it_admin()) { header('Location: /'); exit; }

$user  = current_user();
$error = $success = '';

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $role  = $_POST['role'] ?? 'user';
    if ($name && $email && $pass && in_array($role, ['user','finance_admin','it_admin'])) {
        try {
            $db = db();
            $stmt = $db->prepare("INSERT INTO users (name,email,password_hash,role,status) VALUES (?,?,?,?,'active')");
            $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
            $success = "User <b>" . htmlspecialchars($name) . "</b> created.";
        } catch (PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Email already exists.' : 'Error: ' . $e->getMessage();
        }
    } else { $error = 'All fields are required.'; }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = (int)($_POST['del_id'] ?? 0);
    if ($del_id && $del_id !== (int)$user['id']) {
        db()->prepare("DELETE FROM users WHERE id = ?")->execute([$del_id]);
        $success = 'User deleted.';
    }
}

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'role') {
    $edit_id    = (int)($_POST['edit_id'] ?? 0);
    $new_role   = $_POST['new_role'] ?? '';
    $new_office = $_POST['new_office'] ?? '';
    if (!in_array($new_office, ['doha','beirut',''])) $new_office = '';
    if ($edit_id && in_array($new_role, ['user','finance_admin','it_admin'])) {
        db()->prepare("UPDATE users SET role=?, office=? WHERE id=?")->execute([$new_role, $new_office, $edit_id]);
        $success = 'Role and office updated.';
    }
}

// Handle approve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve') {
    $aid = (int)($_POST['target_id'] ?? 0);
    if ($aid) {
        db()->prepare("UPDATE users SET status='active' WHERE id = ?")->execute([$aid]);
        $success = 'User approved and can now sign in.';
    }
}

// Handle reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject') {
    $rid = (int)($_POST['target_id'] ?? 0);
    if ($rid) {
        db()->prepare("UPDATE users SET status='rejected' WHERE id = ?")->execute([$rid]);
        $success = 'Registration rejected.';
    }
}

$pending_list = db()->query("SELECT id, name, email, created_at FROM users WHERE status='pending' ORDER BY created_at ASC")->fetchAll();
$users_list   = db()->query("SELECT id, name, email, role, office, status, created_at FROM users WHERE status != 'pending' ORDER BY created_at DESC")->fetchAll();
$role_labels  = ['user' => 'User', 'finance_admin' => 'Finance Admin', 'it_admin' => 'IT Admin'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users — G2 Admin</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
  *, *::before, *::after { box-sizing: border-box; }

  .page-wrap { padding: 36px 40px 60px; }
  .page-header { margin-bottom: 28px; }
  .page-header h1 { font-size: 24px; font-weight: 800; color: #1a1a1a; }

  .two-col { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; align-items: start; }

  .panel { background: #fff; border-radius: 14px; border: 1px solid #e8eaee;
           box-shadow: 0 2px 10px rgba(0,0,0,.05); padding: 28px 28px; }
  .panel h2 { font-size: 15px; font-weight: 700; color: #1a1a1a; margin-bottom: 20px; }

  .field { margin-bottom: 16px; }
  label { display: block; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase;
          letter-spacing: 0.4px; margin-bottom: 5px; }
  input[type=text], input[type=email], input[type=password], select {
    width: 100%; padding: 9px 12px; border: 1.5px solid #dde1e7; border-radius: 7px;
    font-size: 13px; font-family: inherit; outline: none; transition: border-color .2s; }
  input:focus, select:focus { border-color: #FF3D33; }
  button[type=submit] { width: 100%; padding: 10px; background: #FF3D33; color: #fff; border: none;
                        border-radius: 7px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 4px; }
  button[type=submit]:hover { background: #e8302a; }

  .msg { padding: 10px 14px; border-radius: 7px; font-size: 13px; margin-bottom: 16px; }
  .msg-ok  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
  .msg-err { background: #fff5f5; border: 1px solid #fca5a5; color: #dc2626; }

  table { width: 100%; border-collapse: collapse; }
  thead tr { background: #f8f9fb; }
  th { padding: 11px 14px; text-align: left; font-size: 11px; font-weight: 700; color: #999;
       text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eee; }
  td { padding: 12px 14px; font-size: 13px; color: #333; border-bottom: 1px solid #f3f3f3; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }

  .role-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .role-select { padding: 4px 8px; border: 1px solid #dde1e7; border-radius: 6px; font-size: 12px; font-family: inherit; }
  .save-role { padding: 4px 10px; background: #1a1a1a; color: #fff; border: none; border-radius: 6px;
               font-size: 11px; font-weight: 600; cursor: pointer; }
  .del-btn { padding: 4px 10px; background: #fff; color: #dc2626; border: 1px solid #fca5a5;
             border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; }
  .del-btn:hover { background: #fff5f5; }

  /* Pending approvals */
  .pending-card { background: #fffbeb; border: 1.5px solid #fcd34d; border-radius: 14px;
                  margin-bottom: 28px; overflow: hidden; }
  .pending-head { padding: 16px 22px; display: flex; align-items: center; gap: 12px;
                  border-bottom: 1px solid #fcd34d; }
  .pending-head h2 { font-size: 14px; font-weight: 700; color: #92400e; }
  .pending-count { background: #FF3D33; color: #fff; font-size: 11px; font-weight: 700;
                   padding: 2px 8px; border-radius: 20px; }
  .pending-card table td { background: transparent; }
  .pending-card thead tr { background: rgba(252,211,77,.2); }
  .pending-card th { color: #a16207; border-bottom-color: #fcd34d; }
  .pending-card tr:last-child td { border-bottom: none; }
  .pending-card td { border-bottom-color: rgba(252,211,77,.4); }
  .approve-btn { padding: 5px 14px; background: #16a34a; color: #fff; border: none;
                 border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; }
  .approve-btn:hover { background: #15803d; }
  .reject-btn  { padding: 5px 12px; background: #fff; color: #dc2626; border: 1px solid #fca5a5;
                 border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
  .reject-btn:hover { background: #fff5f5; }
  .status-badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .s-active   { background: #dcfce7; color: #166534; }
  .s-rejected { background: #fee2e2; color: #991b1b; }
  .del-btn:hover { background: #fff5f5; }
  .you-chip { font-size: 10px; background: #f3f4f6; color: #888; padding: 2px 7px; border-radius: 10px; margin-left: 6px; }

</style>
</head>
<body>

<?php require '../_sidebar.php'; ?>

<div class="main-content">
<div class="topbar"><span class="topbar-title">Manage Users</span></div>
<div class="page-wrap">
  <div class="page-header"><h1>Manage Users</h1></div>

  <?php if ($success): ?><div class="msg msg-ok"><?= $success ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="msg msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- ── Pending Approvals ── -->
  <?php if ($pending_list): ?>
  <div class="pending-card">
    <div class="pending-head">
      <h2>Pending Registrations</h2>
      <span class="pending-count"><?= count($pending_list) ?></span>
    </div>
    <table>
      <thead>
        <tr>
          <th>Name</th><th>Email</th><th>Requested</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pending_list as $p): ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($p['name']) ?></td>
          <td style="color:#888"><?= htmlspecialchars($p['email']) ?></td>
          <td style="color:#a16207;font-size:12px"><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:8px">
              <form method="POST">
                <input type="hidden" name="action"    value="approve">
                <input type="hidden" name="target_id" value="<?= $p['id'] ?>">
                <button class="approve-btn" type="submit">✓ Approve</button>
              </form>
              <form method="POST" onsubmit="return confirm('Reject this registration?')">
                <input type="hidden" name="action"    value="reject">
                <input type="hidden" name="target_id" value="<?= $p['id'] ?>">
                <button class="reject-btn" type="submit">✕ Reject</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="two-col">
    <!-- Create user -->
    <div class="panel">
      <h2>Add New User</h2>
      <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="field"><label>Full Name</label><input type="text" name="name" placeholder="Jane Doe" required></div>
        <div class="field"><label>Email</label><input type="email" name="email" placeholder="jane@g2group.com" required></div>
        <div class="field"><label>Password</label><input type="password" name="password" placeholder="Temp password" required></div>
        <div class="field">
          <label>Role</label>
          <select name="role">
            <option value="user">User</option>
            <option value="finance_admin">Finance Admin</option>
            <option value="it_admin">IT Admin</option>
          </select>
        </div>
        <button type="submit">Create User</button>
      </form>
    </div>

    <!-- Users table -->
    <div class="panel" style="padding:0;overflow:hidden">
      <table>
        <thead>
          <tr><th>Name</th><th>Email</th><th>Status</th><th>Role</th><th>Office</th><th>Since</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($users_list as $u):
            $is_me = (int)$u['id'] === (int)$user['id'];
          ?>
          <tr>
            <td>
              <?= htmlspecialchars($u['name']) ?>
              <?php if ($is_me): ?><span class="you-chip">you</span><?php endif; ?>
            </td>
            <td style="color:#888"><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <?php if (($u['status'] ?? 'active') === 'rejected'): ?>
                <span class="status-badge s-rejected">Rejected</span>
              <?php else: ?>
                <span class="status-badge s-active">Active</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!$is_me): ?>
              <form method="POST" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="edit_id" value="<?= $u['id'] ?>">
                <select class="role-select" name="new_role">
                  <option value="user"          <?= $u['role']==='user'         ?'selected':'' ?>>User</option>
                  <option value="finance_admin" <?= $u['role']==='finance_admin'?'selected':'' ?>>Finance Admin</option>
                  <option value="it_admin"      <?= $u['role']==='it_admin'     ?'selected':'' ?>>IT Admin</option>
                </select>
                <select class="role-select" name="new_office" title="Office">
                  <option value=""       <?= ($u['office']??'')==''      ?'selected':'' ?>>No office</option>
                  <option value="doha"   <?= ($u['office']??'')==='doha'  ?'selected':'' ?>>🇶🇦 Doha</option>
                  <option value="beirut" <?= ($u['office']??'')==='beirut'?'selected':'' ?>>🇱🇧 Beirut</option>
                </select>
                <button class="save-role" type="submit">Save</button>
              </form>
              <?php else: ?>
                <span class="role-badge" style="background:#f3f4f6;color:#555">
                  <?= $role_labels[$u['role']] ?>
                </span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['office'] === 'doha'): ?>
                <span style="font-size:12px;font-weight:600;color:#0891b2">🇶🇦 Doha</span>
              <?php elseif ($u['office'] === 'beirut'): ?>
                <span style="font-size:12px;font-weight:600;color:#7c3aed">🇱🇧 Beirut</span>
              <?php else: ?>
                <span style="font-size:11px;color:#ccc">—</span>
              <?php endif; ?>
            </td>
            <td style="color:#bbb;font-size:12px"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
              <?php if (!$is_me): ?>
              <form method="POST" onsubmit="return confirm('Delete this user?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="del_id" value="<?= $u['id'] ?>">
                <button class="del-btn" type="submit">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div><!-- .main-content -->
</body>
</html>
