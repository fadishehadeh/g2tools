<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/workflow.php';
sm_require_staff();
$staff = sm_current_staff();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $calendar_id = (int)($_POST['calendar_id'] ?? 0);
        $title = trim(strip_tags($_POST['title'] ?? ''));
        if (!$calendar_id || !$title) {
            $msg = 'err|Calendar and title are required.';
        } else {
            $cal = sm_db()->prepare("SELECT client_id FROM calendars WHERE id=?");
            $cal->execute([$calendar_id]);
            $client_id = $cal->fetchColumn();
            if (!$client_id) {
                $msg = 'err|Invalid calendar.';
            } else {
                sm_db()->prepare(
                    "INSERT INTO posts (calendar_id,client_id,title,owner_id,status) VALUES (?,?,?,?,'Draft')"
                )->execute([$calendar_id, $client_id, $title, $staff['id']]);
                $new_id = sm_db()->lastInsertId();
                header('Location: ' . SM_BASE_URL . '/posts/detail.php?id=' . $new_id);
                exit;
            }
        }
    } elseif ($action === 'bulk_archive') {
        $ids = array_map('intval', $_POST['post_ids'] ?? []);
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            sm_db()->prepare("UPDATE posts SET archived=1 WHERE id IN ($in)")->execute($ids);
            $msg = 'ok|' . count($ids) . ' post(s) archived.';
        }
    }
}
[$mt,$mm] = $msg ? explode('|',$msg,2) : ['',''];

