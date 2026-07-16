<?php
session_start();
require '../config.php';
require_login();
require_it_admin_or_superadmin();

function require_it_admin_or_superadmin(): void {
    if (!is_it_admin() && !is_admin()) { header('Location: /assets/'); exit; }
}

$error = '';
$categories  = db()->query("SELECT * FROM asset_categories ORDER BY name")->fetchAll();
$locations   = db()->query("SELECT * FROM asset_locations ORDER BY name")->fetchAll();
$departments = db()->query("SELECT * FROM asset_departments ORDER BY name")->fetchAll();
$users_list  = db()->query("SELECT id,name FROM users WHERE is_active=1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = function($k){ return trim(strip_tags($_POST[$k] ?? '')); };
    $name   = $f('name');
    $tag    = strtoupper(preg_replace('/\s+/','', $f('tag')));
    $status = $_POST['status'] ?? 'active';
    if (!$name || !$tag) { $error = 'Name and Asset Tag are required.'; }
    else {
        $dup = db()->prepare("SELECT id FROM assets WHERE tag=?");
        $dup->execute([$tag]);
        if ($dup->fetchColumn()) { $error = "Tag '$tag' already exists."; }
        else {
            db()->prepare("INSERT INTO assets (tag,name,category_id,location_id,department_id,serial_number,brand,model,purchase_date,purchase_value,warranty_expiry,status,notes,depreciation_method,useful_life_years,salvage_value,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
              ->execute([
                $tag, $name,
                ($f('category_id') ?: null), ($f('location_id') ?: null), ($f('department_id') ?: null),
                ($f('serial_number') ?: null), ($f('brand') ?: null), ($f('model') ?: null),
                ($f('purchase_date') ?: null), ($f('purchase_value') !== '' ? (float)$f('purchase_value') : null),
                ($f('warranty_expiry') ?: null), $status,
                ($f('notes') ?: null),
                $f('depreciation_method') ?: 'none',
                ($f('useful_life_years') !== '' ? (int)$f('useful_life_years') : null),
                ($f('salvage_value') !== '' ? (float)$f('salvage_value') : null),
                $_SESSION['g2_user']['id']
            ]);
            $aid = db()->lastInsertId();

            // Log
            db()->prepare("INSERT INTO asset_activity_log (asset_id,user_id,action,detail) VALUES (?,?,?,?)")
              ->execute([$aid, $_SESSION['g2_user']['id'], 'created', 'Asset created']);

            // Optional assignment
            $assign_to = (int)($_POST['assign_to'] ?? 0);
            if ($assign_to) {
                db()->prepare("INSERT INTO asset_assignments (asset_id,user_id,assigned_by) VALUES (?,?,?)")
                  ->execute([$aid, $assign_to, $_SESSION['g2_user']['id']]);
                db()->prepare("INSERT INTO asset_activity_log (asset_id,user_id,action,detail) VALUES (?,?,?,?)")
                  ->execute([$aid, $_SESSION['g2_user']['id'], 'assigned', 'Assigned on creation']);
            }

            header("Location: view.php?id=$aid&created=1"); exit;
        }
    }
}

// Auto-generate next tag
$last = db()->query("SELECT tag FROM assets ORDER BY id DESC LIMIT 1")->fetchColumn();
$next_num = 1;
if ($last && preg_match('/(\d+)$/', $last, $m)) $next_num = (int)$m[1] + 1;
$suggested_tag = 'G2-' . str_pad($next_num, 5, '0', STR_PAD_LEFT);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Asset — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>.form-card{max-width:700px}</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">Add Asset</span>
</div>
<div class="form-page-wrap">
<div class="form-card">
  <div class="form-header">
    <div class="fh-text"><h1>New Asset</h1><p>Register a new asset in the system</p></div>
    <div class="fh-accent">📦</div>
  </div>
  <div class="form-accent-bar"></div>

  <?php if ($error): ?>
  <div style="margin:16px 24px;padding:12px 16px;background:#fff5f5;border:1px solid #fca5a5;border-radius:8px;font-size:13px;color:#dc2626"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
  <div class="form-body">

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Identity</h2></div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Asset Name <span style="color:#FF3D33">*</span></label>
          <input type="text" name="name" required placeholder="e.g. Dell Laptop XPS 15" value="<?= htmlspecialchars($_POST['name']??'') ?>">
        </div>
        <div class="field">
          <label class="field-label">Asset Tag <span style="color:#FF3D33">*</span></label>
          <input type="text" name="tag" required placeholder="<?= $suggested_tag ?>" value="<?= htmlspecialchars($_POST['tag'] ?? $suggested_tag) ?>">
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Brand</label>
          <input type="text" name="brand" placeholder="e.g. Dell" value="<?= htmlspecialchars($_POST['brand']??'') ?>">
        </div>
        <div class="field">
          <label class="field-label">Model</label>
          <input type="text" name="model" placeholder="e.g. XPS 15 9520" value="<?= htmlspecialchars($_POST['model']??'') ?>">
        </div>
      </div>
      <div class="field" style="max-width:300px">
        <label class="field-label">Serial Number</label>
        <input type="text" name="serial_number" placeholder="Manufacturer serial" value="<?= htmlspecialchars($_POST['serial_number']??'') ?>">
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Classification</h2></div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Category</label>
          <select name="category_id">
            <option value="">— Select —</option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= ($_POST['category_id']??'')==$c['id']?'selected':'' ?>><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label class="field-label">Status</label>
          <select name="status">
            <?php foreach (['active'=>'Active','in_repair'=>'In Repair','retired'=>'Retired','disposed'=>'Disposed','lost'=>'Lost'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($_POST['status']??'active')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Location</label>
          <select name="location_id">
            <option value="">— Select —</option>
            <?php foreach ($locations as $l): ?><option value="<?= $l['id'] ?>" <?= ($_POST['location_id']??'')==$l['id']?'selected':'' ?>><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label class="field-label">Department</label>
          <select name="department_id">
            <option value="">— Select —</option>
            <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= ($_POST['department_id']??'')==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Purchase Info</h2></div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Purchase Date</label>
          <input type="date" name="purchase_date" value="<?= htmlspecialchars($_POST['purchase_date']??'') ?>">
        </div>
        <div class="field">
          <label class="field-label">Purchase Value (QAR)</label>
          <input type="number" name="purchase_value" step="0.01" placeholder="0.00" value="<?= htmlspecialchars($_POST['purchase_value']??'') ?>">
        </div>
      </div>
      <div class="field" style="max-width:240px">
        <label class="field-label">Warranty Expiry</label>
        <input type="date" name="warranty_expiry" value="<?= htmlspecialchars($_POST['warranty_expiry']??'') ?>">
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Assignment</h2></div>
      <div class="field" style="max-width:320px">
        <label class="field-label">Assign to Employee <span style="font-size:11px;color:#aaa">(optional)</span></label>
        <select name="assign_to">
          <option value="">— Unassigned —</option>
          <?php foreach ($users_list as $ul): ?><option value="<?= $ul['id'] ?>" <?= ($_POST['assign_to']??'')==$ul['id']?'selected':'' ?>><?= htmlspecialchars($ul['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Depreciation</h2></div>
      <div class="form-row">
        <div class="field">
          <label class="field-label">Method</label>
          <select name="depreciation_method">
            <option value="none" <?= ($_POST['depreciation_method']??'none')==='none'?'selected':'' ?>>None</option>
            <option value="straight_line" <?= ($_POST['depreciation_method']??'')==='straight_line'?'selected':'' ?>>Straight Line</option>
            <option value="double_declining" <?= ($_POST['depreciation_method']??'')==='double_declining'?'selected':'' ?>>Double Declining Balance</option>
          </select>
        </div>
        <div class="field">
          <label class="field-label">Useful Life (years)</label>
          <input type="number" name="useful_life_years" value="<?= htmlspecialchars($_POST['useful_life_years']??'') ?>" min="1" max="50" placeholder="e.g. 5">
        </div>
        <div class="field">
          <label class="field-label">Salvage Value (QAR)</label>
          <input type="number" name="salvage_value" value="<?= htmlspecialchars($_POST['salvage_value']??'') ?>" step="0.01" min="0" placeholder="0.00">
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Notes</h2></div>
      <div class="field">
        <textarea name="notes" rows="3" placeholder="Any additional notes…"><?= htmlspecialchars($_POST['notes']??'') ?></textarea>
      </div>
    </div>

  </div>
  <div class="form-footer">
    <button type="submit" class="submit-btn">Save Asset</button>
    <a href="list.php" style="margin-left:14px;font-size:13px;color:#aaa;text-decoration:none">Cancel</a>
  </div>
  </form>
</div>
</div>
</div>
</body>
</html>
