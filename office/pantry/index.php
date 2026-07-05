<?php
session_start();
require '../../config.php';
require_login();
$admin = is_admin();

$items = db()->query("SELECT * FROM pantry_items WHERE active=1 ORDER BY category, name")->fetchAll();

// Group by category
$grouped = [];
foreach ($items as $item) {
    $grouped[$item['category']][] = $item;
}

function stock_status(array $item): string {
    $pct = $item['par_level'] > 0 ? $item['current_stock'] / $item['par_level'] : 1;
    if ($item['current_stock'] <= 0)          return 'out';
    if ($pct <= 0.25)                          return 'critical';
    if ($item['current_stock'] < $item['par_level']) return 'low';
    return 'ok';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pantry — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
  .pantry-wrap { padding:32px 40px 80px; }
  .top-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:10px; }
  .top-bar h2 { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#999; }
  .btn-primary { display:inline-flex; align-items:center; gap:6px; padding:10px 18px; background:#FF3D33;
                 color:#fff; border-radius:30px; font-size:13px; font-weight:700; text-decoration:none; }
  .btn-primary:hover { background:#c0170e; }
  .btn-outline { display:inline-flex; align-items:center; gap:6px; padding:10px 18px; background:#fff;
                 color:#555; border-radius:30px; font-size:13px; font-weight:600; text-decoration:none;
                 border:1.5px solid #e8eaee; }
  .btn-outline:hover { border-color:#aaa; }

  .summary-row { display:flex; gap:12px; margin-bottom:32px; flex-wrap:wrap; }
  .sum-chip { padding:8px 16px; border-radius:20px; font-size:12px; font-weight:700; display:flex; align-items:center; gap:6px; }
  .chip-ok       { background:#dcfce7; color:#166534; }
  .chip-low      { background:#fef9c3; color:#854d0e; }
  .chip-critical { background:#fee2e2; color:#991b1b; }
  .chip-out      { background:#f3f4f6; color:#6b7280; }

  .cat-section { margin-bottom:32px; }
  .cat-title { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#999;
               margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid #e8eaee; }
  .items-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:12px; }

  .item-card { background:#fff; border:1.5px solid #e8eaee; border-radius:12px; padding:18px;
               transition:border-color .15s; }
  .item-card.status-ok       { border-left:4px solid #22c55e; }
  .item-card.status-low      { border-left:4px solid #f59e0b; }
  .item-card.status-critical { border-left:4px solid #ef4444; }
  .item-card.status-out      { border-left:4px solid #9ca3af; opacity:.7; }

  .item-name  { font-size:14px; font-weight:700; color:#1a1a1a; margin-bottom:4px; }
  .item-stock { font-size:24px; font-weight:900; letter-spacing:-1px; margin:8px 0 2px; }
  .item-unit  { font-size:11px; color:#aaa; text-transform:uppercase; letter-spacing:.5px; }
  .item-par   { font-size:11px; color:#bbb; margin-top:4px; }

  .status-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; margin-top:6px; }
  .sb-ok       { background:#dcfce7; color:#166534; }
  .sb-low      { background:#fef9c3; color:#854d0e; }
  .sb-critical { background:#fee2e2; color:#991b1b; }
  .sb-out      { background:#f3f4f6; color:#6b7280; }

  .item-actions { display:flex; gap:6px; margin-top:14px; }
  .act-btn { flex:1; padding:7px 0; border:1.5px solid #e8eaee; background:#fff; border-radius:8px;
             font-size:12px; font-weight:600; cursor:pointer; color:#555; text-align:center;
             text-decoration:none; transition:all .15s; }
  .act-btn:hover { border-color:#FF3D33; color:#FF3D33; }
  .act-btn.in:hover  { border-color:#16a34a; color:#16a34a; }
  .act-btn.out:hover { border-color:#dc2626; color:#dc2626; }

  .progress-bar { height:5px; background:#f0f1f3; border-radius:3px; margin-top:10px; overflow:hidden; }
  .progress-fill { height:100%; border-radius:3px; transition:width .3s; }
  .fill-ok       { background:#22c55e; }
  .fill-low      { background:#f59e0b; }
  .fill-critical { background:#ef4444; }
  .fill-out      { background:#d1d5db; }
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/">G2 Tools</a>
  <span class="topbar-title">Pantry</span>
</div>
<div class="pantry-wrap">

  <?php
  $counts = ['ok'=>0,'low'=>0,'critical'=>0,'out'=>0];
  foreach ($items as $it) $counts[stock_status($it)]++;
  ?>
  <div class="top-bar">
    <h2>Stock Overview</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ($admin): ?>
      <a class="btn-outline" href="items.php">⚙ Manage Items</a>
      <a class="btn-outline" href="history.php">📋 History</a>
      <?php endif; ?>
      <a class="btn-primary" href="stock-in.php">＋ Stock In</a>
      <a class="btn-outline" href="stock-out.php">− Stock Out</a>
    </div>
  </div>

  <div class="summary-row">
    <div class="sum-chip chip-ok">✓ <?= $counts['ok'] ?> OK</div>
    <div class="sum-chip chip-low">⚠ <?= $counts['low'] ?> Low</div>
    <div class="sum-chip chip-critical">🔴 <?= $counts['critical'] ?> Critical</div>
    <div class="sum-chip chip-out">✗ <?= $counts['out'] ?> Out of Stock</div>
  </div>

  <?php if (empty($items)): ?>
  <div style="text-align:center;padding:60px;color:#bbb">
    <div style="font-size:40px;margin-bottom:12px">🍵</div>
    No pantry items set up yet.
    <?php if ($admin): ?>
    <div style="margin-top:16px"><a href="items.php" class="btn-primary">Add Items</a></div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <?php foreach ($grouped as $cat => $cat_items): ?>
  <div class="cat-section">
    <div class="cat-title"><?= htmlspecialchars($cat) ?></div>
    <div class="items-grid">
    <?php foreach ($cat_items as $item):
      $st = stock_status($item);
      $pct = $item['par_level'] > 0 ? min(100, ($item['current_stock'] / $item['par_level']) * 100) : 100;
    ?>
    <div class="item-card status-<?= $st ?>">
      <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
      <div class="item-stock"><?= number_format($item['current_stock'], 1) ?></div>
      <div class="item-unit"><?= htmlspecialchars($item['unit']) ?></div>
      <div class="progress-bar"><div class="progress-fill fill-<?= $st ?>" style="width:<?= $pct ?>%"></div></div>
      <div class="item-par">Par: <?= number_format($item['par_level'], 1) ?> <?= htmlspecialchars($item['unit']) ?></div>
      <span class="status-badge sb-<?= $st ?>">
        <?= ['ok'=>'In Stock','low'=>'Low','critical'=>'Critical','out'=>'Out of Stock'][$st] ?>
      </span>
      <div class="item-actions">
        <a class="act-btn in"  href="stock-in.php?item=<?= $item['id'] ?>">＋ In</a>
        <a class="act-btn out" href="stock-out.php?item=<?= $item['id'] ?>">− Out</a>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>
</div>
</body>
</html>
