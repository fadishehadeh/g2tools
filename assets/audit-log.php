<?php
session_start();
require '../config.php';
require_login();
require_can('assets');
require '_lib.php';

$search  = trim($_GET['q'] ?? '');
$action  = $_GET['action'] ?? '';
$asset_id = (int)($_GET['asset_id'] ?? 0);
$page    = max(1,(int)($_GET['p'] ?? 1));
$per     = 50;

$where = []; $params = [];
if ($search) { $where[] = "(a.name LIKE ? OR a.tag LIKE ? OR u.name LIKE ? OR al.detail LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s,$s]); }
if ($action) { $where[] = "al.action=?"; $params[] = $action; }
if ($asset_id) { $where[] = "al.asset_id=?"; $params[] = $asset_id; }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$total = db()->prepare("SELECT COUNT(*) FROM asset_activity_log al JOIN assets a ON a.id=al.asset_id LEFT JOIN users u ON u.id=al.user_id $whereStr");
$total->execute($params); $total = (int)$total->fetchColumn();
$pages = max(1, ceil($total/$per)); $offset = ($page-1)*$per;

$logs = db()->prepare("SELECT al.*, a.name asset_name, a.tag, u.name uname
    FROM asset_activity_log al
    JOIN assets a ON a.id=al.asset_id
    LEFT JOIN users u ON u.id=al.user_id
    $whereStr ORDER BY al.created_at DESC LIMIT $per OFFSET $offset");
$logs->execute($params); $logs = $logs->fetchAll();

$actions_list = db()->query("SELECT DISTINCT action FROM asset_activity_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// CSV export
if (($_GET['export']??'') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="asset_audit_log_'.date('Y-m-d').'.csv"');
    $f = fopen('php://output','w'); fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($f,['Date/Time','Asset Tag','Asset Name','Action','Detail','User']);
    $allLogs = db()->prepare("SELECT al.*, a.name asset_name, a.tag, u.name uname
        FROM asset_activity_log al JOIN assets a ON a.id=al.asset_id LEFT JOIN users u ON u.id=al.user_id
        $whereStr ORDER BY al.created_at DESC");
    $allLogs->execute($params);
    foreach ($allLogs->fetchAll() as $l)
        fputcsv($f,[$l['created_at'],$l['tag'],$l['asset_name'],$l['action'],$l['detail']??'',$l['uname']??'']);
    fclose($f); exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Audit Log — G2 Tools</title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<style>
*,*::before,*::after{box-sizing:border-box}
.pw{padding:28px 36px 60px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center}
.filters input,.filters select{padding:8px 12px;border:1.5px solid #e0e2e8;border-radius:7px;font-size:13px;font-family:inherit}
.filters button{padding:8px 16px;background:#1a1a1a;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer}
.btn-csv{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;background:#1d6f42;color:#fff;border-radius:7px;font-size:12px;font-weight:700;text-decoration:none}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;overflow:hidden}
table{width:100%;border-collapse:collapse;font-size:13px}
th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#aaa;padding:10px 14px;border-bottom:1.5px solid #eef0f3;text-align:left;background:#fafbfc;white-space:nowrap}
td{padding:9px 14px;border-bottom:1px solid #f5f6f8;vertical-align:middle}
tr:last-child td{border-bottom:none}tr:hover td{background:#fafbfc}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:6px;vertical-align:middle}
.dot-created{background:#16a34a}.dot-assigned{background:#0891b2}.dot-maintenance{background:#d97706}
.dot-status_change{background:#7c3aed}.dot-disposed{background:#dc2626}.dot-transferred{background:#0891b2}
.dot-default{background:#aaa}
.pager{display:flex;gap:6px;margin-top:16px;align-items:center;flex-wrap:wrap}
.pager a,.pager span{padding:6px 12px;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;border:1.5px solid #e0e2e8;color:#555}
.pager a:hover{border-color:#aaa}.pager .cur{background:#FF3D33;color:#fff;border-color:#FF3D33}
.total-label{font-size:12px;color:#aaa;margin-left:auto}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">Audit Log</span>
</div>
<div class="pw">
  <div class="ph">
    <h1>Audit Log</h1>
    <a class="btn-csv" href="?q=<?= urlencode($search) ?>&action=<?= urlencode($action) ?>&asset_id=<?= $asset_id ?>&export=csv">⬇ Export CSV</a>
  </div>

  <form class="filters" method="GET">
    <input type="search" name="q" placeholder="Search asset, user, detail…" value="<?= htmlspecialchars($search) ?>" style="min-width:220px">
    <select name="action">
      <option value="">All actions</option>
      <?php foreach ($actions_list as $al): ?>
      <option value="<?= $al ?>" <?= $action===$al?'selected':'' ?>><?= htmlspecialchars(ucfirst(str_replace('_',' ',$al))) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
    <?php if ($search||$action||$asset_id): ?><a href="audit-log.php" style="font-size:13px;color:#aaa;text-decoration:none">Clear</a><?php endif; ?>
    <span class="total-label"><?= number_format($total) ?> entries</span>
  </form>

  <div class="panel">
    <table>
      <thead><tr><th>Date / Time</th><th>Asset</th><th>Action</th><th>Detail</th><th>User</th></tr></thead>
      <tbody>
      <?php if (!$logs): ?>
      <tr><td colspan="5" style="text-align:center;color:#ccc;padding:36px">No log entries found.</td></tr>
      <?php endif; ?>
      <?php foreach ($logs as $l):
        $dotClass = 'dot-'.(in_array($l['action'],['created','assigned','maintenance','status_change','disposed','transferred'])?$l['action']:'default');
      ?>
      <tr>
        <td style="color:#888;font-size:12px;white-space:nowrap"><?= date('d M Y H:i',strtotime($l['created_at'])) ?></td>
        <td>
          <a href="view.php?id=<?= $l['asset_id'] ?>" style="font-weight:600;color:inherit;text-decoration:none"><?= htmlspecialchars($l['asset_name']) ?></a>
          <div style="font-size:11px;color:#aaa;font-family:monospace"><?= htmlspecialchars($l['tag']) ?></div>
        </td>
        <td>
          <span class="dot <?= $dotClass ?>"></span>
          <span style="font-size:12px;font-weight:600"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$l['action']))) ?></span>
        </td>
        <td style="color:#555;font-size:12px;max-width:280px"><?= htmlspecialchars($l['detail']??'') ?></td>
        <td style="color:#888;font-size:12px"><?= htmlspecialchars($l['uname']??'—') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pager">
    <?php if ($page>1): ?><a href="?q=<?= urlencode($search) ?>&action=<?= urlencode($action) ?>&asset_id=<?= $asset_id ?>&p=<?= $page-1 ?>">← Prev</a><?php endif; ?>
    <?php for ($i=max(1,$page-3);$i<=min($pages,$page+3);$i++): ?>
    <<?= $i===$page?'span class="cur"':'a href="?q='.urlencode($search).'&action='.urlencode($action).'&asset_id='.$asset_id.'&p='.$i.'"' ?>><?= $i ?></<?= $i===$page?'span':'a' ?>>
    <?php endfor; ?>
    <?php if ($page<$pages): ?><a href="?q=<?= urlencode($search) ?>&action=<?= urlencode($action) ?>&asset_id=<?= $asset_id ?>&p=<?= $page+1 ?>">Next →</a><?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</div>
</body>
</html>
