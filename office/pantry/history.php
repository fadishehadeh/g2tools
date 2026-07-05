<?php
session_start();
require '../../config.php';
require_login();
$admin = is_admin();
$filter_item = (int)($_GET['item'] ?? 0);
$items = db()->query("SELECT * FROM pantry_items ORDER BY name")->fetchAll();

$sql = "SELECT m.*, i.name as item_name, i.unit, u.name as uname
        FROM pantry_movements m
        JOIN pantry_items i ON i.id = m.item_id
        JOIN users u ON u.id = m.created_by";
$params = [];
if ($filter_item) { $sql .= " WHERE m.item_id = ?"; $params[] = $filter_item; }
$sql .= " ORDER BY m.created_at DESC LIMIT 200";
$stmt = db()->prepare($sql); $stmt->execute($params);
$logs = $stmt->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pantry History — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
  .hist-wrap { padding:32px 40px 80px; max-width:860px; }
  .log-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e8eaee;
               border-radius:14px; overflow:hidden; }
  .log-table th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#aaa;
                  padding:12px 14px; text-align:left; border-bottom:1.5px solid #e8eaee; }
  .log-table td { padding:11px 14px; font-size:13px; color:#444; border-bottom:1px solid #f0f1f3; }
  .log-table tr:last-child td { border-bottom:none; }
  .type-in  { color:#16a34a; font-weight:700; }
  .type-out { color:#ef4444; font-weight:700; }
  .type-adjustment { color:#d97706; font-weight:700; }
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">Pantry</a>
  <span class="topbar-title">Movement History</span>
</div>
<div class="hist-wrap">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <select name="item" onchange="this.form.submit()" style="padding:8px 12px;border:1.5px solid #e8eaee;border-radius:8px;font-size:13px">
        <option value="0">All Items</option>
        <?php foreach($items as $it): ?>
        <option value="<?= $it['id'] ?>" <?= $filter_item===$it['id']?'selected':'' ?>><?= htmlspecialchars($it['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <table class="log-table">
    <thead><tr>
      <th>Date</th><th>Item</th><th>Type</th><th>Qty</th><th>Notes</th><th>By</th>
    </tr></thead>
    <tbody>
    <?php foreach($logs as $l): ?>
    <tr>
      <td style="color:#aaa;font-size:12px;white-space:nowrap"><?= date('d M Y H:i', strtotime($l['created_at'])) ?></td>
      <td style="font-weight:600;color:#1a1a1a"><?= htmlspecialchars($l['item_name']) ?></td>
      <td class="type-<?= $l['type'] ?>"><?= $l['type'] === 'in' ? '＋ In' : ($l['type']==='out'?'− Out':'± Adj') ?></td>
      <td style="font-weight:700;font-variant-numeric:tabular-nums"><?= number_format($l['quantity'],1) ?> <?= htmlspecialchars($l['unit']) ?></td>
      <td style="color:#888"><?= htmlspecialchars($l['notes'] ?? '—') ?></td>
      <td><?= htmlspecialchars($l['uname']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>
</body>
</html>
