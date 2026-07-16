<?php
session_start();
require '../config.php';
require_login();
if (!is_it_admin()) { header('Location: /assets/'); exit; }

$id = (int)($_GET['id'] ?? 0);
$a  = db()->prepare("SELECT * FROM assets WHERE id=?");
$a->execute([$id]); $a = $a->fetch();
if (!$a) { header('Location: list.php'); exit; }

$error = '';
$categories  = db()->query("SELECT * FROM asset_categories ORDER BY name")->fetchAll();
$locations   = db()->query("SELECT * FROM asset_locations ORDER BY name")->fetchAll();
$departments = db()->query("SELECT * FROM asset_departments ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = function($k){ return trim(strip_tags($_POST[$k] ?? '')); };
    $name = $f('name');
    if (!$name) { $error = 'Name is required.'; }
    else {
        db()->prepare("UPDATE assets SET name=?,category_id=?,location_id=?,department_id=?,serial_number=?,brand=?,vendor=?,model=?,condition_state=?,purchase_date=?,purchase_value=?,warranty_expiry=?,status=?,notes=?,depreciation_method=?,useful_life_years=?,salvage_value=? WHERE id=?")
          ->execute([
            $name,
            ($f('category_id') ?: null), ($f('location_id') ?: null), ($f('department_id') ?: null),
            ($f('serial_number') ?: null), ($f('brand') ?: null), ($f('vendor') ?: null),
            ($f('model') ?: null), ($f('condition_state') ?: null),
            ($f('purchase_date') ?: null),
            ($f('purchase_value') !== '' ? (float)$f('purchase_value') : null),
            ($f('warranty_expiry') ?: null), $_POST['status'] ?? 'active',
            ($f('notes') ?: null),
            $f('depreciation_method') ?: 'none',
            ($f('useful_life_years') !== '' ? (int)$f('useful_life_years') : null),
            ($f('salvage_value') !== '' ? (float)$f('salvage_value') : null),
            $id
        ]);
        db()->prepare("INSERT INTO asset_activity_log (asset_id,user_id,action,detail) VALUES (?,?,?,?)")
          ->execute([$id, $_SESSION['g2_user']['id'], 'edited', 'Asset details updated']);
        header("Location: view.php?id=$id"); exit;
    }
}
// Merge POST overrides for re-display on error
$a = array_merge($a, array_intersect_key($_POST, $a));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Asset — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<script src="/form-validate.js" defer></script>
<style>.form-card{max-width:700px}</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="view.php?id=<?= $id ?>">← <?= htmlspecialchars($a['name']) ?></a>
  <span class="topbar-title">Edit Asset</span>
</div>
<div class="form-page-wrap">
<div class="form-card">
  <div class="form-header">
    <div class="fh-text"><h1>Edit <?= htmlspecialchars($a['name']) ?></h1><p>Tag: <?= htmlspecialchars($a['tag']) ?></p></div>
    <div class="fh-accent">✏️</div>
  </div>
  <div class="form-accent-bar"></div>
  <?php if ($error): ?><div style="margin:16px 24px;padding:12px 16px;background:#fff5f5;border:1px solid #fca5a5;border-radius:8px;font-size:13px;color:#dc2626"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST" data-validate>
  <div class="form-body">
    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Identity</h2></div>
      <div class="grid2">
        <div class="field"><label class="field-label">Asset Name *</label><input type="text" name="name" required value="<?= htmlspecialchars($a['name']) ?>"></div>
        <div class="field"><label class="field-label">Asset Tag</label><input type="text" value="<?= htmlspecialchars($a['tag']) ?>" disabled style="background:#f5f6f8;color:#aaa"></div>
      </div>
      <div class="grid2">
        <div class="field"><label class="field-label">Brand / Manufacturer</label><input type="text" name="brand" value="<?= htmlspecialchars($a['brand']??'') ?>"></div>
        <div class="field"><label class="field-label">Model</label><input type="text" name="model" value="<?= htmlspecialchars($a['model']??'') ?>"></div>
      </div>
      <div class="grid2">
        <div class="field"><label class="field-label">Vendor / Supplier</label><input type="text" name="vendor" value="<?= htmlspecialchars($a['vendor']??'') ?>"></div>
        <div class="field"><label class="field-label">Condition</label>
          <select name="condition_state">
            <option value="">— Select —</option>
            <?php foreach (['new'=>'New','good'=>'Good','fair'=>'Fair','poor'=>'Poor'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($a['condition_state']??'')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field" style="max-width:300px"><label class="field-label">Serial Number</label><input type="text" name="serial_number" value="<?= htmlspecialchars($a['serial_number']??'') ?>"></div>
    </div>
    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Classification</h2></div>
      <div class="grid2">
        <div class="field"><label class="field-label">Category</label>
          <select name="category_id"><option value="">—</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $a['category_id']==$c['id']?'selected':'' ?>><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="field-label">Status</label>
          <select name="status"><?php foreach(['active','in_repair','retired','disposed','lost'] as $s): ?><option value="<?= $s ?>" <?= $a['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="grid2">
        <div class="field"><label class="field-label">Location</label>
          <select name="location_id"><option value="">—</option><?php foreach($locations as $l): ?><option value="<?= $l['id'] ?>" <?= $a['location_id']==$l['id']?'selected':'' ?>><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="field-label">Department</label>
          <select name="department_id"><option value="">—</option><?php foreach($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $a['department_id']==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
      </div>
    </div>
    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Purchase Info</h2></div>
      <div class="grid2">
        <div class="field"><label class="field-label">Purchase Date</label><input type="date" name="purchase_date" value="<?= htmlspecialchars($a['purchase_date']??'') ?>"></div>
        <div class="field"><label class="field-label">Purchase Value (QAR)</label><input type="number" name="purchase_value" step="0.01" value="<?= htmlspecialchars($a['purchase_value']??'') ?>"></div>
      </div>
      <div class="field" style="max-width:240px"><label class="field-label">Warranty Expiry</label><input type="date" name="warranty_expiry" value="<?= htmlspecialchars($a['warranty_expiry']??'') ?>"></div>
    </div>
    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Depreciation</h2></div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Method</label>
          <select name="depreciation_method">
            <option value="none" <?= ($a['depreciation_method']??'none')==='none'?'selected':'' ?>>None</option>
            <option value="straight_line" <?= ($a['depreciation_method']??'')==='straight_line'?'selected':'' ?>>Straight Line</option>
            <option value="double_declining" <?= ($a['depreciation_method']??'')==='double_declining'?'selected':'' ?>>Double Declining Balance</option>
          </select>
        </div>
        <div class="field">
          <label class="field-label">Useful Life (years)</label>
          <input type="number" name="useful_life_years" value="<?= htmlspecialchars($a['useful_life_years']??'') ?>" min="1" max="50" placeholder="e.g. 5">
        </div>
        <div class="field">
          <label class="field-label">Salvage Value (QAR)</label>
          <input type="number" name="salvage_value" value="<?= htmlspecialchars($a['salvage_value']??'') ?>" step="0.01" min="0" placeholder="0.00">
        </div>
      </div>
    </div>
    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Notes</h2></div>
      <div class="field"><textarea name="notes" rows="3"><?= htmlspecialchars($a['notes']??'') ?></textarea></div>
    </div>
  </div>
  <div class="form-footer">
    <button type="submit" class="submit-btn">Save Changes</button>
    <a href="view.php?id=<?= $id ?>" style="margin-left:14px;font-size:13px;color:#aaa;text-decoration:none">Cancel</a>
  </div>
  </form>
</div>
</div>
</div>
</body>
</html>

