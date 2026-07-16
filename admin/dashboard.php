<?php
session_start();
require '../config.php';
require_login();
if (!is_admin()) { header('Location: /'); exit; }

$db = db();

// ── Stats ─────────────────────────────────────────────────────────────────────
$needs_approval = ['amex','debit_note','credit_note','vendor_recon','vendor_reg','client_reg'];
$in = implode(',', array_fill(0, count($needs_approval), '?'));

$pending_count = $db->prepare("SELECT COUNT(*) FROM form_submissions WHERE form_type IN ($in) AND approval_status='pending'");
$pending_count->execute($needs_approval);
$pending_count = (int)$pending_count->fetchColumn();

$month_count = (int)$db->query("SELECT COUNT(*) FROM form_submissions WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

$pc_doha   = $db->query("SELECT COALESCE(SUM(amount),0) FROM petty_cash_requests WHERE office='doha'  AND status='unpaid'")->fetchColumn();
$pc_beirut = $db->query("SELECT COALESCE(SUM(amount),0) FROM petty_cash_requests WHERE office='beirut' AND status='unpaid'")->fetchColumn();

$warranty_soon = (int)$db->query("SELECT COUNT(*) FROM assets WHERE warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status='active'")->fetchColumn();

// ── Pending approvals list ────────────────────────────────────────────────────
$filter_type = $_GET['type'] ?? '';
$filter_sql  = '';
$filter_vals = $needs_approval;
if ($filter_type && in_array($filter_type, $needs_approval)) {
    $filter_sql  = "AND fs.form_type = ?";
    $filter_vals = array_merge($needs_approval, [$filter_type]);
}

$pending_q = $db->prepare("
    SELECT fs.id, fs.form_type, fs.form_data, fs.created_at,
           u.name AS submitted_by
    FROM form_submissions fs
    LEFT JOIN users u ON u.id = fs.user_id
    WHERE fs.form_type IN ($in) AND fs.approval_status='pending'
    $filter_sql
    ORDER BY fs.created_at ASC
");
$pending_q->execute($filter_vals);
$pending_rows = $pending_q->fetchAll();

// ── Recent activity ───────────────────────────────────────────────────────────
$recent = $db->query("
    SELECT fs.id, fs.form_type, fs.form_data, fs.created_at, fs.approval_status,
           u.name AS submitted_by
    FROM form_submissions fs
    LEFT JOIN users u ON u.id = fs.user_id
    ORDER BY fs.created_at DESC LIMIT 15
")->fetchAll();

// ── Petty cash breakdown ──────────────────────────────────────────────────────
$pc_stats = [];
$pc_q = $db->query("SELECT office, SUM(status='paid') paid_count, COALESCE(SUM(CASE WHEN status='paid' THEN amount END),0) paid_amt, SUM(status='unpaid') unpaid_count, COALESCE(SUM(CASE WHEN status='unpaid' THEN amount END),0) unpaid_amt, COUNT(*) total_count, COALESCE(SUM(amount),0) total_amt FROM petty_cash_requests GROUP BY office");
foreach ($pc_q->fetchAll() as $r) $pc_stats[$r['office']] = $r;

// ── Form volume this month ────────────────────────────────────────────────────
$vol = $db->query("SELECT form_type, COUNT(*) cnt FROM form_submissions WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) GROUP BY form_type")->fetchAll();
$vol_map = array_column($vol, 'cnt', 'form_type');

// ── Helpers ───────────────────────────────────────────────────────────────────
$type_labels = [
    'amex'           => 'Credit Card Auth',
    'accountability' => 'Accountability',
    'debit_note'     => 'Debit Note',
    'credit_note'    => 'Credit Note',
    'vendor_recon'   => 'Vendor Recon',
    'vendor_reg'     => 'Vendor Registration',
    'client_reg'     => 'Client Registration',
];
$type_icons = [
    'amex'           => '💳',
    'accountability' => '📦',
    'debit_note'     => '📄',
    'credit_note'    => '📋',
    'vendor_recon'   => '📊',
    'vendor_reg'     => '🏢',
    'client_reg'     => '👥',
];
function submission_title(array $fd, string $type): string {
    return match($type) {
        'amex'           => $fd['merchant'] ?? '—',
        'accountability' => $fd['received_by'] ?? '—',
        'debit_note',
        'credit_note'    => $fd['to_name'] ?? '—',
        'vendor_recon'   => $fd['vendor_name'] ?? '—',
        'vendor_reg'     => $fd['legal_name'] ?? '—',
        'client_reg'     => $fd['company_name'] ?? '—',
        default          => '—',
    };
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f4f5f7;color:#1a1a1a}

/* ── Stats row ── */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
.stat-card{background:#fff;border:1.5px solid #e8eaee;border-radius:14px;padding:22px 24px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent,#FF3D33)}
.stat-val{font-size:32px;font-weight:900;color:#1a1a1a;line-height:1;margin-bottom:6px}
.stat-lbl{font-size:12px;color:#999;font-weight:500;text-transform:uppercase;letter-spacing:.6px}
.stat-sub{font-size:11px;color:#bbb;margin-top:4px}
.stat-icon{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:32px;opacity:.12}

/* ── Section titles ── */
.dash-section{margin-bottom:28px}
.dash-title{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#aaa;margin-bottom:12px;display:flex;align-items:center;gap:10px}
.dash-title span{flex:1;height:1px;background:#e8eaee}

/* ── Pending table ── */
.pend-wrap{background:#fff;border:1.5px solid #e8eaee;border-radius:14px;overflow:hidden}
.pend-header{padding:16px 20px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.pend-header h2{font-size:15px;font-weight:800;color:#1a1a1a;flex:1}
.type-filter{display:flex;gap:6px;flex-wrap:wrap}
.type-btn{padding:5px 12px;border-radius:20px;font-size:11px;font-weight:700;text-decoration:none;color:#888;background:#f4f5f7;border:1.5px solid transparent;transition:.15s}
.type-btn:hover,.type-btn.active{background:#fff0ef;color:#FF3D33;border-color:#fca5a5}
.pend-table{width:100%;border-collapse:collapse}
.pend-table th{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#aaa;padding:10px 20px;text-align:left;background:#fafafa;border-bottom:1px solid #f0f0f0}
.pend-table td{padding:13px 20px;font-size:13px;border-bottom:1px solid #f8f8f8;vertical-align:middle}
.pend-table tr:last-child td{border-bottom:none}
.pend-table tr:hover td{background:#fafafa}
.form-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;background:#f4f5f7;color:#555}
.age-warn{color:#d97706;font-weight:700}
.age-old{color:#b91c1c;font-weight:700}
.empty-state{padding:48px 20px;text-align:center;color:#bbb;font-size:14px}
.empty-icon{font-size:40px;margin-bottom:12px}

/* ── Action buttons ── */
.act-btns{display:flex;gap:6px}
.btn-approve{padding:6px 14px;background:#15803d;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s}
.btn-approve:hover{background:#166534}
.btn-reject{padding:6px 14px;background:#fff;color:#b91c1c;border:1.5px solid #fca5a5;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s}
.btn-reject:hover{background:#fff5f5}
.actioned{font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px}
.actioned.approved{background:#f0fdf4;color:#15803d}
.actioned.rejected{background:#fff5f5;color:#b91c1c}

/* ── Bottom grid ── */
.bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.dash-card{background:#fff;border:1.5px solid #e8eaee;border-radius:14px;overflow:hidden}
.dash-card-head{padding:16px 20px;border-bottom:1px solid #f0f0f0;font-size:14px;font-weight:800;color:#1a1a1a}
.dash-card-body{padding:16px 20px}

/* ── Petty cash breakdown ── */
.office-block{margin-bottom:16px}
.office-block:last-child{margin-bottom:0}
.office-name{font-size:12px;font-weight:700;color:#555;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px}
.pc-bars{display:flex;gap:0;border-radius:6px;overflow:hidden;height:10px;margin-bottom:6px}
.pc-bar-paid{background:#15803d;transition:width .4s}
.pc-bar-unpaid{background:#fca5a5}
.pc-legend{display:flex;justify-content:space-between;font-size:11px;color:#aaa}
.pc-legend b{color:#333}

/* ── Form volume chart ── */
.vol-bars{display:flex;flex-direction:column;gap:10px}
.vol-row{display:flex;align-items:center;gap:10px}
.vol-label{font-size:12px;color:#555;width:130px;flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vol-bar-wrap{flex:1;background:#f4f5f7;border-radius:4px;height:8px;overflow:hidden}
.vol-bar-fill{height:100%;background:#FF3D33;border-radius:4px;transition:width .4s}
.vol-count{font-size:12px;font-weight:700;color:#555;width:24px;text-align:right;flex-shrink:0}

/* ── Recent feed ── */
.feed-list{display:flex;flex-direction:column;gap:0}
.feed-item{display:flex;align-items:center;gap:12px;padding:11px 20px;border-bottom:1px solid #f8f8f8;transition:.1s}
.feed-item:last-child{border-bottom:none}
.feed-item:hover{background:#fafafa}
.feed-icon{width:32px;height:32px;border-radius:8px;background:#f4f5f7;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.feed-main{flex:1;min-width:0}
.feed-title{font-size:13px;font-weight:600;color:#1a1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.feed-meta{font-size:11px;color:#aaa;margin-top:2px}
.feed-status{font-size:11px;font-weight:700;padding:3px 8px;border-radius:12px;flex-shrink:0}
.fs-pending{background:#fffbeb;color:#d97706}
.fs-approved{background:#f0fdf4;color:#15803d}
.fs-rejected{background:#fff5f5;color:#b91c1c}
.fs-na{background:#f4f5f7;color:#888}

/* ── Modal ── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:none;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:32px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal h3{font-size:17px;font-weight:800;margin-bottom:6px}
.modal p{font-size:13px;color:#888;margin-bottom:18px}
.modal textarea{width:100%;border:1.5px solid #e8eaee;border-radius:8px;padding:10px 13px;font-size:13px;font-family:inherit;resize:vertical;min-height:80px;outline:none}
.modal textarea:focus{border-color:#FF3D33}
.modal-btns{display:flex;gap:10px;margin-top:16px;justify-content:flex-end}
.modal-cancel{padding:9px 18px;background:#f4f5f7;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.modal-confirm{padding:9px 18px;background:#b91c1c;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer}

@media(max-width:900px){
  .stats-row{grid-template-columns:1fr 1fr}
  .bottom-grid{grid-template-columns:1fr}
}
@media(max-width:560px){
  .stats-row{grid-template-columns:1fr}
}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <span class="topbar-title">Dashboard</span>
  <span style="font-size:12px;color:#aaa"><?= date('l, d F Y') ?></span>
</div>

<div style="padding:28px 32px;max-width:1300px">

  <!-- ── Stats row ── -->
  <div class="stats-row">
    <div class="stat-card" style="--accent:#FF3D33">
      <div class="stat-val"><?= $pending_count ?></div>
      <div class="stat-lbl">Pending Approvals</div>
      <div class="stat-sub">Awaiting finance review</div>
      <div class="stat-icon">⏳</div>
    </div>
    <div class="stat-card" style="--accent:#0891b2">
      <div class="stat-val"><?= $month_count ?></div>
      <div class="stat-lbl">Submissions This Month</div>
      <div class="stat-sub"><?= date('F Y') ?></div>
      <div class="stat-icon">📋</div>
    </div>
    <div class="stat-card" style="--accent:#d97706">
      <div class="stat-val" style="font-size:22px">
        QAR <?= number_format($pc_doha, 0) ?>
        <span style="font-size:13px;color:#aaa;display:block;margin-top:2px">USD <?= number_format($pc_beirut, 0) ?></span>
      </div>
      <div class="stat-lbl">Petty Cash Unpaid</div>
      <div class="stat-sub">Doha + Beirut combined</div>
      <div class="stat-icon">💰</div>
    </div>
    <div class="stat-card" style="--accent:<?= $warranty_soon > 0 ? '#b91c1c' : '#15803d' ?>">
      <div class="stat-val" style="color:<?= $warranty_soon > 0 ? '#b91c1c' : '#15803d' ?>"><?= $warranty_soon ?></div>
      <div class="stat-lbl">Warranty Expiring</div>
      <div class="stat-sub">Within the next 30 days</div>
      <div class="stat-icon">🖥</div>
    </div>
  </div>

  <!-- ── Pending Approvals ── -->
  <div class="dash-section">
    <div class="dash-title">Pending Approvals <span></span></div>
    <div class="pend-wrap">
      <div class="pend-header">
        <h2>Requires Action <span style="background:#FF3D33;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;margin-left:6px"><?= $pending_count ?></span></h2>
        <div class="type-filter">
          <a href="dashboard.php" class="type-btn <?= !$filter_type ? 'active' : '' ?>">All</a>
          <?php foreach ($needs_approval as $t): ?>
          <a href="dashboard.php?type=<?= $t ?>" class="type-btn <?= $filter_type===$t ? 'active' : '' ?>"><?= $type_icons[$t] ?> <?= $type_labels[$t] ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (empty($pending_rows)): ?>
      <div class="empty-state">
        <div class="empty-icon">✅</div>
        <div>No pending approvals<?= $filter_type ? ' for this form type' : '' ?>.</div>
      </div>
      <?php else: ?>
      <table class="pend-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Form Type</th>
            <th>Details</th>
            <th>Submitted By</th>
            <th>Date</th>
            <th>Age</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody id="pendingBody">
        <?php foreach ($pending_rows as $row):
          $fd       = json_decode($row['form_data'], true) ?? [];
          $title    = submission_title($fd, $row['form_type']);
          $label    = $type_labels[$row['form_type']] ?? $row['form_type'];
          $icon     = $type_icons[$row['form_type']] ?? '📄';
          $submitted = $row['created_at'] ? new DateTime($row['created_at']) : null;
          $now       = new DateTime();
          $age_days  = $submitted ? (int)$now->diff($submitted)->days : 0;
          $age_class = $age_days >= 5 ? 'age-old' : ($age_days >= 2 ? 'age-warn' : '');
          $by        = $row['submitted_by'] ?? 'Public';
        ?>
        <tr id="row-<?= $row['id'] ?>">
          <td style="color:#aaa;font-size:12px">#<?= $row['id'] ?></td>
          <td><span class="form-badge"><?= $icon ?> <?= htmlspecialchars($label) ?></span></td>
          <td style="font-weight:600"><?= htmlspecialchars($title) ?></td>
          <td style="color:#888"><?= htmlspecialchars($by) ?></td>
          <td style="color:#888;font-size:12px;white-space:nowrap"><?= $submitted ? $submitted->format('d M Y') : '—' ?></td>
          <td><span class="<?= $age_class ?>"><?= $age_days ?>d</span></td>
          <td>
            <div class="act-btns" style="justify-content:flex-end">
              <?php
                $link = match($row['form_type']) {
                    'amex'        => BASE_URL . '/amex/confirm.php?id=' . $row['id'],
                    'vendor_reg'  => BASE_URL . '/admin/submissions.php?id=' . $row['id'],
                    'client_reg'  => BASE_URL . '/admin/submissions.php?id=' . $row['id'],
                    default       => BASE_URL . '/admin/submissions.php?id=' . $row['id'],
                };
              ?>
              <a href="<?= $link ?>" target="_blank" style="padding:6px 10px;background:#f4f5f7;border-radius:6px;font-size:12px;text-decoration:none;color:#555;font-weight:600">View</a>
              <button class="btn-approve" onclick="doApprove(<?= $row['id'] ?>)">✓ Approve</button>
              <button class="btn-reject"  onclick="openReject(<?= $row['id'] ?>)">✗ Reject</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Bottom grid ── -->
  <div class="bottom-grid" style="margin-bottom:28px">

    <!-- Petty Cash Breakdown -->
    <div class="dash-card">
      <div class="dash-card-head">💰 Petty Cash Breakdown</div>
      <div class="dash-card-body">
        <?php foreach (['doha'=>['🇶🇦','Doha','QAR'], 'beirut'=>['🇱🇧','Beirut','USD']] as $office => [$flag,$name,$cur]):
          $s = $pc_stats[$office] ?? ['paid_count'=>0,'paid_amt'=>0,'unpaid_count'=>0,'unpaid_amt'=>0,'total_count'=>0,'total_amt'=>0];
          $total = (float)$s['total_amt'];
          $paid_pct  = $total > 0 ? round(($s['paid_amt'] / $total) * 100) : 0;
          $unpaid_pct = 100 - $paid_pct;
        ?>
        <div class="office-block">
          <div class="office-name"><?= $flag ?> <?= $name ?> Office <span style="font-weight:400;color:#aaa">(<?= $cur ?>)</span></div>
          <div class="pc-bars">
            <div class="pc-bar-paid"   style="width:<?= $paid_pct ?>%"></div>
            <div class="pc-bar-unpaid" style="width:<?= $unpaid_pct ?>%"></div>
          </div>
          <div class="pc-legend">
            <span>✓ Paid: <b><?= number_format($s['paid_amt'],0) ?></b> (<?= $s['paid_count'] ?> entries)</span>
            <span>○ Unpaid: <b><?= number_format($s['unpaid_amt'],0) ?></b> (<?= $s['unpaid_count'] ?> entries)</span>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($pc_stats)): ?>
        <div style="text-align:center;color:#bbb;font-size:13px;padding:20px 0">No petty cash entries yet.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Form Volume This Month -->
    <div class="dash-card">
      <div class="dash-card-head">📊 Form Volume — <?= date('F') ?></div>
      <div class="dash-card-body">
        <?php
        $max_vol = max(1, ...array_values($vol_map + ['_'=>0]));
        foreach ($type_labels as $t => $lbl):
          $cnt = (int)($vol_map[$t] ?? 0);
          $pct = round(($cnt / $max_vol) * 100);
        ?>
        <div class="vol-row">
          <div class="vol-label"><?= $type_icons[$t] ?> <?= $lbl ?></div>
          <div class="vol-bar-wrap"><div class="vol-bar-fill" style="width:<?= $pct ?>%"></div></div>
          <div class="vol-count"><?= $cnt ?: '<span style="color:#ddd">0</span>' ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ── Recent Activity ── -->
  <div class="dash-section">
    <div class="dash-title">Recent Activity <span></span></div>
    <div class="pend-wrap">
      <div class="pend-header"><h2>Last 15 Submissions</h2></div>
      <div class="feed-list">
        <?php foreach ($recent as $row):
          $fd     = json_decode($row['form_data'], true) ?? [];
          $title  = submission_title($fd, $row['form_type']);
          $label  = $type_labels[$row['form_type']] ?? $row['form_type'];
          $icon   = $type_icons[$row['form_type']] ?? '📄';
          $by     = $row['submitted_by'] ?? 'Public';
          $st     = $row['approval_status'];
          $needs_ap = in_array($row['form_type'], $needs_approval);
          if (!$needs_ap) { $st_label = '—'; $st_class = 'fs-na'; }
          elseif ($st==='approved') { $st_label = '✓ Approved'; $st_class = 'fs-approved'; }
          elseif ($st==='rejected') { $st_label = '✗ Rejected'; $st_class = 'fs-rejected'; }
          else { $st_label = '⏳ Pending'; $st_class = 'fs-pending'; }
          $dt = $row['created_at'] ? (new DateTime($row['created_at']))->format('d M Y') : '—';
        ?>
        <div class="feed-item">
          <div class="feed-icon"><?= $icon ?></div>
          <div class="feed-main">
            <div class="feed-title"><?= htmlspecialchars($title) ?></div>
            <div class="feed-meta"><?= htmlspecialchars($label) ?> &nbsp;·&nbsp; <?= htmlspecialchars($by) ?> &nbsp;·&nbsp; <?= $dt ?></div>
          </div>
          <div class="feed-status <?= $st_class ?>"><?= $st_label ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($recent)): ?>
        <div class="empty-state"><div class="empty-icon">📭</div><div>No submissions yet.</div></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /padding -->
</div><!-- /main-content -->

<!-- ── Reject modal ── -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <h3>Reject Submission</h3>
    <p>Optionally add a note explaining the reason. This will be sent to the submitter.</p>
    <textarea id="rejectNotes" placeholder="Reason for rejection (optional)…"></textarea>
    <div class="modal-btns">
      <button class="modal-cancel" onclick="closeModal()">Cancel</button>
      <button class="modal-confirm" onclick="confirmReject()">Reject</button>
    </div>
  </div>
</div>

<script>
let _rejectId = null;

function doApprove(id) {
  if (!confirm('Approve this submission?')) return;
  action(id, 'approved', '');
}
function openReject(id) {
  _rejectId = id;
  document.getElementById('rejectNotes').value = '';
  document.getElementById('rejectModal').classList.add('open');
}
function closeModal() {
  document.getElementById('rejectModal').classList.remove('open');
  _rejectId = null;
}
function confirmReject() {
  if (!_rejectId) return;
  action(_rejectId, 'rejected', document.getElementById('rejectNotes').value);
  closeModal();
}

function action(id, status, notes) {
  const row = document.getElementById('row-' + id);
  if (row) row.style.opacity = '.4';

  fetch('approve.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'id=' + id + '&action=' + status + '&notes=' + encodeURIComponent(notes)
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      if (row) {
        const cls   = status === 'approved' ? 'approved' : 'rejected';
        const label = status === 'approved' ? '✓ Approved' : '✗ Rejected';
        row.querySelector('.act-btns').innerHTML = '<span class="actioned ' + cls + '">' + label + '</span>';
        row.style.opacity = '1';
      }
      // Update pending count badge
      const badge = document.querySelector('.pend-header h2 span');
      if (badge) { const n = Math.max(0, parseInt(badge.textContent) - 1); badge.textContent = n; }
      // Update stat card
      const statVal = document.querySelector('.stat-val');
      if (statVal) { const n = Math.max(0, parseInt(statVal.textContent) - 1); statVal.textContent = n; }
    } else {
      alert(data.error || 'Something went wrong.');
      if (row) row.style.opacity = '1';
    }
  })
  .catch(() => { alert('Network error.'); if (row) row.style.opacity = '1'; });
}

document.getElementById('rejectModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
</body>
</html>
