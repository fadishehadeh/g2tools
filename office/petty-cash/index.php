<?php
session_start();
require '../../config.php';
require_login();
$user  = current_user();
$admin = is_admin();

$user_office = $user['office'] ?? null;
if ($user_office === '') $user_office = null;
if ($admin) {
    $active_office = $_GET['office'] ?? $user_office ?? 'doha';
    if (!array_key_exists($active_office, OFFICES)) $active_office = 'doha';
} else {
    $active_office = $user_office ?? 'doha';
}

// Gate: user must have access to this specific office's petty cash
if (!$admin && !can('petty_cash_'.$active_office) && !can('petty_cash')) {
    header('Location: /'); exit;
}

// Handle inline mark-paid / mark-unpaid (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_paid']) && $admin) {
    $rid    = (int)$_POST['toggle_paid'];
    $newst  = $_POST['new_status'] === 'paid' ? 'paid' : 'unpaid';
    $paid_at = $newst === 'paid' ? date('Y-m-d H:i:s') : null;
    db()->prepare("UPDATE petty_cash_requests SET status=?, paid_at=? WHERE id=?")->execute([$newst, $paid_at, $rid]);
    $redir = '?office='.$active_office
        .(!empty($_POST['from']) ? '&from='.urlencode($_POST['from']) : '')
        .(!empty($_POST['to'])   ? '&to='.urlencode($_POST['to'])     : '')
        .(!empty($_POST['fstatus']) ? '&fstatus='.urlencode($_POST['fstatus']) : '');
    header('Location: '.$redir); exit;
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
    $unpaid_count = db()->prepare("SELECT COUNT(*) FROM petty_cash_requests WHERE status='unpaid' AND office=?");
    $unpaid_count->execute([$active_office]);
    $unpaid_count = (int)$unpaid_count->fetchColumn();

    $month_spent = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM petty_cash_requests WHERE status='paid' AND office=? AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())");
    $month_spent->execute([$active_office]);
    $month_spent = (float)$month_spent->fetchColumn();
}

// Filters
$filter  = $_GET['fstatus'] ?? 'all';
$from    = $_GET['from'] ?? '';
$to      = $_GET['to']   ?? '';

// Build request query
if ($admin) {
    $sql    = "SELECT r.*, u.name as uname FROM petty_cash_requests r JOIN users u ON u.id=r.user_id WHERE r.office=?";
    $params = [$active_office];
    if ($filter !== 'all') { $sql .= " AND r.status=?"; $params[] = $filter; }
    if ($from) { $sql .= " AND DATE(r.created_at) >= ?"; $params[] = $from; }
    if ($to)   { $sql .= " AND DATE(r.created_at) <= ?"; $params[] = $to; }
    $sql .= " ORDER BY r.created_at DESC LIMIT 200";
    $stmt = db()->prepare($sql); $stmt->execute($params);
    $rows = $stmt->fetchAll();
} else {
    $rows = [];
    if ($user_office) {
        $sql    = "SELECT * FROM petty_cash_requests WHERE user_id=?";
        $params = [$user['id']];
        if ($from) { $sql .= " AND DATE(created_at) >= ?"; $params[] = $from; }
        if ($to)   { $sql .= " AND DATE(created_at) <= ?"; $params[] = $to; }
        $sql .= " ORDER BY created_at DESC";
        $stmt = db()->prepare($sql); $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }
}

