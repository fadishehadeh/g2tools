<?php
session_start();
require '../../config.php';
require_admin();
$user = current_user();

$id   = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT r.*, u.name as uname, u.email as uemail FROM petty_cash_requests r JOIN users u ON u.id=r.user_id WHERE r.id=?");
$stmt->execute([$id]);
$req  = $stmt->fetch();
if (!$req) { header('Location: index.php'); exit; }

$office = $req['office'];
$cur    = OFFICES[$office]['currency'] ?? 'QAR';

$float_row = db()->prepare("SELECT balance FROM petty_cash_float WHERE office=?");
$float_row->execute([$office]);
$float = (float)$float_row->fetchColumn();

$msg = ''; $msg_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $note   = trim(strip_tags($_POST['admin_note'] ?? ''));

    if ($action === 'approve' && $req['status'] === 'pending') {
        db()->prepare("UPDATE petty_cash_requests SET status='approved',admin_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")
           ->execute([$note, $user['id'], $id]);
        if ($req['uemail'])
            @mail($req['uemail'], 'Petty Cash Entry Approved',
                "Hi {$req['uname']},\n\nYour entry for {$cur} " . number_format($req['amount'],2) . " ({$req['category']}) was approved.\nYou will be notified when cash is ready.\n\nG2 Tools",
                "From: G2 Tools <noreply@g2group.com>\r\n");
        $msg = 'Entry approved.';

    } elseif ($action === 'reject' && $req['status'] === 'pending') {
        db()->prepare("UPDATE petty_cash_requests SET status='rejected',admin_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")
           ->execute([$note, $user['id'], $id]);
        if ($req['uemail'])
            @mail($req['uemail'], 'Petty Cash Entry Declined',
                "Hi {$req['uname']},\n\nYour entry for {$cur} " . number_format($req['amount'],2) . " was declined.\nReason: {$note}\n\nG2 Tools",
                "From: G2 Tools <noreply@g2group.com>\r\n");
        $msg = 'Entry rejected.';

    } elseif ($action === 'pay' && $req['status'] === 'approved') {
        if ($float < $req['amount']) {
            $msg = 'Insufficient float. Top up the ' . OFFICES[$office]['label'] . ' float first.';
            $msg_type = 'err';
        } else {
            $new_bal = $float - $req['amount'];
            db()->prepare("UPDATE petty_cash_float SET balance=? WHERE office=?")->execute([$new_bal, $office]);
            db()->prepare("UPDATE petty_cash_requests SET status='paid',paid_at=NOW() WHERE id=?")->execute([$id]);
            db()->prepare("INSERT INTO petty_cash_log (type,office,amount,balance_after,notes,request_id,created_by) VALUES ('disbursement',?,?,?,?,?,?)")
               ->execute([$office, $req['amount'], $new_bal, "Paid to {$req['uname']}: {$req['description']}", $id, $user['id']]);
            if ($req['uemail'])
                @mail($req['uemail'], 'Petty Cash Ready for Collection',
                    "Hi {$req['uname']},\n\nYour {$cur} " . number_format($req['amount'],2) . " petty cash is ready for collection.\n\nG2 Tools",
                    "From: G2 Tools <noreply@g2group.com>\r\n");
            $float = $new_bal;
            $msg = 'Payment recorded. Float updated.';
        }
    }
    $stmt->execute([$id]);
    $req = array_merge($stmt->fetch(), ['uname'=>$req['uname'],'uemail'=>$req['uemail']]);
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Review Entry — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
  .rw { max-width:600px; margin:0 auto; padding:28px 24px 80px; }
  .detail-card { background:#fff; border:1px solid #eef0f3; border-radius:14px; padding:26px; margin-bottom:18px; }
  .dl { display:grid; grid-template-columns:140px 1fr; gap:9px 16px; font-size:13px; }
  .dl dt { color:#bbb; font-weight:600; }
  .dl dd { color:#1a1a1a; font-weight:500; margin:0; }
  .badge { display:inline-flex; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
  .badge-pending  { background:#fef9c3; color:#854d0e; }
  .badge-approved { background:#dcfce7; color:#166534; }
  .badge-rejected { background:#fee2e2; color:#991b1b; }
  .badge-paid     { background:#dbeafe; color:#1e40af; }
  .ar { display:flex; gap:10px; flex-wrap:wrap; }
  .btn-green { padding:10px 20px; background:#16a34a; color:#fff; border:none; border-radius:30px; font-size:13px; font-weight:700; cursor:pointer; }
  .btn-red   { padding:10px 20px; background:#FF3D33; color:#fff; border:none; border-radius:30px; font-size:13px; font-weight:700; cursor:pointer; }
  .btn-blue  { padding:10px 20px; background:#2563eb; color:#fff; border:none; border-radius:30px; font-size:13px; font-weight:700; cursor:pointer; }
  .btn-back  { padding:10px 20px; background:#f6f7f9; color:#555; border:1.5px solid #eef0f3; border-radius:30px; font-size:13px; font-weight:600; text-decoration:none; }
  .msg-ok  { padding:12px 16px; border-radius:8px; background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; font-size:13px; margin-bottom:14px; }
  .msg-err { padding:12px 16px; border-radius:8px; background:#fff5f5; border:1px solid #fca5a5; color:#dc2626; font-size:13px; margin-bottom:14px; }
  .float-bar { background:#f8f9fb; border:1px solid #eef0f3; border-radius:10px; padding:13px 18px;
               display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; font-size:13px; }
  .float-bar span { color:#aaa; }
  .float-bar strong { font-size:15px; font-weight:800; }
  .office-chip { display:inline-flex; align-items:center; gap:6px; background:#f5f6f8;
                 border-radius:20px; padding:4px 12px; font-size:12px; color:#555; font-weight:600; margin-bottom:16px; }
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php?office=<?= $office ?>">Petty Cash</a>
  <span class="topbar-title">Review Entry #<?= $id ?></span>
</div>
<div class="rw">

  <?php if ($msg): ?>
  <div class="msg-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="office-chip">
    <?= $office === 'doha' ? '🇶🇦' : '🇱🇧' ?> <?= OFFICES[$office]['label'] ?>
  </div>

  <div class="float-bar">
    <span>Float Balance — <?= OFFICES[$office]['label'] ?></span>
    <strong style="color:<?= $float < ($office==='doha'?500:200) ? '#FF3D33' : '#16a34a' ?>">
      <?= $cur ?> <?= number_format($float,2) ?>
    </strong>
  </div>

  <div class="detail-card">
    <dl class="dl">
      <dt>Staff</dt>       <dd><?= htmlspecialchars($req['uname']) ?></dd>
      <dt>Amount</dt>      <dd style="font-size:20px;font-weight:900"><?= $cur ?> <?= number_format($req['amount'],2) ?></dd>
      <dt>Category</dt>    <dd><?= htmlspecialchars($req['category']) ?></dd>
      <dt>Description</dt> <dd><?= nl2br(htmlspecialchars($req['description'])) ?></dd>
      <dt>Status</dt>      <dd><span class="badge badge-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span></dd>
      <dt>Requested</dt>   <dd><?= date('d M Y H:i', strtotime($req['created_at'])) ?></dd>
      <?php if ($req['receipt']): ?>
      <dt>Receipt</dt>     <dd><a href="receipt.php?f=<?= urlencode($req['receipt']) ?>" target="_blank" style="color:#FF3D33;font-weight:600">View Receipt ↗</a></dd>
      <?php endif; ?>
      <?php if ($req['admin_note']): ?>
      <dt>Admin Note</dt>  <dd><?= nl2br(htmlspecialchars($req['admin_note'])) ?></dd>
      <?php endif; ?>
    </dl>
  </div>

  <?php if ($req['status'] === 'pending'): ?>
  <div class="detail-card">
    <form method="POST">
      <div class="field" style="margin-bottom:16px">
        <label class="field-label">Note to requester <span style="font-size:11px;color:#aaa">(required for rejection)</span></label>
        <textarea name="admin_note" rows="2" placeholder="Optional note…"></textarea>
      </div>
      <div class="ar">
        <button type="submit" name="action" value="approve" class="btn-green">✓ Approve</button>
        <button type="submit" name="action" value="reject"  class="btn-red">✗ Reject</button>
        <a href="index.php?office=<?= $office ?>" class="btn-back">Back</a>
      </div>
    </form>
  </div>
  <?php elseif ($req['status'] === 'approved'): ?>
  <div class="detail-card">
    <p style="font-size:13px;color:#555;margin-bottom:16px">Approved. Mark as paid once you hand over the cash.</p>
    <form method="POST">
      <div class="ar">
        <button type="submit" name="action" value="pay" class="btn-blue">💵 Mark as Paid</button>
        <a href="index.php?office=<?= $office ?>" class="btn-back">Back</a>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="ar"><a href="index.php?office=<?= $office ?>" class="btn-back">← Back</a></div>
  <?php endif; ?>

</div>
</div>
</body>
</html>
