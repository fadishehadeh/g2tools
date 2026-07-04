<?php
session_start();
require '../config.php';
require_login();
require_can('assets');

$q      = trim($_GET['q'] ?? '');
$cat    = (int)($_GET['cat'] ?? 0);
$loc    = (int)($_GET['loc'] ?? 0);
$status = $_GET['status'] ?? '';

$sql = "SELECT a.*, c.name cat_name, c.icon cat_icon, l.name loc_name,
               d.name dept_name, u.name assigned_to
        FROM assets a
        LEFT JOIN asset_categories c ON c.id=a.category_id
        LEFT JOIN asset_locations l ON l.id=a.location_id
        LEFT JOIN asset_departments d ON d.id=a.department_id
        LEFT JOIN asset_assignments aa ON aa.asset_id=a.id AND aa.returned_at IS NULL
        LEFT JOIN users u ON u.id=aa.user_id
        WHERE 1=1";
$params = [];
if ($q)      { $sql .= " AND (a.name LIKE ? OR a.tag LIKE ? OR a.serial_number LIKE ? OR a.brand LIKE ?)"; $p="%$q%"; $params=array_merge($params,[$p,$p,$p,$p]); }
if ($cat)    { $sql .= " AND a.category_id=?"; $params[]=$cat; }
if ($loc)    { $sql .= " AND a.location_id=?"; $params[]=$loc; }
if ($status) { $sql .= " AND a.status=?"; $params[]=$status; }
$sql .= " ORDER BY a.name ASC";
$stmt = db()->prepare($sql); $stmt->execute($params);
$assets = $stmt->fetchAll();

$categories = db()->query("SELECT * FROM asset_categories ORDER BY name")->fetchAll();
$locations  = db()->query("SELECT * FROM asset_locations ORDER BY name")->fetchAll();
$statuses   = ['active','in_repair','retired','disposed','lost'];

$status_colors = [
    'active'   =>['#f0fdf4','#16a34a'],
    'in_repair'=>['#fffbeb','#d97706'],
    'retired'  =>['#f5f6f8','#888'],
    'disposed' =>['#fef2f2','#dc2626'],
    'lost'     =>['#fdf4ff','#9333ea'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Asset List — G2 Tools</title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<style>
*,*::before,*::after{box-sizing:border-box}
.pw{padding:30px 36px 60px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.btn-new{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#FF3D33;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;align-items:center}
.filters input,.filters select{padding:8px 12px;border:1.5px solid #e0e2e8;border-radius:7px;font-size:13px;font-family:inherit;outline:none}
.filters input{min-width:200px}
.filters input:focus,.filters select:focus{border-color:#FF3D33}
.filters button{padding:8px 16px;background:#1a1a1a;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;overflow:hidden}
table{width:100%;border-collapse:collapse;font-size:13px}
th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#aaa;padding:10px 16px;border-bottom:1.5px solid #eef0f3;text-align:left;white-space:nowrap;background:#fafbfc}
td{padding:11px 16px;border-bottom:1px solid #f5f6f8;vertical-align:middle}
tr:last-child td{border-bottom:none}tr:hover td{background:#fafbfc}
tr.clickable-row:hover td{background:#f5f6ff}
tr.clickable-row:hover .asset-name{color:#FF3D33}
.asset-name{font-weight:600;color:#1a1a1a}
.asset-tag{font-size:11px;color:#aaa;font-family:monospace}
.status-badge{display:inline-block;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:700}
.view-link{color:#FF3D33;font-size:12px;font-weight:700;text-decoration:none}
.count-chip{background:#f5f6f8;color:#888;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:6px}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">All Assets</span>
</div>
<div class="pw">
  <div class="ph">
    <h1>Assets <span class="count-chip"><?= count($assets) ?></span></h1>
    <div style="display:flex;gap:8px">
      <a href="report.php" style="padding:9px 16px;background:#fff;border:1.5px solid #e8eaee;border-radius:8px;font-size:13px;font-weight:600;color:#444;text-decoration:none">📊 Report</a>
      <?php if (is_it_admin()): ?><a class="btn-new" href="add.php">+ Add Asset</a><?php endif; ?>
    </div>
  </div>

  <form class="filters" method="GET">
    <input type="text" name="q" placeholder="Search name, tag, serial, brand…" value="<?= htmlspecialchars($q) ?>">
    <select name="cat">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $cat==$c['id']?'selected':'' ?>><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="loc">
      <option value="">All locations</option>
      <?php foreach ($locations as $l): ?><option value="<?= $l['id'] ?>" <?= $loc==$l['id']?'selected':'' ?>><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
    <?php if ($q||$cat||$loc||$status): ?><a href="list.php" style="font-size:13px;color:#aaa;text-decoration:none">Clear</a><?php endif; ?>
  </form>

  <div class="panel">
    <table>
      <thead><tr>
        <th>Asset</th><th>Category</th><th>Brand / Model</th><th>Location</th>
        <th>Assigned To</th><th style="text-align:right">Value</th><th>Status</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($assets as $a):
        [$sbg,$sfg] = $status_colors[$a['status']] ?? ['#f5f6f8','#888'];
      ?>
      <tr class="clickable-row" onclick="location.href='view.php?id=<?= $a['id'] ?>'" style="cursor:pointer">
        <td>
          <div class="asset-name"><?= htmlspecialchars($a['name']) ?></div>
          <div class="asset-tag"><?= htmlspecialchars($a['tag']) ?></div>
        </td>
        <td><?= $a['cat_icon'] ?? '📦' ?> <?= htmlspecialchars($a['cat_name'] ?? '—') ?></td>
        <td style="color:#555"><?= htmlspecialchars(implode(' ', array_filter([$a['brand'],$a['model']])) ?: '—') ?></td>
        <td style="color:#555"><?= htmlspecialchars($a['loc_name'] ?? '—') ?></td>
        <td style="color:#555"><?= htmlspecialchars($a['assigned_to'] ?? '—') ?></td>
        <td style="text-align:right;font-weight:600"><?= $a['purchase_value'] ? number_format($a['purchase_value'],2) : '—' ?></td>
        <td><span class="status-badge" style="background:<?= $sbg ?>;color:<?= $sfg ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
        <td><a class="view-link" href="view.php?id=<?= $a['id'] ?>" onclick="event.stopPropagation()">View →</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$assets): ?>
      <tr><td colspan="8" style="text-align:center;padding:40px;color:#ccc">No assets found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
</body>
</html>
