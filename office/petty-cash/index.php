<?php
session_start();
require '../../config.php';
require_login();
$user  = current_user();
$admin = is_admin();

// Active office tab (admins can switch; staff locked to their office)
$user_office = $user['office'] ?? null;
if ($user_office === '') $user_office = null;
if ($admin) {
    $active_office = $_GET['office'] ?? $user_office ?? 'doha';
    if (!array_key_exists($active_office, OFFICES)) $active_office = 'doha';
} else {
    $active_office = $user_office;
}

// Floats
$floats = [];
foreach (OFFICES as $key => $_) {
    $row = db()->prepare("SELECT balance FROM petty_cash_float WHERE office=?");
    $row->execute([$key]);
    $floats[$key] = (float)$row->fetchColumn();
}

// Stats for active office
if ($admin) {
    $pending_count = db()->prepare("SELECT COUNT(*) FROM petty_cash_requests WHERE status='pending' AND office=?");
    $pending_count->execute([$active_office]);
    $pending_count = (int)$pending_count->fetchColumn();

    $month_spent = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM petty_cash_requests WHERE status='paid' AND office=? AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())");
    $month_spent->execute([$active_office]);
    $month_spent = (float)$month_spent->fetchColumn();
}

// Requests
$filter = $_GET['status'] ?? 'all';
if ($admin) {
    $sql = "SELECT r.*, u.name as uname FROM petty_cash_requests r JOIN users u ON u.id=r.user_id WHERE r.office=?";
    $params = [$active_office];
    if ($filter !== 'all') { $sql .= " AND r.status=?"; $params[] = $filter; }
    $sql .= " ORDER BY r.created_at DESC LIMIT 80";
    $stmt = db()->prepare($sql); $stmt->execute($params);
    $rows = $stmt->fetchAll();
} else {
    if (!$user_office) {
        $rows = [];
    } else {
        $stmt = db()->prepare("SELECT * FROM petty_cash_requests WHERE user_id=? ORDER BY created_at DESC");
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll();
    }
}