$cur = OFFICES[$active_office]['currency'] ?? 'QAR';
$total_shown = array_sum(array_column($rows, 'amount'));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Petty Cash — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
.pc-wrap{padding:28px 40px 80px}
.otab{display:flex;align-items:center;gap:9px;padding:10px 20px;border-radius:12px;border:1.5px solid #e8eaee;background:#fff;text-decoration:none;color:#666;font-size:13px;font-weight:600;transition:all .15s}
.otab:hover{border-color:#ccc;color:#333}
.otab.active{border-color:#FF3D33;background:#fff5f4;color:#FF3D33;box-shadow:0 2px 8px rgba(255,61,51,.12)}
.otab .bal{font-size:11px;color:#aaa;font-weight:500;margin-top:1px}
.otab.active .bal{color:#FF3D33;opacity:.7}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin-bottom:28px}
.stat-card{background:#fff;border:1px solid #eef0f3;border-radius:14px;padding:20px 22px}
.stat-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#bbb;margin-bottom:8px}
.stat-val{font-size:26px;font-weight:900;color:#1a1a1a;letter-spacing:-1px}
.stat-val.red{color:#FF3D33}.stat-val.green{color:#16a34a}.stat-val.blue{color:#2563eb}
.stat-sub{font-size:11px;color:#ccc;margin-top:3px}
.top-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:10px}
.sec-title{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#aaa}
.btn-primary{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#FF3D33;color:#fff;border-radius:30px;font-size:12.5px;font-weight:700;text-decoration:none;border:none;cursor:pointer;transition:background .15s}
.btn-primary:hover{background:#c0170e}
.btn-outline{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#fff;color:#555;border-radius:30px;font-size:12.5px;font-weight:600;text-decoration:none;border:1.5px solid #e8eaee;transition:border-color .15s}
.btn-outline:hover{border-color:#aaa}
/* Date filter bar */
.filter-bar{display:flex;align-items:flex-end;gap:10px;margin-bottom:12px;flex-wrap:wrap;background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:12px 16px}
.fg{display:flex;flex-direction:column;gap:3px}
.fg label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#bbb}
.fg input,.fg select{padding:6px 10px;border:1.5px solid #e8eaee;border-radius:7px;font-size:12.5px;font-family:inherit;outline:none;background:#fff}
.fg input:focus,.fg select:focus{border-color:#FF3D33}
.quick-btns{display:flex;gap:5px;align-items:flex-end;flex-wrap:wrap}
.qbtn{padding:6px 11px;border-radius:6px;border:1.5px solid #e8eaee;background:#f9f9f9;font-size:11px;font-weight:600;color:#777;cursor:pointer;white-space:nowrap}
.qbtn:hover{border-color:#aaa;color:#333}
.filter-tabs{display:flex;gap:5px;margin-bottom:14px;flex-wrap:wrap}
.ftab{padding:5px 13px;border-radius:20px;font-size:11.5px;font-weight:600;cursor:pointer;border:1.5px solid #e8eaee;background:#fff;color:#888;text-decoration:none;transition:all .15s}
.ftab.active,.ftab:hover{border-color:#FF3D33;color:#FF3D33;background:#fff8f8}
.table-wrap{background:#fff;border:1px solid #eef0f3;border-radius:14px;overflow:hidden}
.req-table{width:100%;border-collapse:collapse}
.req-table th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#bbb;padding:12px 14px;text-align:left;border-bottom:1.5px solid #eef0f3}
.req-table td{padding:10px 14px;font-size:13px;color:#444;border-bottom:1px solid #f5f6f8;vertical-align:middle}
.req-table tr:last-child td{border-bottom:none}
.req-table tr:hover td{background:#fafbfc}
.badge-unpaid{background:#fef9c3;color:#854d0e;display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:10.5px;font-weight:700}
.badge-paid{background:#dbeafe;color:#1e40af;display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:10.5px;font-weight:700}
.cat-pill{background:#f5f6f8;border-radius:6px;padding:2px 8px;font-size:11px;color:#777}
.amt{font-weight:700;color:#1a1a1a;font-variant-numeric:tabular-nums}
.empty{text-align:center;padding:48px;color:#ccc;font-size:14px}
.no-office{background:#fffbeb;border:1.5px solid #fde68a;border-left:4px solid #f59e0b;border-radius:10px;padding:16px 20px;font-size:13px;color:#92400e;margin-bottom:24px}
.no-rcpt{font-size:10px;color:#f59e0b;background:#fffbeb;padding:2px 6px;border-radius:6px;font-weight:700}
/* Receipt lightbox */
.rcpt-link{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;color:#2563eb;text-decoration:none;font-weight:600}
.rcpt-link:hover{text-decoration:underline}
#lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center}
#lightbox.open{display:flex}
#lightbox img{max-width:90vw;max-height:90vh;border-radius:8px;object-fit:contain}
#lightbox-close{position:fixed;top:18px;right:24px;color:#fff;font-size:28px;cursor:pointer;font-weight:700;line-height:1}
/* Mark paid toggle */
.pay-form{display:inline}
.pay-btn{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;border:1.5px solid;cursor:pointer;background:#fff;transition:all .15s}
.pay-btn.mark-paid{border-color:#16a34a;color:#16a34a}.pay-btn.mark-paid:hover{background:#16a34a;color:#fff}
.pay-btn.mark-unpaid{border-color:#6b7280;color:#6b7280}.pay-btn.mark-unpaid:hover{background:#6b7280;color:#fff}
.total-row td{font-weight:700;color:#1a1a1a;padding-top:12px;border-top:2px solid #eef0f3;border-bottom:none}
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
  <div class="no-office">Your account has not been assigned to an office yet. Please ask an IT Admin to assign you to Doha or Beirut in Manage Users.</div>
  <?php endif; ?>

  <!-- Office tabs (admin only) -->
  <?php if ($admin): ?>
  <div style="display:flex;gap:10px;margin-bottom:22px;flex-wrap:wrap">
    <?php foreach (OFFICES as $ok => $ov): ?>
    <a class="otab <?= $active_office===$ok?'active':'' ?>" href="?office=<?= $ok ?>">
      <span style="font-size:18px"><?= $ov['flag'] ?></span>
      <span><?= $ov['label'] ?><br><span class="bal"><?= $ov['currency'] ?> <?= number_format($floats[$ok],2) ?></span></span>
    </a>
    <?php endforeach; ?>
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
      <div class="stat-label">Unpaid Requests</div>
      <div class="stat-val <?= $unpaid_count>0?'red':'' ?>"><?= $unpaid_count ?></div>
      <div class="stat-sub">Awaiting payment</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Paid This Month</div>
      <div class="stat-val blue"><?= $cur ?> <?= number_format($month_spent,2) ?></div>
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
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ($admin): ?>
      <a class="btn-outline" href="topup.php?office=<?= $active_office ?>">＋ Top Up Float</a>
      <a class="btn-outline" href="log.php?office=<?= $active_office ?>">📋 Log</a>
      <a class="btn-outline" href="report.php?office=<?= $active_office ?>">📊 Report</a>
      <a class="btn-outline" href="categories.php?office=<?= $active_office ?>">🗂 Categories</a>
      <?php endif; ?>
      <?php if ($admin || $user_office): ?>
      <a class="btn-primary" href="request.php?office=<?= $active_office ?>">＋ New Request</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Date filter bar -->
  <form class="filter-bar" method="GET" id="filterForm">
    <input type="hidden" name="office" value="<?= $active_office ?>">
    <input type="hidden" name="fstatus" value="<?= htmlspecialchars($filter) ?>">
    <div class="fg"><label>From</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>" id="inp_from"></div>
    <div class="fg"><label>To</label><input type="date" name="to" value="<?= htmlspecialchars($to) ?>" id="inp_to"></div>
    <div class="quick-btns">
      <button type="button" class="qbtn" onclick="setRange('this_month')">This month</button>
      <button type="button" class="qbtn" onclick="setRange('last_month')">Last month</button>
      <button type="button" class="qbtn" onclick="setRange('this_year')">This year</button>
      <button type="submit" style="padding:6px 14px;border-radius:7px;background:#1a1a1a;color:#fff;border:none;font-size:12px;font-weight:700;cursor:pointer">Apply</button>
      <?php if ($from || $to): ?>
      <a href="?office=<?= $active_office ?>&fstatus=<?= htmlspecialchars($filter) ?>" style="font-size:12px;color:#bbb;text-decoration:none;padding:6px 4px">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Status filter tabs -->
  <?php if ($admin): ?>
  <div class="filter-tabs">
    <?php
    $qs_date = ($from ? '&from='.$from : '').($to ? '&to='.$to : '');
    foreach (['all'=>'All','unpaid'=>'Unpaid','paid'=>'Paid'] as $k=>$l):
    ?>
    <a class="ftab <?= $filter===$k?'active':'' ?>" href="?office=<?= $active_office ?>&fstatus=<?= $k ?><?= $qs_date ?>"><?= $l ?></a>
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
      <th>Description</th><th>Category</th><th>Amount</th><th>Receipt</th><th>Status</th><th>Date</th>
      <?php if($admin): ?><th></th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
    <tr>
      <?php if($admin): ?><td style="font-weight:600;color:#1a1a1a"><?= htmlspecialchars($r['uname']) ?></td><?php endif; ?>
      <td><?= htmlspecialchars($r['description']) ?></td>
      <td><span class="cat-pill"><?= htmlspecialchars($r['category']) ?></span></td>
      <td class="amt"><?= $cur ?> <?= number_format($r['amount'],2) ?></td>
      <td>
        <?php if (!empty($r['receipt'])): ?>
          <?php $ext = strtolower(pathinfo($r['receipt'], PATHINFO_EXTENSION)); ?>
          <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
            <a href="#" class="rcpt-link" onclick="showLightbox('receipt.php?f=<?= urlencode($r['receipt']) ?>');return false">📎 View</a>
          <?php else: ?>
            <a href="receipt.php?f=<?= urlencode($r['receipt']) ?>" target="_blank" class="rcpt-link">📎 PDF</a>
          <?php endif; ?>
        <?php else: ?>
          <span class="no-rcpt">⚠ Missing</span>
        <?php endif; ?>
      </td>
      <td><span class="badge-<?= $r['status'] ?>"><?= $r['status']==='paid'?'Paid':'Unpaid' ?></span></td>
      <td style="color:#bbb;font-size:12px;white-space:nowrap"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
      <?php if ($admin): ?>
      <td>
        <form class="pay-form" method="POST">
          <input type="hidden" name="toggle_paid" value="<?= $r['id'] ?>">
          <input type="hidden" name="new_status" value="<?= $r['status']==='paid'?'unpaid':'paid' ?>">
          <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
          <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
          <input type="hidden" name="fstatus" value="<?= htmlspecialchars($filter) ?>">
          <?php if ($r['status'] === 'unpaid'): ?>
          <button class="pay-btn mark-paid" type="submit">✓ Mark Paid</button>
          <?php else: ?>
          <button class="pay-btn mark-unpaid" type="submit">↩ Unpaid</button>
          <?php endif; ?>
        </form>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if ($admin && count($rows) > 1): ?>
    <tfoot><tr class="total-row">
      <?php $colspan = 3; ?><td colspan="<?= $colspan ?>">Total (<?= count($rows) ?> requests)</td>
      <td class="amt"><?= $cur ?> <?= number_format($total_shown,2) ?></td>
      <td colspan="<?= $admin ? 4 : 3 ?>"></td>
    </tr></tfoot>
    <?php endif; ?>
  </table>
  <?php endif; ?>
  </div>

</div>
</div>

<!-- Receipt lightbox -->
<div id="lightbox" onclick="closeLightbox()">
  <span id="lightbox-close" onclick="closeLightbox()">✕</span>
  <img id="lightbox-img" src="" alt="Receipt">
</div>

<script>
function showLightbox(url){
  document.getElementById('lightbox-img').src = url;
  document.getElementById('lightbox').classList.add('open');
}
function closeLightbox(){
  document.getElementById('lightbox').classList.remove('open');
  document.getElementById('lightbox-img').src = '';
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeLightbox(); });

function setRange(r){
  const now  = new Date();
  const pad  = n => String(n).padStart(2,'0');
  const ymd  = d => d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate());
  let from, to = ymd(now);
  if (r==='this_month'){
    from = now.getFullYear()+'-'+pad(now.getMonth()+1)+'-01';
  } else if (r==='last_month'){
    const lm = new Date(now.getFullYear(), now.getMonth()-1, 1);
    from = ymd(lm);
    const le = new Date(now.getFullYear(), now.getMonth(), 0);
    to   = ymd(le);
  } else if (r==='this_year'){
    from = now.getFullYear()+'-01-01';
  }
  document.getElementById('inp_from').value = from;
  document.getElementById('inp_to').value   = to;
  document.getElementById('filterForm').submit();
}
</script>
</body>
</html>
