<?php
session_start();
require '../../config.php';
require_login();
if (!is_finance_admin()) { header('Location: /'); exit; }

$q    = trim($_GET['q'] ?? '');
$role = $_GET['role'] ?? '';

$sql    = "SELECT * FROM users WHERE 1=1";
$params = [];
if ($q)    { $sql .= " AND (name LIKE ? OR email LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($role) { $sql .= " AND role = ?"; $params[] = $role; }
$sql .= " ORDER BY is_active DESC, name ASC";
$stmt = db()->prepare($sql); $stmt->execute($params);
$users = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$role_colors = [
    'superadmin'    => ['#fef3c7','#92400e'],
    'finance_admin' => ['#dbeafe','#1e40af'],
    'it_admin'      => ['#f0fdf4','#166534'],
    'user'          => ['#f5f6f8','#666'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>User Management — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
*,*::before,*::after{box-sizing:border-box}
.pw{padding:30px 36px 60px;max-width:1100px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.btn-new{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:#FF3D33;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none}
.btn-new:hover{background:#c0170e}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center}
.filters input,.filters select{padding:8px 13px;border:1.5px solid #e0e2e8;border-radius:7px;font-size:13px;font-family:inherit;outline:none}
.filters input:focus,.filters select:focus{border-color:#FF3D33}
.filters button{padding:8px 16px;background:#1a1a1a;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;overflow:hidden}
table{width:100%;border-collapse:collapse;font-size:13px}
th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#aaa;padding:11px 16px;border-bottom:1.5px solid #eef0f3;text-align:left;white-space:nowrap;background:#fafbfc}
td{padding:12px 16px;border-bottom:1px solid #f5f6f8;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafbfc}
.avatar{width:32px;height:32px;border-radius:50%;background:#FF3D33;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0}
.name-cell{display:flex;align-items:center;gap:10px}
.name-cell .nm{font-weight:600;color:#1a1a1a}
.name-cell .em{font-size:11px;color:#aaa}
.role-badge{display:inline-block;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:700}
.inactive-row td{opacity:.45}
.action-btns{display:flex;gap:6px}
.btn-edit{padding:5px 13px;background:#f1f5f9;color:#444;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none}
.btn-edit:hover{background:#e2e8f0}
.btn-deactivate{padding:5px 13px;background:#fff0f0;color:#dc2626;border-radius:6px;font-size:12px;font-weight:600;border:none;cursor:pointer}
.btn-activate{padding:5px 13px;background:#f0fdf4;color:#16a34a;border-radius:6px;font-size:12px;font-weight:600;border:none;cursor:pointer}
.flash{padding:12px 16px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:18px}
.flash-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.flash-err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.count-chip{background:#f5f6f8;color:#888;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:6px}
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/admin/">← Admin</a>
  <span class="topbar-title">User Management</span>
</div>
<div class="pw">

  <?php if ($flash): ?>
  <div class="flash <?= $flash['type']==='ok'?'flash-ok':'flash-err' ?>"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="ph">
    <h1>Users <span class="count-chip"><?= count($users) ?></span></h1>
    <a class="btn-new" href="create.php">+ New User</a>
  </div>

  <form class="filters" method="GET">
    <input type="text" name="q" placeholder="Search name or email…" value="<?= htmlspecialchars($q) ?>">
    <select name="role">
      <option value="">All roles</option>
      <?php foreach (ROLES as $k => $v): ?>
      <option value="<?= $k ?>" <?= $role===$k?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Search</button>
    <?php if ($q||$role): ?><a href="index.php" style="font-size:13px;color:#aaa;text-decoration:none">Clear</a><?php endif; ?>
  </form>

  <div class="panel">
    <table>
      <thead><tr>
        <th>User</th><th>Role</th><th>Office</th><th>Access</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $u):
        $initials = implode('', array_map(fn($w)=>strtoupper($w[0]), array_slice(explode(' ', $u['name']), 0, 2)));
        [$rbg,$rfg] = $role_colors[$u['role']] ?? ['#f5f6f8','#666'];
        $modules = json_decode($u['access_modules'] ?? '[]', true) ?: [];
      ?>
      <tr class="<?= !$u['is_active']?'inactive-row':'' ?>">
        <td>
          <div class="name-cell">
            <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            <div>
              <div class="nm"><?= htmlspecialchars($u['name']) ?></div>
              <div class="em"><?= htmlspecialchars($u['email'] ?? '—') ?></div>
            </div>
          </div>
        </td>
        <td><span class="role-badge" style="background:<?= $rbg ?>;color:<?= $rfg ?>"><?= ROLES[$u['role']] ?? $u['role'] ?></span></td>
        <td style="color:#555"><?= $u['office'] ? htmlspecialchars(OFFICES[$u['office']]['label'] ?? $u['office']) : '<span style="color:#ddd">—</span>' ?></td>
        <td style="font-size:11px;color:#888;max-width:180px">
          <?php if (in_array($u['role'], ['superadmin','finance_admin','it_admin'])): ?>
            <span style="color:#aaa;font-style:italic">Full role access</span>
          <?php elseif ($modules): ?>
            <?= implode(', ', array_map('ucfirst', $modules)) ?>
          <?php else: ?>
            <span style="color:#e5e7eb">No access</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($u['is_active']): ?>
            <span style="color:#16a34a;font-size:11px;font-weight:700">● Active</span>
          <?php else: ?>
            <span style="color:#dc2626;font-size:11px;font-weight:700">● Inactive</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="action-btns">
            <a class="btn-edit" href="edit.php?id=<?= $u['id'] ?>">Edit</a>
            <?php if ($u['id'] != ($_SESSION['g2_user']['id'] ?? 0)): ?>
            <form method="POST" action="toggle.php" style="margin:0">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <?php if ($u['is_active']): ?>
                <button class="btn-deactivate" type="submit" onclick="return confirm('Deactivate this user?')">Deactivate</button>
              <?php else: ?>
                <button class="btn-activate" type="submit">Activate</button>
              <?php endif; ?>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?>
      <tr><td colspan="6" style="text-align:center;padding:40px;color:#ccc">No users found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>
</div>
</body>
</html>
