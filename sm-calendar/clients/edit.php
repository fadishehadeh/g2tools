<?php
require_once __DIR__ . '/../lib/bootstrap.php';
sm_require_staff();
$staff = sm_current_staff();

$id = (int)($_GET['id'] ?? 0);
$stmt = sm_db()->prepare("SELECT * FROM clients WHERE id=?");
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { header('Location: ' . SM_BASE_URL . '/clients/'); exit; }

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(strip_tags($_POST['name'] ?? ''));
    $email = trim(strip_tags($_POST['email'] ?? ''));
    $manager_id = (int)($_POST['account_manager_id'] ?? 0) ?: null;
    $platforms = trim(strip_tags($_POST['connected_platforms'] ?? ''));

    if (!$name) {
        $msg = 'err|Client name is required.';
    } else {
        $logo_name = $client['logo'];
        if (!empty($_FILES['logo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg','gif','svg'], true) && $_FILES['logo']['size'] <= 3*1024*1024) {
                $fname = 'logo_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], SM_LOGO_PATH . $fname)) {
                    if ($logo_name && file_exists(SM_LOGO_PATH . $logo_name)) @unlink(SM_LOGO_PATH . $logo_name);
                    $logo_name = $fname;
                }
            }
        }
        sm_db()->prepare(
            "UPDATE clients SET name=?,email=?,logo=?,account_manager_id=?,connected_platforms=? WHERE id=?"
        )->execute([$name, $email ?: null, $logo_name, $manager_id, $platforms ?: null, $id]);
        $msg = 'ok|Client updated.';

        $stmt->execute([$id]);
        $client = $stmt->fetch();
    }
}
[$mt,$mm] = $msg ? explode('|',$msg,2) : ['',''];

$managers = sm_db()->query("SELECT id,name FROM " . g2_users_table() . " WHERE status='active' ORDER BY name")->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Client — G2 SM Calendar Tool</title>
<link rel="stylesheet" href="/sm-calendar/sm.css">
<style>
  .panel { background:#fff; border:1px solid #e8eaee; border-radius:14px; padding:24px; max-width:560px; }
  .current-logo { width:64px; height:64px; border-radius:12px; background:#f6f7f9; border:1px solid #e8eaee;
                  display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:14px; }
  .current-logo img { width:100%; height:100%; object-fit:contain; }
</style>
</head>
<body>
<div class="sm-shell">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="sm-main">
    <div class="sm-topbar">
      <h1>Edit Client</h1>
      <a href="<?= SM_BASE_URL ?>/clients/" style="font-size:13px;color:#999;text-decoration:none">← Back to Clients</a>
    </div>

    <?php if ($mm): ?>
    <div class="sm-msg <?= $mt==='ok'?'sm-msg-ok':'sm-msg-err' ?>" style="max-width:560px"><?= htmlspecialchars($mm) ?></div>
    <?php endif; ?>

    <div class="panel">
      <?php if ($client['logo']): ?>
      <div class="current-logo"><img src="/sm-calendar/storage/logos/<?= htmlspecialchars($client['logo']) ?>"></div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data">
        <div class="sm-field"><label>Client Name</label><input type="text" name="name" value="<?= htmlspecialchars($client['name']) ?>" required></div>
        <div class="sm-field"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($client['email'] ?? '') ?>"></div>
        <div class="sm-field">
          <label>Account Manager</label>
          <select name="account_manager_id">
            <option value="">— None —</option>
            <?php foreach ($managers as $m): ?>
            <option value="<?= $m['id'] ?>" <?= (int)$client['account_manager_id']===(int)$m['id']?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sm-field"><label>Connected Platforms</label><input type="text" name="connected_platforms" value="<?= htmlspecialchars($client['connected_platforms'] ?? '') ?>" placeholder="Facebook, Instagram"></div>
        <div class="sm-field"><label>Replace Logo</label><input type="file" name="logo" accept="image/*"></div>
        <button type="submit" class="sm-btn-primary">Save Changes</button>
      </form>
    </div>
  </main>
</div>
</body>
</html>
