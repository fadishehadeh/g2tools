<?php
require_once __DIR__ . '/../lib/bootstrap.php';
sm_require_staff();
$staff = sm_current_staff();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim(strip_tags($_POST['name'] ?? ''));
        $client_id = (int)($_POST['client_id'] ?? 0);
        $month = trim($_POST['month'] ?? '');
        $owner_id = (int)($_POST['owner_id'] ?? 0) ?: null;

        if (!$name || !$client_id || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $msg = 'err|Name, client, and month are required.';
        } else {
            sm_db()->prepare(
                "INSERT INTO calendars (client_id,name,month,owner_id) VALUES (?,?,?,?)"
            )->execute([$client_id, $name, $month, $owner_id]);
            $msg = 'ok|Calendar created.';
        }
    } elseif ($action === 'archive') {
        $id = (int)($_POST['id'] ?? 0);
        sm_db()->prepare("UPDATE calendars SET archived = 1 - archived WHERE id=?")->execute([$id]);
        $msg = 'ok|Calendar updated.';
    } elseif ($action === 'duplicate') {
        $id = (int)($_POST['id'] ?? 0);
        $with_posts = !empty($_POST['with_posts']);

        $stmt = sm_db()->prepare("SELECT * FROM calendars WHERE id=?");
        $stmt->execute([$id]);
        $src = $stmt->fetch();

        if ($src) {
            sm_db()->prepare("INSERT INTO calendars (client_id,name,month,owner_id) VALUES (?,?,?,?)")
                ->execute([$src['client_id'], $src['name'] . ' (Copy)', $src['month'], $src['owner_id']]);
            $new_id = sm_db()->lastInsertId();

            if ($with_posts) {
                $posts = sm_db()->prepare("SELECT * FROM posts WHERE calendar_id=? AND archived=0");
                $posts->execute([$id]);
                $ins = sm_db()->prepare(
                    "INSERT INTO posts (calendar_id,client_id,title,caption,notes,hashtags,platform,format,technical_specs,status,scheduled_at,owner_id)
                     VALUES (?,?,?,?,?,?,?,?,?,'Draft',?,?)"
                );
                foreach ($posts->fetchAll() as $p) {
                    $ins->execute([$new_id,$src['client_id'],$p['title'],$p['caption'],$p['notes'],$p['hashtags'],$p['platform'],$p['format'],$p['technical_specs'],$p['scheduled_at'],$p['owner_id']]);
                }
            }
            $msg = 'ok|Calendar duplicated' . ($with_posts ? ' with posts.' : ' (shell only).');
        }
    }
}
[$mt,$mm] = $msg ? explode('|',$msg,2) : ['',''];

$client_filter = (int)($_GET['client_id'] ?? 0);
$show_archived = isset($_GET['archived']);

$sql = "SELECT cal.*, c.name AS client_name,
          (SELECT COUNT(*) FROM posts WHERE calendar_id=cal.id AND archived=0) AS post_count
        FROM calendars cal JOIN clients c ON c.id = cal.client_id
        WHERE cal.archived = " . ($show_archived ? '1' : '0');
$params = [];
if ($client_filter) { $sql .= " AND cal.client_id=?"; $params[] = $client_filter; }
$sql .= " ORDER BY cal.month DESC, cal.name ASC";
$stmt = sm_db()->prepare($sql); $stmt->execute($params);
$calendars = $stmt->fetchAll();

$clients = sm_db()->query("SELECT id,name FROM clients WHERE archived=0 ORDER BY name")->fetchAll();
$owners  = sm_db()->query("SELECT id,name FROM " . g2_users_table() . " WHERE status='active' ORDER BY name")->fetchAll();
$owner_names = array_column($owners, 'name', 'id');

