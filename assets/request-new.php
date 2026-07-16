<?php
session_start();
require '../config.php';
require_login();
require_can('assets');

$user = current_user();
$db   = db();

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_name    = trim(strip_tags($_POST['asset_name'] ?? ''));
    $category_id   = (int)($_POST['category_id'] ?? 0) ?: null;
    $quantity      = max(1, (int)($_POST['quantity'] ?? 1));
    $est_cost      = strlen(trim($_POST['est_cost'] ?? '')) ? (float)$_POST['est_cost'] : null;
    $urgency       = in_array($_POST['urgency'] ?? '', ['low','medium','high']) ? $_POST['urgency'] : 'medium';
    $justification = trim(strip_tags($_POST['justification'] ?? ''));

    if (!$asset_name || !$justification) {
        $error = 'Asset name and justification are required.';
    } else {
        $db->prepare("INSERT INTO asset_requests (requested_by,asset_name,category_id,quantity,est_cost,urgency,justification) VALUES (?,?,?,?,?,?,?)")
           ->execute([$user['id'], $asset_name, $category_id, $quantity, $est_cost, $urgency, $justification]);

        // Notify finance/IT admins
        require_once '../mailer.php';
        $admins = $db->query("SELECT name, email FROM users WHERE role IN ('finance_admin','it_admin') AND email != ''")->fetchAll();
        foreach ($admins as $a) {
            $subj = "New Asset Request — {$asset_name} (by {$user['name']})";
            $body = mail_template("New Asset Request", "
                <p><strong>{$user['name']}</strong> has submitted a new asset request.</p>
                <div class='info-box'><strong>Asset</strong> " . htmlspecialchars($asset_name) . "</div>
                <div class='info-box'><strong>Quantity</strong> {$quantity}</div>
                <div class='info-box'><strong>Urgency</strong> " . ucfirst($urgency) . "</div>" .
                ($est_cost ? "<div class='info-box'><strong>Estimated Cost</strong> QAR " . number_format($est_cost, 2) . "</div>" : "") . "
                <div class='info-box'><strong>Justification</strong> " . htmlspecialchars($justification) . "</div>
                <a class='btn' href='https://g2tools.greydoha.com/assets/requests.php'>Review in G2 Tools</a>
            ");
            send_mail(['email' => $a['email'], 'name' => $a['name']], $subj, $body);
        }
        $success = true;
    }
}

$categories = $db->query("SELECT id, name, icon FROM asset_categories ORDER BY name")->fetchAll();
$urgency_labels = ['low' => 'Low — within a month', 'medium' => 'Medium — within a week', 'high' => 'High — urgent / blocking'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Asset Request — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="requests.php">← Asset Requests</a>
  <span class="topbar-title">New Asset Request</span>
</div>
<div class="form-page-wrap">
<div class="form-card" style="max-width:580px">

<?php if ($success): ?>
<div style="padding:48px 40px;text-align:center">
  <div style="font-size:48px;margin-bottom:16px">📋</div>
  <div style="font-size:20px;font-weight:800;margin-bottom:8px">Request Submitted</div>
  <div style="font-size:14px;color:#888;margin-bottom:28px">Finance admin has been notified and will review your request.</div>
  <div style="display:flex;gap:10px;justify-content:center">
    <a href="request-new.php" style="padding:10px 20px;background:#FF3D33;color:#fff;border-radius:30px;font-size:13px;font-weight:700;text-decoration:none">New Request</a>
    <a href="requests.php" style="padding:10px 20px;background:#f6f7f9;color:#555;border-radius:30px;font-size:13px;font-weight:600;text-decoration:none">My Requests</a>
  </div>
</div>
<?php else: ?>

<div class="form-header">
  <div class="fh-text"><h1>New Asset Request</h1><p>Request a new asset for procurement by Finance</p></div>
  <div class="fh-accent">🖥️</div>
</div>
<div class="form-accent-bar"></div>

<?php if ($error): ?>
<div style="margin:16px 24px;padding:12px 16px;background:#fff5f5;border:1px solid #fca5a5;border-radius:8px;font-size:13px;color:#dc2626"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
<div class="form-body">
  <div class="section">

    <div class="field">
      <label class="field-label">Asset Name / Description <span style="color:#FF3D33">*</span></label>
      <input type="text" name="asset_name" required placeholder="e.g. Dell XPS 15 Laptop" value="<?= htmlspecialchars($_POST['asset_name'] ?? '') ?>">
    </div>

    <div class="field">
      <label class="field-label">Category</label>
      <select name="category_id">
        <option value="">— Select category —</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= ($_POST['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
          <?= $c['icon'] ? $c['icon'] . ' ' : '' ?><?= htmlspecialchars($c['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="field">
        <label class="field-label">Quantity <span style="color:#FF3D33">*</span></label>
        <input type="number" name="quantity" min="1" value="<?= (int)($_POST['quantity'] ?? 1) ?>" required>
      </div>
      <div class="field">
        <label class="field-label">Estimated Cost (QAR)</label>
        <input type="number" name="est_cost" min="0" step="0.01" placeholder="0.00" value="<?= htmlspecialchars($_POST['est_cost'] ?? '') ?>">
      </div>
    </div>

    <div class="field">
      <label class="field-label">Urgency <span style="color:#FF3D33">*</span></label>
      <select name="urgency" required>
        <?php foreach ($urgency_labels as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= ($_POST['urgency'] ?? 'medium') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label class="field-label">Justification <span style="color:#FF3D33">*</span></label>
      <textarea name="justification" rows="4" required placeholder="Why is this asset needed? What will it be used for?"><?= htmlspecialchars($_POST['justification'] ?? '') ?></textarea>
    </div>

  </div>
</div>
<div class="form-footer">
  <button type="submit" class="submit-btn">Submit Request</button>
</div>
</form>
<?php endif; ?>
</div>
</div>
</div>
</body>
</html>
