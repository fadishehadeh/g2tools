<?php
require_once __DIR__ . '/../lib/bootstrap.php';
sm_require_admin();
$staff = sm_current_staff();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'grant') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $sm_role = $_POST['sm_role'] ?? 'manager';
        if ($user_id && in_array($sm_role, ['admin','manager'], true)) {
            sm_db()->prepare(
                "INSERT INTO sm_user_access (user_id, sm_role, granted_by) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE sm_role = VALUES(sm_role)"
            )->execute([$user_id, $sm_role, $staff['id']]);
            $msg = 'ok|Access granted/updated.';
        }
    } elseif ($action === 'revoke') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id && $user_id !== (int)$staff['id']) {
            sm_db()->prepare("DELETE FROM sm_user_access WHERE user_id=?")->execute([$user_id]);
            $msg = 'ok|Access revoked.';
        } else {
            $msg = 'err|You cannot revoke your own access.';
        }
    }
}
[$mt,$mm] = $msg ? explode('|',$msg,2) : ['',''];

// All G2 Tools staff (it_admin/finance_admin/user), with their current SM access if any
$rows = sm_db()->query(
    "SELECT u.id, u.name, u.email, u.role as g2_role, a.sm_role, a.granted_at
     FROM " . g2_users_table() . " u
     LEFT JOIN sm_user_access a ON a.user_id = u.id
     WHERE u.status='active'
     ORDER BY (a.sm_role IS NOT NULL) DESC, u.name ASC"
)->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Access Control — G2 SM Calendar Tool</title>
<link rel="stylesheet" href="/g2forms/sm-calendar/sm.css">
<style>
  .ac-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e8eaee; border-radius:14px; overflow:hidden; }
  .ac-table th { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#aaa;
                 padding:12px 14px; text-align:left; border-bottom:1.5px solid #eef0f3; }
  .ac-table td { padding:11px 14px; font-size:13px; border-bottom:1px solid #f5f6f8; vertical-align:middle; }
  .ac-table tr:last-child td { border-bottom:none; }
  .g2-role-chip { font-size:10.5px; background:#f5f6f8; padding:2px 8px; border-radius:6px; color:#777; }
  .sm-role-chip { font-size:10.5px; font-weight:700; padding:2px 9px; border-radius:20px; }
  .sm-role-admin { background:#fff3f2; color:#FF3D33; }
  .sm-role-manager { background:#eff6ff; color:#2563eb; }
  .sm-role-none { background:#f5f6f8; color:#bbb; }
  select.inline { padding:5px 8px; border:1.5px solid #e8eaee; border-radius:6px; font-size:12px; }
  .btn-grant { padding:5px 12px; background:#1a1a1a; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; }
  .btn-revoke { padding:5px 12px; background:#fff; color:#dc2626; border:1px solid #fca5a5; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; }
</style>
</head>
<body>
<div class="sm-shell">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="sm-main">
    <div class="sm-topbar"><h1>Access Control</h1></div>

    <?php if ($mm): ?>
    <div class="sm-msg <?= $mt==='ok'?'sm-msg-ok':'sm-msg-err' ?>" style="max-width:600px"><?= htmlspecialchars($mm) ?></div>
    <?php endif; ?>

    <p style="font-size:13px;color:#999;margin-bottom:20px;max-width:600px">
      Grant G2 SM Calendar Tool access to existing G2 Tools staff. They'll sign in with their existing email and password — no new account needed.
    </p>

    <table class="ac-table">
      <thead><tr><th>Name</th><th>Email</th><th>G2 Tools Role</th><th>SM Calendar Access</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($r['name']) ?></td>
        <td style="color:#888"><?= htmlspecialchars($r['email']) ?></td>
        <td><span class="g2-role-chip"><?= htmlspecialchars($r['g2_role']) ?></span></td>
        <td>
          <?php if ($r['sm_role']): ?>
            <span class="sm-role-chip sm-role-<?= $r['sm_role'] ?>"><?= ucfirst($r['sm_role']) ?></span>
          <?php else: ?>
            <span class="sm-role-chip sm-role-none">No access</span>
          <?php endif; ?>
        </td>
        <td>
          <form method="POST" style="display:flex;gap:6px;align-items:center">
            <input type="hidden" name="user_id" value="<?= $r['id'] ?>">
            <select name="sm_role" class="inline">
              <option value="manager" <?= $r['sm_role']==='manager'?'selected':'' ?>>Manager</option>
              <option value="admin"   <?= $r['sm_role']==='admin'?'selected':'' ?>>Admin</option>
            </select>
            <button type="submit" name="action" value="grant" class="btn-grant"><?= $r['sm_role'] ? 'Update' : 'Grant' ?></button>
            <?php if ($r['sm_role']): ?>
            <button type="submit" name="action" value="revoke" class="btn-revoke" onclick="return confirm('Revoke SM Calendar access for <?= htmlspecialchars(addslashes($r['name'])) ?>?')">Revoke</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </main>
</div>
</body>
</html>
