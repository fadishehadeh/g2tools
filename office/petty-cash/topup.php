<?php
session_start();
require '../../config.php';
require_admin();
$user = current_user();

$office = $_GET['office'] ?? $_POST['office'] ?? 'doha';
if (!array_key_exists($office, OFFICES)) $office = 'doha';

$float_row = db()->prepare("SELECT balance FROM petty_cash_float WHERE office=?");
$float_row->execute([$office]);
$float = (float)$float_row->fetchColumn();
$cur   = OFFICES[$office]['currency'];
$msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $office  = $_POST['office'] ?? 'doha';
    $amount  = (float)$_POST['amount'];
    $ref     = trim(strip_tags($_POST['reference'] ?? ''));
    $notes   = trim(strip_tags($_POST['notes'] ?? ''));
    if ($amount <= 0) {
        $msg = 'err|Amount must be greater than zero.';
    } else {
        $float_row->execute([$office]);
        $float   = (float)$float_row->fetchColumn();
        $new_bal = $float + $amount;
        db()->prepare("UPDATE petty_cash_float SET balance=? WHERE office=?")->execute([$new_bal, $office]);
        db()->prepare("INSERT INTO petty_cash_log (type,office,amount,balance_after,reference,notes,created_by) VALUES ('topup',?,?,?,?,?,?)")
           ->execute([$office, $amount, $new_bal, $ref, $notes, $user['id']]);
        $float = $new_bal;
        $msg   = 'ok|Float topped up. New balance: ' . $cur . ' ' . number_format($new_bal,2);
    }
}
[$mt,$mm] = $msg ? explode('|',$msg,2) : ['',''];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Top Up Float — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php?office=<?= $office ?>">Petty Cash</a>
  <span class="topbar-title">Top Up Float</span>
</div>
<div class="form-page-wrap">
<div class="form-card" style="max-width:480px">
  <div class="form-header">
    <div class="fh-text">
      <h1>Top Up Float</h1>
      <p><?= OFFICES[$office]['label'] ?> — current balance: <strong><?= $cur ?> <?= number_format($float,2) ?></strong></p>
    </div>
    <div class="fh-accent">💰</div>
  </div>
  <div class="form-accent-bar"></div>
  <?php if ($mm): ?>
  <div style="margin:16px 24px;padding:12px 16px;border-radius:8px;font-size:13px;
    <?= $mt==='ok'?'background:#f0fdf4;border:1px solid #bbf7d0;color:#166534':'background:#fff5f5;border:1px solid #fca5a5;color:#dc2626' ?>">
    <?= htmlspecialchars($mm) ?>
  </div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-body">
      <div class="section">
        <input type="hidden" name="office" value="<?= $office ?>">
        <div class="field"><label class="field-label">Amount (<?= $cur ?>) <span style="color:#FF3D33">*</span></label>
          <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" required></div>
        <div class="field"><label class="field-label">Reference</label>
          <input type="text" name="reference" placeholder="e.g. Cheque no. / Transfer ref"></div>
        <div class="field"><label class="field-label">Notes</label>
          <textarea name="notes" rows="2" placeholder="Optional notes…"></textarea></div>
      </div>
    </div>
    <div class="form-footer">
      <button type="submit" class="submit-btn">＋ Add to Float</button>
    </div>
  </form>
</div>
</div>
</div>
</body>
</html>
