<?php
require_once __DIR__ . '/../lib/bootstrap.php';
sm_require_staff();
$staff = sm_current_staff();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim(strip_tags($_POST['name'] ?? ''));
        $email = trim(strip_tags($_POST['email'] ?? ''));
        $manager_id = (int)($_POST['account_manager_id'] ?? 0) ?: null;
        $platforms = trim(strip_tags($_POST['connected_platforms'] ?? ''));

        if (!$name) {
            $msg = 'err|Client name is required.';
        } else {
            $logo_name = null;
            if (!empty($_FILES['logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['png','jpg','jpeg','gif','svg'], true) && $_FILES['logo']['size'] <= 3*1024*1024) {
                    $fname = 'logo_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], SM_LOGO_PATH . $fname)) $logo_name = $fname;
                }
            }
            sm_db()->prepare(
                "INSERT INTO clients (name,email,logo,account_manager_id,connected_platforms) VALUES (?,?,?,?,?)"
            )->execute([$name, $email ?: null, $logo_name, $manager_id, $platforms ?: null]);
            $msg = 'ok|Client created.';
        }
    } elseif ($action === 'archive') {
        $id = (int)($_POST['id'] ?? 0);
        sm_db()->prepare("UPDATE clients SET archived = 1 - archived WHERE id=?")->execute([$id]);
        $msg = 'ok|Client updated.';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (sm_is_admin()) {
            sm_db()->prepare("DELETE FROM clients WHERE id=?")->execute([$id]);
            $msg = 'ok|Client deleted.';
        }
    }
}
[$mt,$mm] = $msg ? explode('|',$msg,2) : ['',''];

$show_archived = isset($_GET['archived']);
$stmt = sm_db()->query(
    "SELECT c.*,
       (SELECT COUNT(*) FROM calendars WHERE client_id=c.id) AS calendar_count,
       (SELECT COUNT(*) FROM posts WHERE client_id=c.id) AS post_count
     FROM clients c
     WHERE c.archived = " . ($show_archived ? '1' : '0') . "
     ORDER BY c.name ASC"
);
$clients = $stmt->fetchAll();

