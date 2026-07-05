<?php
session_start();
require '../../config.php';
require_login();
$user  = current_user();
$items = db()->query("SELECT * FROM pantry_items WHERE active=1 ORDER BY category, name")->fetchAll();
$preselect = (int)($_GET['item'] ?? 0);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)$_POST['item_id'];
    $qty     = (float)$_POST['quantity'];
    $notes   = trim(strip_tags($_POST['notes'] ?? ''));
    if ($item_id && $qty > 0) {
        db()->prepare("UPDATE pantry_items SET current_stock = current_stock + ? WHERE id=?")->execute([$qty, $item_id]);
        db()->prepare("INSERT INTO pantry_movements (item_id,type,quantity,notes,created_by) VALUES (?,'in',?,?,?)")->execute([$item_id,$qty,$notes,$user['id']]);
        $msg = 'ok|Stock updated successfully.';
        $preselect = 0;
    } else {
        $msg = 'err|Please select an item and enter a valid quantity.';
    }
}
[$mt,$mm] = $msg ? explode('|',$msg,2) : ['',''];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Stock In — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">Pantry</a>
  <span class="topbar-title">Stock In</span>
</div>
<div class="form-page-wrap">
<div class="form-card" style="max-width:480px">
  <div class="form-header">
    <div class="fh-text"><h1>Stock In</h1><p>Log new stock received into the pantry</p></div>
    <div class="fh-accent">📦</div>
  </div>
  <div class="form-accent-bar" style="background:#16a34a"></div>
  <?php if ($mm): ?>
  <div style="margin:16px 24px;padding:12px 16px;border-radius:8px;font-size:13px;
    <?= $mt==='ok' ? 'background:#f0fdf4;border:1px solid #bbf7d0;color:#166534' : 'background:#fff5f5;border:1px solid #fca5a5;color:#dc2626' ?>">
    <?= htmlspecialchars($mm) ?>
  </div>
  <?php endif; ?>
  <form method="POST">
  <div class="form-body">
    <div class="section">
      <div class="field">
        <label class="field-label">Item <span style="color:#FF3D33">*</span></label>
        <select name="item_id" required>
          <option value="">Select item…</option>
          <?php foreach($items as $it): ?>
          <option value="<?= $it['id'] ?>" <?= $preselect===$it['id']?'selected':'' ?>>
            <?= htmlspecialchars($it['name']) ?> — <?= number_format($it['current_stock'],1) ?> <?= htmlspecialchars($it['unit']) ?> in stock
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="field-label">Quantity Received <span style="color:#FF3D33">*</span></label>
        <input type="number" name="quantity" step="0.1" min="0.1" placeholder="0" required>
      </div>
      <div class="field">
        <label class="field-label">Notes</label>
        <input type="text" name="notes" placeholder="e.g. Supplier delivery, invoice #123">
      </div>
    </div>
  </div>
  <div class="form-footer">
    <button type="submit" class="submit-btn" style="background:#16a34a">＋ Add Stock</button>
  </div>
  </form>
</div>
</div>
</div>
</body>
</html>
