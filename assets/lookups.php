<?php
session_start();
require '../config.php';
require_login();
if (!is_it_admin()) { header('Location: /assets/'); exit; }

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type   = $_POST['type'] ?? '';
    $action = $_POST['action'] ?? 'add';
    $name   = trim(strip_tags($_POST['name'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'add' && $name) {
        if ($type === 'category') {
            db()->prepare("INSERT INTO asset_categories (name,icon) VALUES (?,?)")->execute([$name, $_POST['icon']??'📦']);
        } elseif ($type === 'location') {
            db()->prepare("INSERT INTO asset_locations (name,office) VALUES (?,?)")->execute([$name, $_POST['office']??null]);
        } elseif ($type === 'department') {
            db()->prepare("INSERT INTO asset_departments (name) VALUES (?)")->execute([$name]);
        }
        $_SESSION['flash'] = ['type'=>'ok','msg'=>ucfirst($type)." '$name' added."];
    } elseif ($action === 'delete' && $id) {
        $table = ['category'=>'asset_categories','location'=>'asset_locations','department'=>'asset_departments'][$type] ?? null;
        if ($table) db()->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
        $_SESSION['flash'] = ['type'=>'ok','msg'=>ucfirst($type).' removed.'];
    }
    header('Location: lookups.php'); exit;
}

$categories  = db()->query("SELECT * FROM asset_categories ORDER BY name")->fetchAll();
$locations   = db()->query("SELECT * FROM asset_locations ORDER BY name")->fetchAll();
$departments = db()->query("SELECT * FROM asset_departments ORDER BY name")->fetchAll();
$icons = ['📦','💻','🖥️','📱','🖨️','🔌','🪑','🚗','📷','🔧','🖱️','⌨️','📺','🔋'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Asset Lookups — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
*,*::before,*::after{box-sizing:border-box}
.pw{padding:30px 36px 60px;max-width:900px}
.ph{display:flex;align-items:center;margin-bottom:24px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;overflow:hidden}
.panel-head{padding:14px 18px;border-bottom:1px solid #f0f1f3;font-size:13px;font-weight:800;color:#1a1a1a}
.items{padding:4px 0}
.item-row{display:flex;align-items:center;justify-content:space-between;padding:8px 18px;border-bottom:1px solid #f8f9fa;font-size:13px;color:#444}
.item-row:last-child{border-bottom:none}
.del-btn{background:none;border:none;color:#fca5a5;font-size:16px;cursor:pointer;padding:0 4px}
.del-btn:hover{color:#dc2626}
.add-form{padding:12px 18px;border-top:1px solid #f0f1f3;display:flex;gap:8px;flex-wrap:wrap}
.add-form input,.add-form select{flex:1;min-width:80px;padding:7px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:12px;font-family:inherit;outline:none}
.add-form input:focus,.add-form select:focus{border-color:#FF3D33}
.add-form button{padding:7px 14px;background:#FF3D33;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap}
.flash{padding:11px 16px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:16px;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">Categories & Locations</span>
</div>
<div class="pw">
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <div class="ph"><h1>Asset Lookups</h1></div>

  <div class="grid3">

    <!-- Categories -->
    <div class="panel">
      <div class="panel-head">🏷️ Categories</div>
      <div class="items">
        <?php foreach ($categories as $c): ?>
        <div class="item-row">
          <span><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></span>
          <form method="POST"><input type="hidden" name="type" value="category"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button class="del-btn" type="submit" title="Delete" onclick="return confirm('Delete this category?')">×</button></form>
        </div>
        <?php endforeach; ?>
      </div>
      <form class="add-form" method="POST">
        <input type="hidden" name="type" value="category"><input type="hidden" name="action" value="add">
        <select name="icon"><?php foreach($icons as $ic): ?><option value="<?= $ic ?>"><?= $ic ?></option><?php endforeach; ?></select>
        <input type="text" name="name" placeholder="Category name" required>
        <button type="submit">Add</button>
      </form>
    </div>

    <!-- Locations -->
    <div class="panel">
      <div class="panel-head">📍 Locations</div>
      <div class="items">
        <?php foreach ($locations as $l): ?>
        <div class="item-row">
          <span><?= htmlspecialchars($l['name']) ?><?= $l['office'] ? ' <span style="color:#aaa;font-size:11px">('.htmlspecialchars($l['office']).')</span>' : '' ?></span>
          <form method="POST"><input type="hidden" name="type" value="location"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $l['id'] ?>">
          <button class="del-btn" type="submit" onclick="return confirm('Delete?')">×</button></form>
        </div>
        <?php endforeach; ?>
      </div>
      <form class="add-form" method="POST">
        <input type="hidden" name="type" value="location"><input type="hidden" name="action" value="add">
        <input type="text" name="name" placeholder="Location name" required>
        <select name="office"><option value="">No office</option><?php foreach(OFFICES as $k=>$o): ?><option value="<?= $k ?>"><?= $o['label'] ?></option><?php endforeach; ?></select>
        <button type="submit">Add</button>
      </form>
    </div>

    <!-- Departments -->
    <div class="panel">
      <div class="panel-head">🏢 Departments</div>
      <div class="items">
        <?php foreach ($departments as $d): ?>
        <div class="item-row">
          <span><?= htmlspecialchars($d['name']) ?></span>
          <form method="POST"><input type="hidden" name="type" value="department"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>">
          <button class="del-btn" type="submit" onclick="return confirm('Delete?')">×</button></form>
        </div>
        <?php endforeach; ?>
      </div>
      <form class="add-form" method="POST">
        <input type="hidden" name="type" value="department"><input type="hidden" name="action" value="add">
        <input type="text" name="name" placeholder="Department name" required>
        <button type="submit">Add</button>
      </form>
    </div>

  </div>
</div>
</div>
</body>
</html>