$managers = sm_db()->query("SELECT id,name FROM " . g2_users_table() . " WHERE status='active' ORDER BY name")->fetchAll();
$manager_names = array_column($managers, 'name', 'id');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clients — G2 SM Calendar Tool</title>
<link rel="stylesheet" href="/g2forms/sm-calendar/sm.css">
<style>
  .cl-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px; margin-top:20px; }
  .cl-card { background:#fff; border:1.5px solid #e8eaee; border-radius:14px; padding:20px; transition:border-color .15s; }
  .cl-card:hover { border-color:#ccc; }
  .cl-card-top { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
  .cl-logo { width:44px; height:44px; border-radius:10px; background:#f6f7f9; border:1px solid #e8eaee;
             display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; }
  .cl-logo img { width:100%; height:100%; object-fit:contain; }
  .cl-name { font-size:14px; font-weight:700; color:#1a1a1a; }
  .cl-email { font-size:11.5px; color:#aaa; }
  .cl-meta { display:flex; gap:14px; font-size:11.5px; color:#999; margin-bottom:14px; }
  .cl-meta strong { color:#1a1a1a; }
  .cl-manager { font-size:11.5px; color:#888; margin-bottom:14px; }
  .cl-actions { display:flex; gap:8px; }
  .cl-btn { flex:1; padding:7px 0; border:1.5px solid #e8eaee; background:#fff; border-radius:8px;
            font-size:11.5px; font-weight:600; cursor:pointer; color:#555; text-align:center; text-decoration:none; }
  .cl-btn:hover { border-color:#FF3D33; color:#FF3D33; }

  .panel { background:#fff; border:1px solid #e8eaee; border-radius:14px; padding:22px; margin-bottom:24px; }
  .panel h2 { font-size:13px; font-weight:800; margin:0 0 16px; }
  .fg { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; align-items:end; }
  .fg .sm-field { margin-bottom:0; }

  .tab-row { display:flex; gap:6px; margin-bottom:6px; }
  .tab-link { padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600;
              border:1.5px solid #e8eaee; background:#fff; color:#888; text-decoration:none; }
  .tab-link.active { border-color:#FF3D33; color:#FF3D33; background:#fff8f8; }
</style>
</head>
<body>
<div class="sm-shell">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="sm-main">
    <div class="sm-topbar">
      <h1>Clients</h1>
      <div class="sm-user-chip">
        <span class="sm-role-badge"><?= htmlspecialchars($staff['sm_role']) ?></span>
        <strong><?= htmlspecialchars($staff['name']) ?></strong>
      </div>
    </div>

    <?php if ($mm): ?>
    <div class="sm-msg <?= $mt==='ok'?'sm-msg-ok':'sm-msg-err' ?>" style="max-width:600px"><?= htmlspecialchars($mm) ?></div>
    <?php endif; ?>

    <div class="panel">
      <h2>Add New Client</h2>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create">
        <div class="fg">
          <div class="sm-field"><label>Client Name</label><input type="text" name="name" placeholder="e.g. Acme Corp" required></div>
          <div class="sm-field"><label>Email</label><input type="email" name="email" placeholder="contact@acme.com"></div>
          <div class="sm-field">
            <label>Account Manager</label>
            <select name="account_manager_id">
              <option value="">— None —</option>
              <?php foreach ($managers as $m): ?>
              <option value="<?= $m['id'] ?>" <?= (int)$staff['id']===(int)$m['id']?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="sm-field"><label>Connected Platforms</label><input type="text" name="connected_platforms" placeholder="Facebook, Instagram"></div>
          <div class="sm-field"><label>Logo</label><input type="file" name="logo" accept="image/*"></div>
        </div>
        <button type="submit" class="sm-btn-primary" style="margin-top:16px;width:auto;padding:11px 24px">＋ Add Client</button>
      </form>
    </div>

    <div class="tab-row">
      <a class="tab-link <?= !$show_archived?'active':'' ?>" href="?">Active</a>
      <a class="tab-link <?= $show_archived?'active':'' ?>" href="?archived=1">Archived</a>
    </div>

    <?php if (empty($clients)): ?>
    <p style="color:#bbb;font-size:13px;margin-top:20px">No <?= $show_archived?'archived':'active' ?> clients.</p>
    <?php else: ?>
    <div class="cl-grid">
      <?php foreach ($clients as $c): ?>
      <div class="cl-card">
        <div class="cl-card-top">
          <div class="cl-logo">
            <?php if ($c['logo']): ?>
            <img src="/g2forms/sm-calendar/storage/logos/<?= htmlspecialchars($c['logo']) ?>">
            <?php else: ?>
            <span style="font-size:16px;font-weight:800;color:#ccc"><?= strtoupper(substr($c['name'],0,1)) ?></span>
            <?php endif; ?>
          </div>
          <div>
            <div class="cl-name"><?= htmlspecialchars($c['name']) ?></div>
            <div class="cl-email"><?= htmlspecialchars($c['email'] ?: 'No email set') ?></div>
          </div>
        </div>
        <div class="cl-meta">
          <span><strong><?= $c['calendar_count'] ?></strong> calendars</span>
          <span><strong><?= $c['post_count'] ?></strong> posts</span>
        </div>
        <div class="cl-manager">
          Manager: <strong style="color:#555"><?= htmlspecialchars($c['account_manager_id'] ? ($manager_names[$c['account_manager_id']] ?? '—') : '—') ?></strong>
        </div>
        <div class="cl-actions">
          <a class="cl-btn" href="edit.php?id=<?= $c['id'] ?>">Edit</a>
          <a class="cl-btn" href="<?= SM_BASE_URL ?>/calendars/?client_id=<?= $c['id'] ?>">Calendars</a>
          <form method="POST" style="flex:1">
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button type="submit" class="cl-btn" style="width:100%"><?= $show_archived?'Restore':'Archive' ?></button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
