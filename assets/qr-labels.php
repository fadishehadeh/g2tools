<?php
session_start();
require '../config.php';
require_login();
require_can('assets');
require '_lib.php';

// Single asset or bulk (comma-separated ids or 'all')
$ids_param = $_GET['ids'] ?? '';
$single_id = (int)($_GET['id'] ?? 0);

if ($single_id) {
    $assets = db()->query("SELECT a.*,c.name cat_name FROM assets a LEFT JOIN asset_categories c ON c.id=a.category_id WHERE a.id=$single_id")->fetchAll();
} elseif ($ids_param === 'all') {
    $assets = db()->query("SELECT a.*,c.name cat_name FROM assets a LEFT JOIN asset_categories c ON c.id=a.category_id ORDER BY a.name")->fetchAll();
} elseif ($ids_param) {
    $ids = array_map('intval', explode(',', $ids_param));
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $s   = db()->prepare("SELECT a.*,c.name cat_name FROM assets a LEFT JOIN asset_categories c ON c.id=a.category_id WHERE a.id IN ($ph) ORDER BY a.name");
    $s->execute($ids); $assets = $s->fetchAll();
} else {
    // Show selector
    $all = db()->query("SELECT id,tag,name FROM assets ORDER BY name")->fetchAll();
    $assets = null;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>QR Labels — G2 Tools</title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<style>
*,*::before,*::after{box-sizing:border-box}
.pw{padding:28px 36px 60px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;border:none;cursor:pointer}
.btn-red{background:#FF3D33;color:#fff}.btn-red:hover{background:#c0170e}
.btn-grey{background:#f1f5f9;color:#444}.btn-grey:hover{background:#e2e8f0}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:20px 22px;margin-bottom:16px}

/* Label grid */
.label-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-top:10px}
.label-card{border:1.5px solid #e8eaee;border-radius:10px;padding:14px;text-align:center;background:#fff;cursor:pointer;transition:border-color .15s}
.label-card:hover{border-color:#FF3D33}
.label-card input[type=checkbox]{margin-bottom:6px;accent-color:#FF3D33;width:14px;height:14px}
.label-tag{font-family:monospace;font-size:11px;color:#aaa;margin-top:6px}
.label-name{font-size:12px;font-weight:700;color:#1a1a1a;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.label-cat{font-size:11px;color:#aaa}

/* Print label */
@media print {
  .sidebar,.main-content .topbar,.no-print{display:none!important}
  .main-content{margin-left:0!important;padding:0!important}
  .pw{padding:0}
  .print-labels{display:grid!important;grid-template-columns:repeat(3,1fr);gap:0;padding:0}
  .plabel{border:1px dashed #ccc;padding:10px;text-align:center;page-break-inside:avoid}
  .plabel img{width:100px;height:100px}
  .plabel .ptag{font-family:monospace;font-size:10px;color:#555;margin-top:4px}
  .plabel .pname{font-size:11px;font-weight:700;margin-top:2px}
  .plabel .pcat{font-size:10px;color:#888}
  .plabel .pco{font-size:9px;color:#aaa;margin-top:4px}
}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">QR Labels</span>
</div>
<div class="pw">
  <div class="ph">
    <h1>QR Code Labels</h1>
    <div class="no-print" style="display:flex;gap:8px">
      <button class="btn btn-grey" onclick="window.print()">🖨 Print</button>
    </div>
  </div>

  <?php if ($assets === null): ?>
  <!-- Selector -->
  <div class="panel no-print">
    <p style="font-size:13px;color:#555;margin:0 0 14px">Select assets to print labels for, or <a href="?ids=all" style="color:#FF3D33;font-weight:700">print all</a>.</p>
    <form method="GET" action="qr-labels.php">
      <div class="label-grid">
        <?php foreach ($all as $a): ?>
        <label class="label-card">
          <input type="checkbox" name="ids[]" value="<?= $a['id'] ?>">
          <div class="label-name"><?= htmlspecialchars($a['name']) ?></div>
          <div class="label-tag"><?= htmlspecialchars($a['tag']) ?></div>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px">
        <button class="btn btn-red" type="submit" onclick="transformCheckboxes(this.form)">Generate Labels</button>
        <a href="?ids=all" class="btn btn-grey">All Assets</a>
      </div>
    </form>
  </div>
  <script>
  function transformCheckboxes(form) {
    const checked = [...form.querySelectorAll('input[type=checkbox]:checked')].map(c=>c.value);
    if (!checked.length) { alert('Select at least one asset.'); return false; }
    // Replace checkboxes with single ids param
    window.location = 'qr-labels.php?ids=' + checked.join(',');
    return false;
  }
  </script>

  <?php else: ?>
  <!-- Print labels -->
  <div class="no-print" style="margin-bottom:14px;font-size:13px;color:#888"><?= count($assets) ?> label<?= count($assets)!==1?'s':'' ?> — <a href="qr-labels.php" style="color:#FF3D33">← Change selection</a></div>
  <div class="print-labels" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px">
    <?php foreach ($assets as $a): ?>
    <div class="plabel" style="border:1.5px dashed #e8eaee;border-radius:10px;padding:14px;text-align:center">
      <img src="<?= asset_qr_url($a['tag'], 160) ?>" alt="QR" style="width:120px;height:120px;display:block;margin:0 auto" loading="lazy">
      <div class="ptag" style="font-family:monospace;font-size:11px;color:#555;margin-top:6px"><?= htmlspecialchars($a['tag']) ?></div>
      <div class="pname" style="font-size:12px;font-weight:700;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($a['name']) ?></div>
      <div class="pcat" style="font-size:10px;color:#888"><?= htmlspecialchars($a['cat_name'] ?? '') ?></div>
      <div class="pco" style="font-size:9px;color:#aaa;margin-top:4px">G2 Group — Asset Management</div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</div>
</body>
</html>
