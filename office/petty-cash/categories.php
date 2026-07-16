<?php
session_start();
require '../../config.php';
require_login();
if (!is_admin()) { header('Location: index.php'); exit; }

$office = $_GET['office'] ?? 'doha';
if (!array_key_exists($office, OFFICES)) $office = 'doha';

$msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim(strip_tags($_POST['name'] ?? ''));
        if ($name) {
            $max = db()->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM petty_cash_categories WHERE office=?");
            $max->execute([$office]); $next = (int)$max->fetchColumn();
            db()->prepare("INSERT INTO petty_cash_categories (office,name,sort_order) VALUES (?,?,?)")->execute([$office, $name, $next]);
            $msg = 'ok|Category added.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { db()->prepare("DELETE FROM petty_cash_categories WHERE id=? AND office=?")->execute([$id, $office]); }
        $msg = 'ok|Category removed.';
    } elseif ($action === 'rename') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim(strip_tags($_POST['name'] ?? ''));
        if ($id && $name) { db()->prepare("UPDATE petty_cash_categories SET name=? WHERE id=? AND office=?")->execute([$name, $id, $office]); }
        $msg = 'ok|Category renamed.';
    } elseif ($action === 'move') {
        $id  = (int)($_POST['id'] ?? 0);
        $dir = $_POST['dir'] ?? '';
        if ($id && in_array($dir, ['up','down'])) {
            $all = db()->prepare("SELECT id,sort_order FROM petty_cash_categories WHERE office=? ORDER BY sort_order,id");
            $all->execute([$office]); $cats = $all->fetchAll();
            foreach ($cats as $i => $c) {
                if ($c['id'] == $id) {
                    $swap_i = $dir==='up' ? $i-1 : $i+1;
                    if (isset($cats[$swap_i])) {
                        db()->prepare("UPDATE petty_cash_categories SET sort_order=? WHERE id=?")->execute([$cats[$swap_i]['sort_order'], $id]);
                        db()->prepare("UPDATE petty_cash_categories SET sort_order=? WHERE id=?")->execute([$c['sort_order'], $cats[$swap_i]['id']]);
                    }
                    break;
                }
            }
        }
    }
    header('Location: categories.php?office='.$office.($msg?'&msg='.urlencode($msg):'')); exit;
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];
[$mt,$mm] = $msg ? explode('|',$msg,2) : ['',''];

$cats = db()->prepare("SELECT * FROM petty_cash_categories WHERE office=? ORDER BY sort_order,name");
$cats->execute([$office]); $cats = $cats->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Petty Cash Categories — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
.cat-list{background:#fff;border:1px solid #eef0f3;border-radius:14px;overflow:hidden;margin-bottom:24px}
.cat-row{display:flex;align-items:center;gap:10px;padding:12px 18px;border-bottom:1px solid #f5f6f8}
.cat-row:last-child{border-bottom:none}
.cat-name{flex:1;font-size:14px;color:#1a1a1a;font-weight:500}
.cat-actions{display:flex;gap:6px}
.act-btn{padding:4px 10px;border-radius:6px;border:1.5px solid #e8eaee;background:#fff;font-size:12px;font-weight:600;color:#666;cursor:pointer}
.act-btn:hover{border-color:#aaa;color:#333}
.act-btn.danger{border-color:#fca5a5;color:#dc2626}.act-btn.danger:hover{background:#fee2e2}
.add-row{display:flex;gap:8px;padding:16px}
.add-row input{flex:1;padding:9px 12px;border:1.5px solid #e8eaee;border-radius:8px;font-size:13px;outline:none}
.add-row input:focus{border-color:#FF3D33}
.add-btn{padding:9px 18px;background:#FF3D33;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer}
.empty{padding:32px;text-align:center;color:#ccc;font-size:13px}
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php?office=<?= $office ?>">← Petty Cash</a>
  <span class="topbar-title">Categories</span>
</div>
<div class="form-page-wrap">
<div class="form-card" style="max-width:560px">
  <div class="form-header">
    <div class="fh-text"><h1>Petty Cash Categories</h1><p>Manage expense categories for each office</p></div>
    <div class="fh-accent">🗂</div>
  </div>
  <div class="form-accent-bar"></div>

  <!-- Office tabs -->
  <div style="display:flex;gap:8px;padding:16px 24px 0">
    <?php foreach (OFFICES as $ok => $ov): ?>
    <a href="categories.php?office=<?= $ok ?>" style="padding:7px 16px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none;
      <?= $office===$ok ? 'background:#FF3D33;color:#fff' : 'background:#f5f6f8;color:#666' ?>"><?= $ov['flag'] ?> <?= $ov['label'] ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($mm): ?>
  <div style="margin:14px 24px 0;padding:10px 14px;border-radius:8px;font-size:13px;
    <?= $mt==='ok'?'background:#f0fdf4;border:1px solid #bbf7d0;color:#166534':'background:#fff5f5;border:1px solid #fca5a5;color:#dc2626' ?>"><?= htmlspecialchars($mm) ?></div>
  <?php endif; ?>

  <div class="cat-list" style="margin:16px 24px 0">
    <?php if (empty($cats)): ?>
    <div class="empty">No categories yet. Add one below.</div>
    <?php else: foreach ($cats as $i => $c): ?>
    <div class="cat-row">
      <div class="cat-name"><?= htmlspecialchars($c['name']) ?></div>
      <div class="cat-actions">
        <?php if ($i > 0): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="dir" value="up">
          <button class="act-btn" type="submit" title="Move up">↑</button>
        </form>
        <?php endif; ?>
        <?php if ($i < count($cats)-1): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="dir" value="down">
          <button class="act-btn" type="submit" title="Move down">↓</button>
        </form>
        <?php endif; ?>
        <button class="act-btn" onclick="renameCategory(<?= $c['id'] ?>,'<?= addslashes($c['name']) ?>')" type="button">Rename</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('Remove this category?')">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button class="act-btn danger" type="submit">Remove</button>
        </form>
      </div>
    </div>
    <?php endforeach; endif; ?>
    <div class="add-row">
      <form method="POST" style="display:contents">
        <input type="hidden" name="action" value="add">
        <input type="text" name="name" placeholder="New category name…" required>
        <button class="add-btn" type="submit">Add</button>
      </form>
    </div>
  </div>

  <!-- Hidden rename form -->
  <form method="POST" id="renameForm" style="display:none">
    <input type="hidden" name="action" value="rename">
    <input type="hidden" name="id" id="rename_id">
    <input type="hidden" name="name" id="rename_name">
  </form>
</div>
</div>
</div>
<script>
function renameCategory(id, current){
  const name = prompt('Rename category:', current);
  if (name && name.trim() && name !== current) {
    document.getElementById('rename_id').value = id;
    document.getElementById('rename_name').value = name.trim();
    document.getElementById('renameForm').submit();
  }
}
</script>
</body>
</html>