$status_colors = ['Draft'=>'#9ca3af','Brief Sent'=>'#2563eb','Artwork Pending'=>'#f59e0b','In Review'=>'#8b5cf6','Approved'=>'#16a34a','Rejected'=>'#dc2626','Published'=>'#0891b2'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Calendars — G2 SM Calendar Tool</title>
<link rel="stylesheet" href="/sm-calendar/sm.css">
<style>
  .panel { background:#fff; border:1px solid #e8eaee; border-radius:14px; padding:22px; margin-bottom:24px; }
  .panel h2 { font-size:13px; font-weight:800; margin:0 0 16px; }
  .fg { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; }
  .fg .sm-field { margin-bottom:0; }

  .tab-row { display:flex; gap:6px; margin-bottom:18px; align-items:center; justify-content:space-between; }
  .tab-link { padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600;
              border:1.5px solid #e8eaee; background:#fff; color:#888; text-decoration:none; }
  .tab-link.active { border-color:#FF3D33; color:#FF3D33; background:#fff8f8; }
  .filter-chip { font-size:12px; color:#888; background:#f5f6f8; padding:5px 12px; border-radius:20px; display:flex; align-items:center; gap:6px; }
  .filter-chip a { color:#FF3D33; text-decoration:none; font-weight:700; }

  .cal-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:14px; }
  .cal-card { background:#fff; border:1.5px solid #e8eaee; border-radius:14px; padding:20px; }
  .cal-card:hover { border-color:#ccc; }
  .cal-top { display:flex; align-items:start; justify-content:space-between; margin-bottom:6px; }
  .cal-name { font-size:14.5px; font-weight:700; color:#1a1a1a; }
  .cal-month { font-size:11px; font-weight:700; background:#fff3f2; color:#FF3D33; padding:3px 10px; border-radius:20px; }
  .cal-client { font-size:12px; color:#999; margin-bottom:14px; }
  .cal-meta { font-size:11.5px; color:#aaa; margin-bottom:14px; }
  .cal-meta strong { color:#555; }
  .cal-actions { display:flex; gap:6px; flex-wrap:wrap; }
  .cal-btn { padding:7px 12px; border:1.5px solid #e8eaee; background:#fff; border-radius:8px;
             font-size:11.5px; font-weight:600; cursor:pointer; color:#555; text-decoration:none; }
  .cal-btn:hover { border-color:#FF3D33; color:#FF3D33; }
  .cal-btn.primary { background:#FF3D33; color:#fff; border-color:#FF3D33; }
  .cal-btn.primary:hover { background:#c0170e; color:#fff; }
</style>
</head>
<body>
<div class="sm-shell">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="sm-main">
    <div class="sm-topbar">
      <h1>Calendars</h1>
      <div class="sm-user-chip">
        <span class="sm-role-badge"><?= htmlspecialchars($staff['sm_role']) ?></span>
        <strong><?= htmlspecialchars($staff['name']) ?></strong>
      </div>
    </div>

    <?php if ($mm): ?>
    <div class="sm-msg <?= $mt==='ok'?'sm-msg-ok':'sm-msg-err' ?>" style="max-width:600px"><?= htmlspecialchars($mm) ?></div>
    <?php endif; ?>

    <div class="panel">
      <h2>New Calendar</h2>
      <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="fg">
          <div class="sm-field"><label>Name</label><input type="text" name="name" placeholder="e.g. July Content Plan" required></div>
          <div class="sm-field">
            <label>Client</label>
            <select name="client_id" required>
              <option value="">Select client…</option>
              <?php foreach ($clients as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $client_filter===(int)$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="sm-field"><label>Month</label><input type="month" name="month" value="<?= date('Y-m') ?>" required></div>
          <div class="sm-field">
            <label>Owner</label>
            <select name="owner_id">
              <option value="">— Unassigned —</option>
              <?php foreach ($owners as $o): ?>
              <option value="<?= $o['id'] ?>" <?= (int)$staff['id']===(int)$o['id']?'selected':'' ?>><?= htmlspecialchars($o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button type="submit" class="sm-btn-primary" style="margin-top:16px;width:auto;padding:11px 24px">＋ Create Calendar</button>
      </form>
    </div>

    <div class="tab-row">
      <div style="display:flex;gap:6px">
        <a class="tab-link <?= !$show_archived?'active':'' ?>" href="?<?= $client_filter?'client_id='.$client_filter:'' ?>">Active</a>
        <a class="tab-link <?= $show_archived?'active':'' ?>" href="?archived=1<?= $client_filter?'&client_id='.$client_filter:'' ?>">Archived</a>
      </div>
      <?php if ($client_filter):
        $cname = '';
        foreach ($clients as $c) if ((int)$c['id']===$client_filter) $cname = $c['name'];
      ?>
      <div class="filter-chip">Filtered: <?= htmlspecialchars($cname) ?> <a href="?">✕</a></div>
      <?php endif; ?>
    </div>

    <?php if (empty($calendars)): ?>
    <p style="color:#bbb;font-size:13px">No <?= $show_archived?'archived':'active' ?> calendars<?= $client_filter?' for this client':'' ?>.</p>
    <?php else: ?>
    <div class="cal-grid">
      <?php foreach ($calendars as $cal): ?>
      <div class="cal-card">
        <div class="cal-top">
          <div class="cal-name"><?= htmlspecialchars($cal['name']) ?></div>
          <div class="cal-month"><?= date('M Y', strtotime($cal['month'] . '-01')) ?></div>
        </div>
        <div class="cal-client"><?= htmlspecialchars($cal['client_name']) ?></div>
        <div class="cal-meta">
          <strong><?= $cal['post_count'] ?></strong> posts &middot;
          Owner: <strong><?= htmlspecialchars($cal['owner_id'] ? ($owner_names[$cal['owner_id']] ?? '—') : '—') ?></strong>
        </div>
        <div class="cal-actions">
          <a class="cal-btn primary" href="<?= SM_BASE_URL ?>/posts/?calendar_id=<?= $cal['id'] ?>">Open</a>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="duplicate">
            <input type="hidden" name="id" value="<?= $cal['id'] ?>">
            <button type="submit" class="cal-btn" title="Duplicate shell only">Duplicate</button>
          </form>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="duplicate">
            <input type="hidden" name="id" value="<?= $cal['id'] ?>">
            <input type="hidden" name="with_posts" value="1">
            <button type="submit" class="cal-btn" title="Duplicate with posts">Dup. +Posts</button>
          </form>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="id" value="<?= $cal['id'] ?>">
            <button type="submit" class="cal-btn"><?= $show_archived?'Restore':'Archive' ?></button>
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