$cur = OFFICES[$active_office]['currency'] ?? 'QAR';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Petty Cash — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
  .pc-wrap { padding: 28px 40px 80px; }

  .office-tabs-unused { display:none; }
  .otab { display:flex; align-items:center; gap:9px; padding:10px 20px; border-radius:12px;
          border:1.5px solid #e8eaee; background:#fff; text-decoration:none; color:#666;
          font-size:13px; font-weight:600; transition:all .15s; }
  .otab:hover { border-color:#ccc; color:#333; }
  .otab.active { border-color:#FF3D33; background:#fff5f4; color:#FF3D33; box-shadow:0 2px 8px rgba(255,61,51,.12); }
  .otab .flag { font-size:18px; }
  .otab .bal  { font-size:11px; color:#aaa; font-weight:500; margin-top:1px; }
  .otab.active .bal { color:#FF3D33; opacity:.7; }

  .stat-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:12px; margin-bottom:28px; }
  .stat-card { background:#fff; border:1px solid #eef0f3; border-radius:14px; padding:20px 22px; }
  .stat-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#bbb; margin-bottom:8px; }
  .stat-val   { font-size:26px; font-weight:900; color:#1a1a1a; letter-spacing:-1px; }
  .stat-val.red   { color:#FF3D33; }
  .stat-val.green { color:#16a34a; }
  .stat-sub   { font-size:11px; color:#ccc; margin-top:3px; }

  .top-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
  .sec-title { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#aaa; }
  .btn-primary { display:inline-flex; align-items:center; gap:6px; padding:9px 18px;
                 background:#FF3D33; color:#fff; border-radius:30px; font-size:12.5px; font-weight:700;
                 text-decoration:none; border:none; cursor:pointer; transition:background .15s; }
  .btn-primary:hover { background:#c0170e; }
  .btn-outline { display:inline-flex; align-items:center; gap:6px; padding:9px 18px;
                 background:#fff; color:#555; border-radius:30px; font-size:12.5px; font-weight:600;
                 text-decoration:none; border:1.5px solid #e8eaee; transition:border-color .15s; }
  .btn-outline:hover { border-color:#aaa; }

  .filter-tabs { display:flex; gap:5px; margin-bottom:14px; flex-wrap:wrap; }
  .ftab { padding:5px 13px; border-radius:20px; font-size:11.5px; font-weight:600; cursor:pointer;
          border:1.5px solid #e8eaee; background:#fff; color:#888; text-decoration:none; transition:all .15s; }
  .ftab.active, .ftab:hover { border-color:#FF3D33; color:#FF3D33; background:#fff8f8; }

  .table-wrap { background:#fff; border:1px solid #eef0f3; border-radius:14px; overflow:hidden; }
  .req-table { width:100%; border-collapse:collapse; }
  .req-table th { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#bbb;
                  padding:12px 14px; text-align:left; border-bottom:1.5px solid #eef0f3; }
  .req-table td { padding:11px 14px; font-size:13px; color:#444; border-bottom:1px solid #f5f6f8; vertical-align:middle; }
  .req-table tr:last-child td { border-bottom:none; }
  .req-table tr:hover td { background:#fafbfc; }

  .badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:20px; font-size:10.5px; font-weight:700; }
  .badge-pending  { background:#fef9c3; color:#854d0e; }
  .badge-approved { background:#dcfce7; color:#166534; }
  .badge-rejected { background:#fee2e2; color:#991b1b; }
  .badge-paid     { background:#dbeafe; color:#1e40af; }
  .cat-pill { background:#f5f6f8; border-radius:6px; padding:2px 8px; font-size:11px; color:#777; }
  .amt { font-weight:700; color:#1a1a1a; font-variant-numeric:tabular-nums; }
  .empty { text-align:center; padding:48px; color:#ccc; font-size:14px; }
  .no-office { background:#fffbeb; border:1.5px solid #fde68a; border-left:4px solid #f59e0b;
               border-radius:10px; padding:16px 20px; font-size:13px; color:#92400e; margin-bottom:24px; }
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/">G2 Tools</a>
  <span class="topbar-title">Petty Cash</span>
</div>
<div class="pc-wrap">

  <?php if (!$admin && !$user_office): ?>
  <div class="no-office">
    Your account has not been assigned to an office yet. Please ask an IT Admin to assign you to Doha or Beirut in Manage Users.
  </div>
  <?php endif; ?>


  <!-- Stats -->
  <?php if ($admin): ?>
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-label">Float Balance</div>
      <div class="stat-val <?= $floats[$active_office] < ($active_office==='doha'?500:200) ? 'red' : 'green' ?>">
        <?= $cur ?> <?= number_format($floats[$active_office],2) ?>
      </div>
      <div class="stat-sub"><?= OFFICES[$active_office]['label'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pending Requests</div>
      <div class="stat-val <?= $pending_count>0?'red':'' ?>"><?= $pending_count ?></div>
      <div class="stat-sub">Awaiting approval</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Spent This Month</div>
      <div class="stat-val"><?= $cur ?> <?= number_format($month_spent,2) ?></div>
      <div class="stat-sub"><?= date('F Y') ?></div>
    </div>
  </div>
  <?php elseif ($user_office): ?>
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-label">Float Balance</div>
      <div class="stat-val"><?= $cur ?> <?= number_format($floats[$user_office],2) ?></div>
      <div class="stat-sub"><?= OFFICES[$user_office]['label'] ?></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Top bar -->
  <div class="top-bar">
    <div class="sec-title"><?= $admin ? 'All Requests' : 'My Requests' ?></div>
    <div style="display:flex;gap:8px">
      <?php if ($admin): ?>
      <a class="btn-outline" href="topup.php?office=<?= $active_office ?>">＋ Top Up Float</a>
      <a class="btn-outline" href="log.php?office=<?= $active_office ?>">📋 Log</a>
      <a class="btn-outline" href="report.php?office=<?= $active_office ?>">📊 Report</a>
      <?php endif; ?>
      <?php if ($admin || $user_office): ?>
      <a class="btn-primary" href="request.php?office=<?= $active_office ?>">＋ New Request</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filter tabs -->
  <?php if ($admin): ?>
  <div class="filter-tabs">
    <?php foreach(['all'=>'All','pending'=>'Pending','approved'=>'Approved','paid'=>'Paid','rejected'=>'Rejected'] as $k=>$l): ?>
    <a class="ftab <?= $filter===$k?'active':'' ?>" href="?office=<?= $active_office ?>&status=<?= $k ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Table -->
  <div class="table-wrap">
  <?php if (empty($rows)): ?>
    <div class="empty">No requests found.</div>
  <?php else: ?>
  <table class="req-table">
    <thead><tr>
      <?php if($admin): ?><th>Staff</th><?php endif; ?>
      <th>Description</th><th>Category</th><th>Amount</th><th>Status</th><th>Date</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
    <tr>
      <?php if($admin): ?><td style="font-weight:600;color:#1a1a1a"><?= htmlspecialchars($r['uname']) ?></td><?php endif; ?>
      <td><?= htmlspecialchars($r['description']) ?></td>
      <td><span class="cat-pill"><?= htmlspecialchars($r['category']) ?></span></td>
      <td class="amt"><?= $cur ?> <?= number_format($r['amount'],2) ?></td>
      <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
      <td style="color:#bbb;font-size:12px;white-space:nowrap"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
      <td>
        <?php
        $lbl = match($r['status']) { 'pending'=>'Review','approved'=>'Pay', default=>'View' };
        $clr = match($r['status']) { 'pending'=>'#FF3D33','approved'=>'#2563eb', default=>'#bbb' };
        ?>
        <a href="manage.php?id=<?= $r['id'] ?>" style="font-size:12px;color:<?= $clr ?>;font-weight:700;text-decoration:none"><?= $lbl ?></a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  </div>

</div>
</div>
</body>
</html>
