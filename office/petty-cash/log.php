<?php
session_start();
require '../../config.php';
require_admin();

$office = $_GET['office'] ?? 'doha';
if (!array_key_exists($office, OFFICES)) $office = 'doha';

$float_row = db()->prepare("SELECT balance FROM petty_cash_float WHERE office=?");
$float_row->execute([$office]);
$float = (float)$float_row->fetchColumn();
$cur   = OFFICES[$office]['currency'];

$stmt = db()->prepare("SELECT l.*, u.name as uname FROM petty_cash_log l JOIN users u ON u.id=l.created_by WHERE l.office=? ORDER BY l.created_at DESC LIMIT 100");
$stmt->execute([$office]);
$logs = $stmt->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Float Log — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
  .log-wrap { padding:28px 40px 80px; max-width:900px; }
  .office-tabs { display:flex; gap:8px; margin-bottom:24px; }
  .otab { padding:8px 18px; border-radius:20px; border:1.5px solid #eef0f3; background:#fff;
          text-decoration:none; color:#666; font-size:13px; font-weight:600; transition:all .15s; }
  .otab.active { border-color:#FF3D33; color:#FF3D33; background:#fff8f8; }
  .log-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #eef0f3; border-radius:14px; overflow:hidden; }
  .log-table th { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#bbb;
                  padding:12px 14px; text-align:left; border-bottom:1.5px solid #eef0f3; }
  .log-table td { padding:11px 14px; font-size:13px; color:#444; border-bottom:1px solid #f5f6f8; }
  .log-table tr:last-child td { border-bottom:none; }
  .type-topup        { color:#16a34a; font-weight:700; }
  .type-disbursement { color:#2563eb; font-weight:700; }
  .type-adjustment   { color:#d97706; font-weight:700; }
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php?office=<?= $office ?>">Petty Cash</a>
  <span class="topbar-title">Float Log</span>
</div>
<div class="log-wrap">
  <div class="office-tabs">
    <?php foreach(OFFICES as $k=>$o): ?>
    <a class="otab <?= $office===$k?'active':'' ?>" href="?office=<?= $k ?>">
      <?= $k==='doha'?'🇶🇦':'🇱🇧' ?> <?= $o['label'] ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
    <span style="font-size:13px;color:#888">
      Current float: <strong style="color:#1a1a1a"><?= $cur ?> <?= number_format($float,2) ?></strong>
    </span>
    <a href="topup.php?office=<?= $office ?>" style="padding:8px 18px;background:#FF3D33;color:#fff;border-radius:30px;font-size:12.5px;font-weight:700;text-decoration:none">＋ Top Up</a>
  </div>
  <table class="log-table">
    <thead><tr>
      <th>Date</th><th>Type</th><th>Amount</th><th>Balance After</th><th>Reference</th><th>Notes</th><th>By</th>
    </tr></thead>
    <tbody>
    <?php foreach($logs as $l): ?>
    <tr>
      <td style="color:#bbb;font-size:12px;white-space:nowrap"><?= date('d M Y H:i', strtotime($l['created_at'])) ?></td>
      <td class="type-<?= $l['type'] ?>"><?= ucfirst($l['type']) ?></td>
      <td style="font-weight:700;color:<?= $l['type']==='topup'?'#16a34a':'#2563eb' ?>">
        <?= $l['type']==='topup'?'＋':'−' ?> <?= $cur ?> <?= number_format($l['amount'],2) ?>
      </td>
      <td style="font-weight:700"><?= $cur ?> <?= number_format($l['balance_after'],2) ?></td>
      <td style="color:#888"><?= htmlspecialchars($l['reference']??'—') ?></td>
      <td style="color:#888"><?= htmlspecialchars($l['notes']??'—') ?></td>
      <td><?= htmlspecialchars($l['uname']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>
</body>
</html>
