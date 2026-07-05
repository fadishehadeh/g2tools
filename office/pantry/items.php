<?php
session_start();
require '../../config.php';
require_admin();
$msg = '';

// Handle add / edit / toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $name     = trim(strip_tags($_POST['name'] ?? ''));
        $unit     = trim(strip_tags($_POST['unit'] ?? 'pcs'));
        $cat      = trim(strip_tags($_POST['category'] ?? 'General'));
        $par      = (float)($_POST['par_level'] ?? 5);
        $reorder  = (float)($_POST['reorder_qty'] ?? 10);
        $stock    = (float)($_POST['current_stock'] ?? 0);
        if (!$name) { $msg = 'err|Name is required.'; }
        elseif ($action === 'add') {
            db()->prepare("INSERT INTO pantry_items (name,unit,category,par_level,reorder_qty,current_stock) VALUES (?,?,?,?,?,?)")
               ->execute([$name,$unit,$cat,$par,$reorder,$stock]);
            $msg = 'ok|Item added.';
        } else {
            db()->prepare("UPDATE pantry_items SET name=?,unit=?,category=?,par_level=?,reorder_qty=? WHERE id=?")
               ->execute([$name,$unit,$cat,$par,$reorder,(int)$_POST['id']]);
            $msg = 'ok|Item updated.';
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        db()->prepare("UPDATE pantry_items SET active = 1 - active WHERE id=?")->execute([$id]);
        $msg = 'ok|Item updated.';
    }
}

[$mt,$mm] = $msg ? explode('|',$msg,2) : ['',''];
$items = db()->query("SELECT * FROM pantry_items ORDER BY category, name")->fetchAll();
$categories = array_unique(array_column($items, 'category'));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pantry Items — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
  .items-wrap { padding:32px 40px 80px; max-width:900px; }
  .items-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e8eaee;
                 border-radius:14px; overflow:hidden; margin-top:24px; }
  .items-table th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#aaa;
                    padding:12px 14px; text-align:left; border-bottom:1.5px solid #e8eaee; }
  .items-table td { padding:11px 14px; font-size:13px; color:#444; border-bottom:1px solid #f0f1f3; }
  .items-table tr:last-child td { border-bottom:none; }
  .stock-num { font-weight:700; font-variant-numeric:tabular-nums; }
  .inactive { opacity:.45; }
  .add-form { background:#fff; border:1px solid #e8eaee; border-radius:14px; padding:24px; margin-bottom:0; }
  .add-form h3 { font-size:13px; font-weight:800; color:#1a1a1a; margin-bottom:18px; }
  .fg { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:12px; }
  .fg .field { margin:0; }
  .btn-sm { padding:8px 16px; border-radius:20px; font-size:12px; font-weight:700; cursor:pointer; border:none; }
  .btn-add  { background:#FF3D33; color:#fff; }
  .btn-deactivate { background:#f3f4f6; color:#6b7280; }
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">Pantry</a>
  <span class="topbar-title">Manage Items</span>
</div>
<div class="items-wrap">

  <?php if ($mm): ?>
  <div style="padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;
    <?= $mt==='ok' ? 'background:#f0fdf4;border:1px solid #bbf7d0;color:#166534' : 'background:#fff5f5;border:1px solid #fca5a5;color:#dc2626' ?>">
    <?= htmlspecialchars($mm) ?>
  </div>
  <?php endif; ?>

  <!-- Add item -->
  <div class="add-form">
    <h3>Add New Item</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="fg">
        <div class="field"><label class="field-label">Name *</label><input type="text" name="name" placeholder="e.g. Coffee" required></div>
        <div class="field"><label class="field-label">Unit</label><input type="text" name="unit" value="pcs" placeholder="pcs / kg / L"></div>
        <div class="field"><label class="field-label">Category</label>
          <input type="text" name="category" value="General" list="cats" placeholder="General">
          <datalist id="cats"><?php foreach($categories as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?></datalist>
        </div>
        <div class="field"><label class="field-label">Par Level</label><input type="number" name="par_level" value="5" min="0" step="0.1"></div>
        <div class="field"><label class="field-label">Reorder Qty</label><input type="number" name="reorder_qty" value="10" min="0" step="0.1"></div>
        <div class="field"><label class="field-label">Opening Stock</label><input type="number" name="current_stock" value="0" min="0" step="0.1"></div>
      </div>
      <div style="margin-top:16px"><button type="submit" class="btn-sm btn-add">＋ Add Item</button></div>
    </form>
  </div>

  <!-- Items table -->
  <table class="items-table">
    <thead><tr>
      <th>Name</th><th>Category</th><th>Unit</th><th>Stock</th><th>Par</th><th>Reorder Qty</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach($items as $it): ?>
    <tr class="<?= !$it['active'] ? 'inactive' : '' ?>">
      <td style="font-weight:600;color:#1a1a1a"><?= htmlspecialchars($it['name']) ?></td>
      <td><span style="font-size:11px;background:#f6f7f9;padding:2px 8px;border-radius:6px"><?= htmlspecialchars($it['category']) ?></span></td>
      <td><?= htmlspecialchars($it['unit']) ?></td>
      <td class="stock-num"><?= number_format($it['current_stock'],1) ?></td>
      <td><?= number_format($it['par_level'],1) ?></td>
      <td><?= number_format($it['reorder_qty'],1) ?></td>
      <td><?= $it['active'] ? '<span style="color:#16a34a;font-weight:700;font-size:12px">Active</span>' : '<span style="color:#aaa;font-size:12px">Inactive</span>' ?></td>
      <td>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $it['id'] ?>">
          <button class="btn-sm btn-deactivate"><?= $it['active'] ? 'Deactivate' : 'Activate' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

</div>
</div>
</body>
</html>