$calendar_id = (int)($_GET['calendar_id'] ?? 0);
$client_id   = (int)($_GET['client_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$view = $_GET['view'] ?? 'list';

$sql = "SELECT p.*, c.name AS client_name, cal.name AS calendar_name
        FROM posts p JOIN clients c ON c.id=p.client_id JOIN calendars cal ON cal.id=p.calendar_id
        WHERE p.archived=0";
$params = [];
if ($calendar_id) { $sql .= " AND p.calendar_id=?"; $params[] = $calendar_id; }
if ($client_id)   { $sql .= " AND p.client_id=?"; $params[] = $client_id; }
if ($status_filter && in_array($status_filter, SM_POST_STATUSES, true)) { $sql .= " AND p.status=?"; $params[] = $status_filter; }
$sql .= " ORDER BY p.scheduled_at IS NULL, p.scheduled_at ASC, p.created_at DESC";
$stmt = sm_db()->prepare($sql); $stmt->execute($params);
$posts = $stmt->fetchAll();

$calendars = sm_db()->query("SELECT cal.id,cal.name,c.name AS client_name FROM calendars cal JOIN clients c ON c.id=cal.client_id WHERE cal.archived=0 ORDER BY cal.month DESC")->fetchAll();
$current_calendar_name = '';
if ($calendar_id) { foreach ($calendars as $c) if ((int)$c['id']===$calendar_id) $current_calendar_name = $c['name']; }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Posts — G2 SM Calendar Tool</title>
<link rel="stylesheet" href="/sm-calendar/sm.css">
<style>
  .panel { background:#fff; border:1px solid #e8eaee; border-radius:14px; padding:20px 22px; margin-bottom:20px; }
  .fg { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; align-items:end; }
  .fg .sm-field { margin-bottom:0; }

  .filter-row { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; align-items:center; }
  .filter-row select { padding:7px 10px; border:1.5px solid #e8eaee; border-radius:8px; font-size:12.5px; }
  .filter-chip { font-size:12px; color:#888; background:#f5f6f8; padding:5px 12px; border-radius:20px; display:flex; align-items:center; gap:6px; }
  .filter-chip a { color:#FF3D33; text-decoration:none; font-weight:700; }

  .posts-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e8eaee; border-radius:14px; overflow:hidden; }
  .posts-table th { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#aaa;
                    padding:11px 14px; text-align:left; border-bottom:1.5px solid #eef0f3; }
  .posts-table td { padding:11px 14px; font-size:13px; border-bottom:1px solid #f5f6f8; vertical-align:middle; }
  .posts-table tr:last-child td { border-bottom:none; }
  .posts-table tr:hover td { background:#fafbfc; }
  .pt-title { font-weight:600; color:#1a1a1a; text-decoration:none; }
  .pt-title:hover { color:#FF3D33; }
  .pt-meta { font-size:11.5px; color:#aaa; }
  .pt-platform { font-size:11px; background:#f5f6f8; padding:2px 8px; border-radius:6px; color:#777; }
  .bulk-bar { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
  .empty { text-align:center; padding:48px; color:#ccc; font-size:14px; }
</style>
</head>
<body>
<div class="sm-shell">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="sm-main">
    <div class="sm-topbar">
      <h1>Posts</h1>
      <div class="sm-user-chip">
        <span class="sm-role-badge"><?= htmlspecialchars($staff['sm_role']) ?></span>
        <strong><?= htmlspecialchars($staff['name']) ?></strong>
      </div>
    </div>

    <?php if ($mm): ?>
    <div class="sm-msg <?= $mt==='ok'?'sm-msg-ok':'sm-msg-err' ?>" style="max-width:600px"><?= htmlspecialchars($mm) ?></div>
    <?php endif; ?>

    <div class="panel">
      <h2 style="font-size:13px;font-weight:800;margin:0 0 16px">New Post</h2>
      <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="fg">
          <div class="sm-field">
            <label>Calendar</label>
            <select name="calendar_id" required>
              <option value="">Select calendar…</option>
              <?php foreach ($calendars as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $calendar_id===(int)$c['id']?'selected':'' ?>><?= htmlspecialchars($c['client_name']) ?> — <?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="sm-field" style="grid-column:span 2"><label>Title</label><input type="text" name="title" placeholder="e.g. Eid promo carousel" required></div>
        </div>
        <button type="submit" class="sm-btn-primary" style="margin-top:16px;width:auto;padding:11px 24px">＋ Create Post</button>
      </form>
    </div>

    <form method="POST" id="bulkForm">
    <input type="hidden" name="action" value="bulk_archive">
    <div class="filter-row">
      <select onchange="location.href='?'+(this.value?'status='+this.value:'')+'<?= $calendar_id?'&calendar_id='.$calendar_id:'' ?>'">
        <option value="">All statuses</option>
        <?php foreach (SM_POST_STATUSES as $s): ?>
        <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($calendar_id): ?>
      <div class="filter-chip">Calendar: <?= htmlspecialchars($current_calendar_name) ?> <a href="?">✕</a></div>
      <?php endif; ?>
      <button type="submit" class="cl-btn" style="margin-left:auto;padding:7px 16px;border:1.5px solid #e8eaee;background:#fff;border-radius:8px;font-size:12px;cursor:pointer" onclick="return confirm('Archive selected posts?')">Archive Selected</button>
    </div>

    <?php if (empty($posts)): ?>
    <div class="empty">No posts found.</div>
    <?php else: ?>
    <table class="posts-table">
      <thead><tr>
        <th style="width:30px"><input type="checkbox" onclick="document.querySelectorAll('.post-check').forEach(c=>c.checked=this.checked)"></th>
        <th>Title</th><th>Client / Calendar</th><th>Platform</th><th>Status</th><th>Scheduled</th><th>Owner</th>
      </tr></thead>
      <tbody>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td><input type="checkbox" class="post-check" name="post_ids[]" value="<?= $p['id'] ?>" form="bulkForm"></td>
        <td><a class="pt-title" href="detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a></td>
        <td class="pt-meta"><?= htmlspecialchars($p['client_name']) ?> · <?= htmlspecialchars($p['calendar_name']) ?></td>
        <td><?php if($p['platform']): ?><span class="pt-platform"><?= htmlspecialchars($p['platform']) ?></span><?php else: ?>—<?php endif; ?></td>
        <td><?= sm_status_badge($p['status']) ?></td>
        <td class="pt-meta"><?= $p['scheduled_at'] ? date('d M Y', strtotime($p['scheduled_at'])) : '—' ?></td>
        <td class="pt-meta">#<?= $p['owner_id'] ?? '—' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    </form>
  </main>
</div>
</body>
</html>
