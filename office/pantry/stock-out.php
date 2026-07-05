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

    $item = null;
    foreach ($items as $it) { if ($it['id'] === $item_id) { $item = $it; break; } }

    if (!$item || $qty <= 0) {
        $msg = 'err|Please select an item and enter a valid quantity.';
    } elseif ($qty > $item['current_stock']) {
        $msg = 'err|Quantity exceeds current stock (' . number_format($item['current_stock'],1) . ' ' . $item['unit'] . ').';
    } else {
        $new_stock = $item['current_stock'] - $qty;
        db()->prepare("UPDATE pantry_items SET current_stock=? WHERE id=?")->execute([$new_stock, $item_id]);
        db()->prepare("INSERT INTO pantry_movements (item_id,type,quantity,notes,created_by) VALUES (?,'out',?,?,?)")->execute([$item_id,$qty,$notes,$user['id']]);

        // Check if now below par — send alert if not sent in last 24h
        if ($new_stock < $item['par_level']) {
            $last = db()->prepare("SELECT sent_at FROM pantry_alert_log WHERE item_id=? ORDER BY sent_at DESC LIMIT 1");
            $last->execute([$item_id]);
            $last_sent = $last->fetchColumn();
            $should_alert = !$last_sent || (time() - strtotime($last_sent)) > 86400;

            if ($should_alert) {
                $admins = db()->query("SELECT email FROM users WHERE role IN ('finance_admin','it_admin') AND email != ''")->fetchAll();
                $admin_emails = array_column($admins, 'email');
                if ($admin_emails) {
                    $status = $new_stock <= 0 ? 'OUT OF STOCK' : 'LOW STOCK';
                    $subject = "Pantry Alert: {$item['name']} is {$status}";
                    $body    = "Pantry stock alert from G2 Tools.\n\n"
                             . "Item:          {$item['name']}\n"
                             . "Current Stock: " . number_format($new_stock,1) . " {$item['unit']}\n"
                             . "Par Level:     " . number_format($item['par_level'],1) . " {$item['unit']}\n"
                             . "Status:        {$status}\n\n"
                             . "Please arrange for restocking (recommended reorder: " . number_format($item['reorder_qty'],1) . " {$item['unit']}).\n";
                    $headers = "From: G2 Tools <noreply@g2group.com>\r\n";
                    mail(implode(', ', $admin_emails), $subject, $body, $headers);
                    db()->prepare("INSERT INTO pantry_alert_log (item_id) VALUES (?)")->execute([$item_id]);
                }
            }
        }
        $msg = 'ok|Stock updated. ' . number_format($new_stock,1) . ' ' . $item['unit'] . ' remaining.';
        $preselect = 0;
    }
}
[$mt,$mm] = $msg ? explode('|',$msg,2) : ['',''];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Stock Out — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">Pantry</a>
  <span class="topbar-title">Stock Out</span>
</div>
<div class="form-page-wrap">
<div class="form-card" style="max-width:480px">
  <div class="form-header">
    <div class="fh-text"><h1>Stock Out</h1><p>Log items consumed from the pantry</p></div>
    <div class="fh-accent">🍵</div>
  </div>
  <div class="form-accent-bar" style="background:#f59e0b"></div>
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
        <label class="field-label">Quantity Used <span style="color:#FF3D33">*</span></label>
        <input type="number" name="quantity" step="0.1" min="0.1" placeholder="0" required>
      </div>
      <div class="field">
        <label class="field-label">Notes</label>
        <input type="text" name="notes" placeholder="e.g. Weekly kitchen use">
      </div>
    </div>
  </div>
  <div class="form-footer">
    <button type="submit" class="submit-btn" style="background:#f59e0b">− Log Consumption</button>
  </div>
  </form>
</div>
</div>
</div>
</body>
</html>
