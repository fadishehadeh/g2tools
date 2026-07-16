<?php
session_start();
require 'config.php';
require_login();

$db   = db();
$user = current_user();
$uid  = $user['id'];

$needs_approval = ['amex','debit_note','credit_note','vendor_recon','vendor_reg','client_reg'];
$in = implode(',', array_fill(0, count($needs_approval), '?'));

// ── Stats (role-aware) ────────────────────────────────────────────────────────
$pending_count = 0;
if (is_finance_admin()) {
    $s = $db->prepare("SELECT COUNT(*) FROM form_submissions WHERE form_type IN ($in) AND approval_status='pending'");
    $s->execute($needs_approval);
    $pending_count = (int)$s->fetchColumn();
}

$month_count = 0;
if (is_admin()) {
    $month_count = (int)$db->query("SELECT COUNT(*) FROM form_submissions WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
} elseif (can_any_finance()) {
    $month_count = (int)$db->prepare("SELECT COUNT(*) FROM form_submissions WHERE user_id=? AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->execute([$uid]) ? (int)$db->query("SELECT COUNT(*) FROM form_submissions WHERE user_id=$uid AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn() : 0;
}

$pc_doha = $pc_beirut = 0;
if (is_admin() || can_any_petty()) {
    $pc_doha   = $db->query("SELECT COALESCE(SUM(amount),0) FROM petty_cash_requests WHERE office='doha'   AND status='unpaid'")->fetchColumn();
    $pc_beirut = $db->query("SELECT COALESCE(SUM(amount),0) FROM petty_cash_requests WHERE office='beirut' AND status='unpaid'")->fetchColumn();
}

$warranty_soon = 0;
if (can('assets')) {
    $warranty_soon = (int)$db->query("SELECT COUNT(*) FROM assets WHERE warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status='active'")->fetchColumn();
}

// ── Pending approvals (finance admin only) ────────────────────────────────────
$pending_rows = [];
if (is_finance_admin()) {
    $filter_type = $_GET['type'] ?? '';
    $filter_sql  = '';
    $filter_vals = $needs_approval;
    if ($filter_type && in_array($filter_type, $needs_approval)) {
        $filter_sql  = "AND fs.form_type = ?";
        $filter_vals = array_merge($needs_approval, [$filter_type]);
    }
    $pq = $db->prepare("SELECT fs.id, fs.form_type, fs.form_data, fs.created_at, u.name AS submitted_by
        FROM form_submissions fs LEFT JOIN users u ON u.id = fs.user_id
        WHERE fs.form_type IN ($in) AND fs.approval_status='pending'
        $filter_sql ORDER BY fs.created_at ASC");
    $pq->execute($filter_vals);
    $pending_rows = $pq->fetchAll();
} else {
    $filter_type = '';
}

// ── My recent submissions (non-admin finance users) ───────────────────────────
$my_recent = [];
if (!is_admin() && can_any_finance()) {
    $mq = $db->prepare("SELECT fs.id, fs.form_type, fs.form_data, fs.created_at, fs.approval_status
        FROM form_submissions fs WHERE fs.user_id=? ORDER BY fs.created_at DESC LIMIT 10");
    $mq->execute([$uid]);
    $my_recent = $mq->fetchAll();
}

// ── Petty cash breakdown ──────────────────────────────────────────────────────
$pc_stats = [];
if (is_admin() || can_any_petty()) {
    $pcq = $db->query("SELECT office, SUM(status='paid') paid_count, COALESCE(SUM(CASE WHEN status='paid' THEN amount END),0) paid_amt, SUM(status='unpaid') unpaid_count, COALESCE(SUM(CASE WHEN status='unpaid' THEN amount END),0) unpaid_amt, COUNT(*) total_count, COALESCE(SUM(amount),0) total_amt FROM petty_cash_requests GROUP BY office");
    foreach ($pcq->fetchAll() as $r) $pc_stats[$r['office']] = $r;
}

// ── Form volume (admin only) ──────────────────────────────────────────────────
$vol_map = [];
if (is_admin()) {
    $vol = $db->query("SELECT form_type, COUNT(*) cnt FROM form_submissions WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) GROUP BY form_type")->fetchAll();
    $vol_map = array_column($vol, 'cnt', 'form_type');
}

// ── Recent activity (admin) ───────────────────────────────────────────────────
$recent = [];
if (is_admin()) {
    $recent = $db->query("SELECT fs.id, fs.form_type, fs.form_data, fs.created_at, fs.approval_status, u.name AS submitted_by
        FROM form_submissions fs LEFT JOIN users u ON u.id = fs.user_id
        ORDER BY fs.created_at DESC LIMIT 15")->fetchAll();
}

// ── Asset summary ─────────────────────────────────────────────────────────────
$asset_stats = null;
if (can('assets')) {
    $asset_stats = $db->query("SELECT COUNT(*) total, SUM(status='active') active, COALESCE(SUM(purchase_value),0) total_value FROM assets")->fetch();
}

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

.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
.stats-row.cols-3{grid-template-columns:repeat(3,1fr)}
.stats-row.cols-2{grid-template-columns:repeat(2,1fr)}
.stats-row.cols-1{grid-template-columns:1fr}
.stat-card{background:#fff;border:1.5px solid #e8eaee;border-radius:14px;padding:22px 24px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent,#FF3D33)}
.stat-val{font-size:32px;font-weight:900;color:#1a1a1a;line-height:1;margin-bottom:6px}
.stat-lbl{font-size:12px;color:#999;font-weight:500;text-transform:uppercase;letter-spacing:.6px}
.stat-sub{font-size:11px;color:#bbb;margin-top:4px}
.stat-icon{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:32px;opacity:.12}

.dash-section{margin-bottom:28px}
.dash-title{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#aaa;margin-bottom:12px;display:flex;align-items:center;gap:10px}
.dash-title span{flex:1;height:1px;background:#e8eaee}

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

.act-btns{display:flex;gap:6px}
.btn-approve{padding:6px 14px;background:#15803d;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s}
.btn-approve:hover{background:#166534}
.btn-reject{padding:6px 14px;background:#fff;color:#b91c1c;border:1.5px solid #fca5a5;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s}
.btn-reject:hover{background:#fff5f5}
.actioned{font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px}
.actioned.approved{background:#f0fdf4;color:#15803d}
.actioned.rejected{background:#fff5f5;color:#b91c1c}

.bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.dash-card{background:#fff;border:1.5px solid #e8eaee;border-radius:14px;overflow:hidden}
.dash-card-head{padding:16px 20px;border-bottom:1px solid #f0f0f0;font-size:14px;font-weight:800;color:#1a1a1a}
.dash-card-body{padding:16px 20px}

.office-block{margin-bottom:16px}
.office-block:last-child{margin-bottom:0}
.office-name{font-size:12px;font-weight:700;color:#555;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px}
.pc-bars{display:flex;border-radius:6px;overflow:hidden;height:10px;margin-bottom:6px}
.pc-bar-paid{background:#15803d;transition:width .4s}
.pc-bar-unpaid{background:#fca5a5}
.pc-legend{display:flex;justify-content:space-between;font-size:11px;color:#aaa}
.pc-legend b{color:#333}

.vol-bars{display:flex;flex-direction:column;gap:10px}
.vol-row{display:flex;align-items:center;gap:10px}
.vol-label{font-size:12px;color:#555;width:130px;flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vol-bar-wrap{flex:1;background:#f4f5f7;border-radius:4px;height:8px;overflow:hidden}
.vol-bar-fill{height:100%;background:#FF3D33;border-radius:4px;transition:width .4s}
.vol-count{font-size:12px;font-weight:700;color:#555;width:24px;text-align:right;flex-shrink:0}

.feed-list{display:flex;flex-direction:column}
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

@media(max-width:900px){.stats-row,.stats-row.cols-3,.stats-row.cols-4{grid-template-columns:1fr 1fr}.bottom-grid{grid-template-columns:1fr}}
@media(max-width:560px){.stats-row,.stats-row.cols-3{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php require '_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <span class="topbar-title">Dashboard</span>
  <span style="font-size:12px;color:#aaa"><?= date('l, d F Y') ?></span>
</div>

<div style="padding:28px 32px;max-width:1300px">

<?php
// Build visible stat cards
$stat_cards = [];
if (is_finance_admin()) {
    $stat_cards[] = ['val'=>$pending_count,'lbl'=>'Pending Approvals','sub'=>'Awaiting finance review','icon'=>'⏳','accent'=>'#FF3D33'];
}
if (is_admin()) {
    $stat_cards[] = ['val'=>$month_count,'lbl'=>'Submissions This Month','sub'=>date('F Y'),'icon'=>'📋','accent'=>'#0891b2'];
}
if (is_admin() || can_any_petty()) {
    $stat_cards[] = ['val'=>'QAR '.number_format($pc_doha,0).'<span style="font-size:13px;color:#aaa;display:block;margin-top:2px">USD '.number_format($pc_beirut,0).'</span>','lbl'=>'Petty Cash Unpaid','sub'=>'Doha + Beirut','icon'=>'💰','accent'=>'#d97706','raw'=>true];
}
if (can('assets')) {
    $stat_cards[] = ['val'=>$warranty_soon,'lbl'=>'Warranty Expiring','sub'=>'Within 30 days','icon'=>'🖥','accent'=>$warranty_soon > 0 ? '#b91c1c' : '#15803d','color'=>$warranty_soon > 0 ? '#b91c1c' : '#15803d'];
    if ($asset_stats) {
        $stat_cards[] = ['val'=>(int)$asset_stats['active'],'lbl'=>'Active Assets','sub'=>'of '.(int)$asset_stats['total'].' total','icon'=>'🖥','accent'=>'#7c3aed'];
    }
}
$cols = min(4, count($stat_cards));
$col_class = $cols <= 1 ? 'cols-1' : ($cols === 2 ? 'cols-2' : ($cols === 3 ? 'cols-3' : ''));
?>

<?php if (!empty($stat_cards)): ?>
<div class="stats-row <?= $col_class ?>">
<?php foreach ($stat_cards as $c): ?>
  <div class="stat-card" style="--accent:<?= $c['accent'] ?>">
    <div class="stat-val" <?= isset($c['color']) ? 'style="color:'.$c['color'].'"' : '' ?>><?= $c['val'] ?></div>
    <div class="stat-lbl"><?= $c['lbl'] ?></div>
    <div class="stat-sub"><?= $c['sub'] ?></div>
    <div class="stat-icon"><?= $c['icon'] ?></div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Pending Approvals (finance admin) ── -->
<?php if (is_finance_admin()): ?>
<div class="dash-section">
  <div class="dash-title">Pending Approvals <span></span></div>
  <div class="pend-wrap">
    <div class="pend-header">
      <h2>Requires Action <span style="background:#FF3D33;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;margin-left:6px"><?= $pending_count ?></span></h2>
      <div class="type-filter">
        <a href="/dashboard.php" class="type-btn <?= !$filter_type ? 'active' : '' ?>">All</a>
        <?php foreach ($needs_approval as $t): ?>
        <a href="/dashboard.php?type=<?= $t ?>" class="type-btn <?= $filter_type===$t ? 'active' : '' ?>"><?= $type_icons[$t] ?> <?= $type_labels[$t] ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if (empty($pending_rows)): ?>
    <div class="empty-state"><div class="empty-icon">✅</div><div>No pending approvals<?= $filter_type ? ' for this type' : '' ?>.</div></div>
    <?php else: ?>
    <table class="pend-table">
      <thead><tr>
        <th>#</th><th>Form Type</th><th>Details</th><th>Submitted By</th><th>Date</th><th>Age</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody id="pendingBody">
      <?php foreach ($pending_rows as $row):
        $fd       = json_decode($row['form_data'], true) ?? [];
        $title    = submission_title($fd, $row['form_type']);
        $label    = $type_labels[$row['form_type']] ?? $row['form_type'];
        $icon     = $type_icons[$row['form_type']] ?? '📄';
        $submitted = $row['created_at'] ? new DateTime($row['created_at']) : null;
        $age_days  = $submitted ? (int)(new DateTime())->diff($submitted)->days : 0;
        $age_class = $age_days >= 5 ? 'age-old' : ($age_days >= 2 ? 'age-warn' : '');
      ?>
      <tr id="row-<?= $row['id'] ?>">
        <td style="color:#aaa;font-size:12px">#<?= $row['id'] ?></td>
        <td><span class="form-badge"><?= $icon ?> <?= htmlspecialchars($label) ?></span></td>
        <td style="font-weight:600"><?= htmlspecialchars($title) ?></td>
        <td style="color:#888"><?= htmlspecialchars($row['submitted_by'] ?? 'Public') ?></td>
        <td style="color:#888;font-size:12px;white-space:nowrap"><?= $submitted ? $submitted->format('d M Y') : '—' ?></td>
        <td><span class="<?= $age_class ?>"><?= $age_days ?>d</span></td>
        <td>
          <div class="act-btns" style="justify-content:flex-end">
            <a href="<?= BASE_URL ?>/admin/submissions.php?id=<?= $row['id'] ?>" target="_blank" style="padding:6px 10px;background:#f4f5f7;border-radius:6px;font-size:12px;text-decoration:none;color:#555;font-weight:600">View</a>
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
<?php endif; ?>

<!-- ── Petty Cash Breakdown ── -->
<?php if (!empty($pc_stats) || (is_admin() || can_any_petty())): ?>
<div class="bottom-grid" style="margin-bottom:28px">
  <div class="dash-card">
    <div class="dash-card-head">💰 Petty Cash Breakdown</div>
    <div class="dash-card-body">
      <?php foreach (['doha'=>['🇶🇦','Doha','QAR'], 'beirut'=>['🇱🇧','Beirut','USD']] as $office => [$flag,$name,$cur]):
        $s = $pc_stats[$office] ?? ['paid_count'=>0,'paid_amt'=>0,'unpaid_count'=>0,'unpaid_amt'=>0,'total_count'=>0,'total_amt'=>0];
        $total = (float)$s['total_amt'];
        $paid_pct = $total > 0 ? round(($s['paid_amt'] / $total) * 100) : 0;
      ?>
      <div class="office-block">
        <div class="office-name"><?= $flag ?> <?= $name ?> <span style="font-weight:400;color:#aaa">(<?= $cur ?>)</span></div>
        <div class="pc-bars">
          <div class="pc-bar-paid"   style="width:<?= $paid_pct ?>%"></div>
          <div class="pc-bar-unpaid" style="width:<?= 100-$paid_pct ?>%"></div>
        </div>
        <div class="pc-legend">
          <span>✓ Paid: <b><?= number_format($s['paid_amt'],0) ?></b> (<?= $s['paid_count'] ?>)</span>
          <span>○ Unpaid: <b><?= number_format($s['unpaid_amt'],0) ?></b> (<?= $s['unpaid_count'] ?>)</span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($pc_stats)): ?><div style="text-align:center;color:#bbb;font-size:13px;padding:20px 0">No entries yet.</div><?php endif; ?>
    </div>
  </div>

  <!-- Form Volume (admin) / My Submissions (others) -->
  <?php if (is_admin()): ?>
  <div class="dash-card">
    <div class="dash-card-head">📊 Form Volume — <?= date('F') ?></div>
    <div class="dash-card-body">
      <?php $max_vol = max(1, ...array_values($vol_map + ['_'=>0]));
      foreach ($type_labels as $t => $lbl):
        $cnt = (int)($vol_map[$t] ?? 0);
        $pct = round(($cnt / $max_vol) * 100); ?>
      <div class="vol-row">
        <div class="vol-label"><?= $type_icons[$t] ?> <?= $lbl ?></div>
        <div class="vol-bar-wrap"><div class="vol-bar-fill" style="width:<?= $pct ?>%"></div></div>
        <div class="vol-count"><?= $cnt ?: '<span style="color:#ddd">0</span>' ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php elseif (!empty($my_recent)): ?>
  <div class="dash-card">
    <div class="dash-card-head">📋 My Recent Submissions</div>
    <div class="feed-list">
      <?php foreach ($my_recent as $row):
        $fd    = json_decode($row['form_data'], true) ?? [];
        $title = submission_title($fd, $row['form_type']);
        $label = $type_labels[$row['form_type']] ?? $row['form_type'];
        $icon  = $type_icons[$row['form_type']] ?? '📄';
        $st    = $row['approval_status'];
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
          <div class="feed-meta"><?= htmlspecialchars($label) ?> &nbsp;·&nbsp; <?= $dt ?></div>
        </div>
        <div class="feed-status <?= $st_class ?>"><?= $st_label ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Recent Activity (admin) ── -->
<?php if (is_admin() && !empty($recent)): ?>
<div class="dash-section">
  <div class="dash-title">Recent Activity <span></span></div>
  <div class="pend-wrap">
    <div class="pend-header"><h2>Last 15 Submissions</h2></div>
    <div class="feed-list">
      <?php foreach ($recent as $row):
        $fd    = json_decode($row['form_data'], true) ?? [];
        $title = submission_title($fd, $row['form_type']);
        $label = $type_labels[$row['form_type']] ?? $row['form_type'];
        $icon  = $type_icons[$row['form_type']] ?? '📄';
        $by    = $row['submitted_by'] ?? 'Public';
        $st    = $row['approval_status'];
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
          <div class="feed-meta"><?= htmlspecialchars($label) ?> · <?= htmlspecialchars($by) ?> · <?= $dt ?></div>
        </div>
        <div class="feed-status <?= $st_class ?>"><?= $st_label ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (empty($stat_cards) && empty($pending_rows) && empty($pc_stats) && empty($my_recent)): ?>
<div style="text-align:center;padding:80px 20px;color:#bbb">
  <div style="font-size:48px;margin-bottom:16px">👋</div>
  <div style="font-size:18px;font-weight:700;color:#333;margin-bottom:8px">Welcome, <?= htmlspecialchars($user['name'] ?? '') ?></div>
  <div style="font-size:14px">Use the sidebar to navigate to your tools.</div>
</div>
<?php endif; ?>

</div>
</div>

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
  fetch('/admin/approve.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'id=' + id + '&action=' + status + '&notes=' + encodeURIComponent(notes)
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      if (row) {
        const cls = status === 'approved' ? 'approved' : 'rejected';
        row.querySelector('.act-btns').innerHTML = '<span class="actioned ' + cls + '">' + (status==='approved'?'✓ Approved':'✗ Rejected') + '</span>';
        row.style.opacity = '1';
      }
      const badge = document.querySelector('.pend-header h2 span');
      if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent) - 1);
      const statVal = document.querySelector('.stat-val');
      if (statVal) statVal.textContent = Math.max(0, parseInt(statVal.textContent) - 1);
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
