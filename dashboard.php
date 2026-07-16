<?php
session_start();
require 'config.php';
require_login();

$db   = db();
$user = current_user();
$uid  = $user['id'];

$needs_approval = ['amex','debit_note','credit_note','vendor_recon','vendor_reg','client_reg'];
$in = implode(',', array_fill(0, count($needs_approval), '?'));

// ── Pending count (finance admin) ─────────────────────────────────────────────
$pending_count = 0;
if (is_finance_admin()) {
    $s = $db->prepare("SELECT COUNT(*) FROM form_submissions WHERE form_type IN ($in) AND approval_status='pending'");
    $s->execute($needs_approval);
    $pending_count = (int)$s->fetchColumn();
}

// ── Month count ───────────────────────────────────────────────────────────────
if (is_admin()) {
    $month_count = (int)$db->query("SELECT COUNT(*) FROM form_submissions WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
} elseif (can_any_finance()) {
    $sq = $db->prepare("SELECT COUNT(*) FROM form_submissions WHERE user_id=? AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
    $sq->execute([$uid]);
    $month_count = (int)$sq->fetchColumn();
} else {
    $month_count = 0;
}

// ── 3 oldest pending approvals (finance admin) ────────────────────────────────
$pending_rows = [];
if (is_finance_admin()) {
    $pq = $db->prepare("
        SELECT fs.id, fs.form_type, fs.form_data, fs.created_at, u.name AS submitted_by
        FROM form_submissions fs LEFT JOIN users u ON u.id = fs.user_id
        WHERE fs.form_type IN ($in) AND fs.approval_status='pending'
        ORDER BY fs.created_at ASC LIMIT 3");
    $pq->execute($needs_approval);
    $pending_rows = $pq->fetchAll();
}

// ── 3 recent submissions ──────────────────────────────────────────────────────
if (is_admin()) {
    $recent = $db->query("
        SELECT fs.id, fs.form_type, fs.form_data, fs.created_at, fs.approval_status, u.name AS submitted_by
        FROM form_submissions fs LEFT JOIN users u ON u.id = fs.user_id
        ORDER BY fs.created_at DESC LIMIT 3")->fetchAll();
} elseif (can_any_finance()) {
    $rq = $db->prepare("SELECT id, form_type, form_data, created_at, approval_status FROM form_submissions WHERE user_id=? ORDER BY created_at DESC LIMIT 3");
    $rq->execute([$uid]);
    $recent = $rq->fetchAll();
    foreach ($recent as &$r) $r['submitted_by'] = $user['name'];
    unset($r);
} else {
    $recent = [];
}

// ── Helpers ───────────────────────────────────────────────────────────────────
$type_labels = [
    'amex'=>'Credit Card Auth','accountability'=>'Accountability','debit_note'=>'Debit Note',
    'credit_note'=>'Credit Note','vendor_recon'=>'Vendor Recon','vendor_reg'=>'Vendor Registration',
    'client_reg'=>'Client Registration',
];
$type_icons = [
    'amex'=>'💳','accountability'=>'📦','debit_note'=>'📄','credit_note'=>'📋',
    'vendor_recon'=>'📊','vendor_reg'=>'🏢','client_reg'=>'👥',
];
function submission_title(array $fd, string $type): string {
    return match($type) {
        'amex'           => $fd['merchant'] ?? '—',
        'accountability' => $fd['received_by'] ?? $fd['item_name'] ?? '—',
        'debit_note','credit_note' => $fd['to_name'] ?? '—',
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
.page{padding:32px 36px;max-width:1100px}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:32px;max-width:600px}
.stat-card{background:#fff;border:1.5px solid #e8eaee;border-radius:16px;padding:26px 28px;position:relative;overflow:hidden;text-decoration:none;display:block;transition:.15s}
.stat-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.07);transform:translateY(-1px)}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent)}
.stat-val{font-size:38px;font-weight:900;color:#1a1a1a;line-height:1;margin-bottom:6px}
.stat-lbl{font-size:12px;color:#999;font-weight:600;text-transform:uppercase;letter-spacing:.7px}
.stat-sub{font-size:11px;color:#ccc;margin-top:4px}
.stat-icon{position:absolute;right:22px;top:50%;transform:translateY(-50%);font-size:36px;opacity:.1}

/* Sections */
.section{margin-bottom:32px}
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.section-title{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#aaa}
.section-link{font-size:12px;color:#FF3D33;font-weight:700;text-decoration:none}
.section-link:hover{text-decoration:underline}

/* Cards */
.sub-list{display:flex;flex-direction:column;gap:8px}
.sub-card{background:#fff;border:1.5px solid #e8eaee;border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit;transition:.12s}
.sub-card:hover{border-color:#FF3D33;box-shadow:0 2px 12px rgba(255,61,51,.08)}
.sub-card-icon{width:36px;height:36px;border-radius:9px;background:#f4f5f7;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.sub-card-main{flex:1;min-width:0}
.sub-card-title{font-size:13px;font-weight:700;color:#1a1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sub-card-meta{font-size:11px;color:#aaa;margin-top:3px}
.sub-card-right{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0}
.age-badge{font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px}
.age-fresh{background:#f4f5f7;color:#888}
.age-warn{background:#fffbeb;color:#d97706}
.age-old{background:#fff5f5;color:#b91c1c}
.status-pill{font-size:11px;font-weight:700;padding:3px 9px;border-radius:10px}
.sp-pending{background:#fffbeb;color:#d97706}
.sp-approved{background:#f0fdf4;color:#15803d}
.sp-rejected{background:#fff5f5;color:#b91c1c}
.sp-na{background:#f4f5f7;color:#aaa}
.arrow{color:#ccc;font-size:16px;margin-left:4px}

/* Empty */
.empty{padding:32px;text-align:center;color:#bbb;font-size:13px;background:#fff;border:1.5px solid #e8eaee;border-radius:12px}
.empty-icon{font-size:32px;margin-bottom:8px}

/* Welcome (no content) */
.welcome{text-align:center;padding:80px 20px}
.welcome-icon{font-size:52px;margin-bottom:16px}
.welcome h2{font-size:22px;font-weight:900;color:#1a1a1a;margin-bottom:8px}
.welcome p{font-size:14px;color:#aaa}

@media(max-width:600px){.stats-row{grid-template-columns:1fr}.page{padding:20px 16px}}
</style>
</head>
<body>
<?php require '_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <span class="topbar-title">Dashboard</span>
  <span style="font-size:12px;color:#aaa"><?= date('l, d F Y') ?></span>
</div>
<div class="page">

<?php $has_content = is_finance_admin() || can_any_finance() || is_admin(); ?>
<?php if (!$has_content): ?>
<div class="welcome">
  <div class="welcome-icon">👋</div>
  <h2>Welcome, <?= htmlspecialchars($user['name'] ?? '') ?></h2>
  <p>Use the sidebar to navigate to your tools.</p>
</div>
<?php else: ?>

<!-- Stats -->
<?php
$cards = [];
if (is_finance_admin()) $cards[] = ['val'=>$pending_count,'lbl'=>'Pending Approvals','sub'=>'Awaiting review','icon'=>'⏳','accent'=>'#FF3D33','href'=>'/admin/submissions.php'];
if (is_admin() || can_any_finance()) $cards[] = ['val'=>$month_count,'lbl'=>'Submissions This Month','sub'=>date('F Y'),'icon'=>'📋','accent'=>'#0891b2','href'=>is_admin()?'/admin/submissions.php':'/history.php'];
?>
<?php if (!empty($cards)): ?>
<div class="stats-row" style="grid-template-columns:repeat(<?= count($cards) ?>,1fr)">
<?php foreach ($cards as $c): ?>
<a class="stat-card" href="<?= $c['href'] ?>" style="--accent:<?= $c['accent'] ?>">
  <div class="stat-val"><?= $c['val'] ?></div>
  <div class="stat-lbl"><?= $c['lbl'] ?></div>
  <div class="stat-sub"><?= $c['sub'] ?></div>
  <div class="stat-icon"><?= $c['icon'] ?></div>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Pending Approvals -->
<?php if (is_finance_admin()): ?>
<div class="section">
  <div class="section-head">
    <div class="section-title">Pending Approvals <?php if ($pending_count > 0): ?><span style="background:#FF3D33;color:#fff;font-size:10px;padding:1px 7px;border-radius:9px;margin-left:6px;vertical-align:middle"><?= $pending_count ?></span><?php endif; ?></div>
    <?php if ($pending_count > 3): ?><a class="section-link" href="/admin/submissions.php">View all <?= $pending_count ?> →</a><?php endif; ?>
  </div>
  <?php if (empty($pending_rows)): ?>
  <div class="empty"><div class="empty-icon">✅</div>No pending approvals.</div>
  <?php else: ?>
  <div class="sub-list">
  <?php foreach ($pending_rows as $row):
    $fd       = json_decode($row['form_data'], true) ?? [];
    $title    = submission_title($fd, $row['form_type']);
    $label    = $type_labels[$row['form_type']] ?? $row['form_type'];
    $icon     = $type_icons[$row['form_type']] ?? '📄';
    $submitted = $row['created_at'] ? new DateTime($row['created_at']) : null;
    $age_days  = $submitted ? (int)(new DateTime())->diff($submitted)->days : 0;
    $age_class = $age_days >= 5 ? 'age-old' : ($age_days >= 2 ? 'age-warn' : 'age-fresh');
    $age_label = $age_days === 0 ? 'Today' : ($age_days === 1 ? '1d ago' : "{$age_days}d ago");
  ?>
  <a class="sub-card" href="/admin/submission-view.php?id=<?= $row['id'] ?>">
    <div class="sub-card-icon"><?= $icon ?></div>
    <div class="sub-card-main">
      <div class="sub-card-title"><?= htmlspecialchars($title) ?></div>
      <div class="sub-card-meta"><?= htmlspecialchars($label) ?> &nbsp;·&nbsp; <?= htmlspecialchars($row['submitted_by'] ?? 'Public') ?> &nbsp;·&nbsp; <?= $submitted ? $submitted->format('d M Y') : '—' ?></div>
    </div>
    <div class="sub-card-right">
      <span class="age-badge <?= $age_class ?>"><?= $age_label ?></span>
    </div>
    <span class="arrow">›</span>
  </a>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Recent Submissions -->
<?php if (!empty($recent)): ?>
<div class="section">
  <div class="section-head">
    <div class="section-title">Recent Submissions</div>
    <a class="section-link" href="<?= is_admin() ? '/admin/submissions.php' : '/history.php' ?>">View all →</a>
  </div>
  <div class="sub-list">
  <?php foreach ($recent as $row):
    $fd    = json_decode($row['form_data'], true) ?? [];
    $title = submission_title($fd, $row['form_type']);
    $label = $type_labels[$row['form_type']] ?? $row['form_type'];
    $icon  = $type_icons[$row['form_type']] ?? '📄';
    $by    = $row['submitted_by'] ?? 'Public';
    $st    = $row['approval_status'] ?? '';
    $needs_ap = in_array($row['form_type'], $needs_approval);
    if (!$needs_ap) { $st_label = '—'; $st_class = 'sp-na'; }
    elseif ($st==='approved') { $st_label = '✓ Approved'; $st_class = 'sp-approved'; }
    elseif ($st==='rejected') { $st_label = '✗ Rejected'; $st_class = 'sp-rejected'; }
    else { $st_label = '⏳ Pending'; $st_class = 'sp-pending'; }
    $dt = $row['created_at'] ? (new DateTime($row['created_at']))->format('d M Y') : '—';
  ?>
  <a class="sub-card" href="/admin/submission-view.php?id=<?= $row['id'] ?>">
    <div class="sub-card-icon"><?= $icon ?></div>
    <div class="sub-card-main">
      <div class="sub-card-title"><?= htmlspecialchars($title) ?></div>
      <div class="sub-card-meta"><?= htmlspecialchars($label) ?> &nbsp;·&nbsp; <?= is_admin() ? htmlspecialchars($by) . ' &nbsp;·&nbsp; ' : '' ?><?= $dt ?></div>
    </div>
    <div class="sub-card-right">
      <?php if ($needs_ap): ?><span class="status-pill <?= $st_class ?>"><?= $st_label ?></span><?php endif; ?>
    </div>
    <span class="arrow">›</span>
  </a>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>
</div>
</div>
</body>
</html>
