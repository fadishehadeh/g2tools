<?php
session_start();
require '../config.php';
require_login();
require_can('assets');
require '_lib.php';

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = db()->query("SELECT
    COUNT(*) total,
    SUM(status='active') active,
    SUM(status='in_repair') in_repair,
    SUM(status='retired') retired,
    SUM(status='disposed') disposed,
    SUM(status='lost') lost,
    COALESCE(SUM(purchase_value),0) total_value
  FROM assets")->fetch();

$assigned_count = db()->query("SELECT COUNT(DISTINCT asset_id) FROM asset_assignments WHERE returned_at IS NULL")->fetchColumn();
$pending_disposal = db()->query("SELECT COUNT(*) FROM asset_disposals WHERE approved_by IS NULL")->fetchColumn();
$overdue_maintenance = db()->query("SELECT COUNT(*) FROM asset_maintenance WHERE next_due IS NOT NULL AND next_due < CURDATE()")->fetchColumn();

// ── Category breakdown for chart ─────────────────────────────────────────────
$cat_data = db()->query("SELECT c.name, c.icon, COUNT(a.id) cnt
    FROM asset_categories c LEFT JOIN assets a ON a.category_id=c.id
    GROUP BY c.id ORDER BY cnt DESC LIMIT 8")->fetchAll();

// ── Status breakdown for chart ────────────────────────────────────────────────
$status_data = db()->query("SELECT status, COUNT(*) cnt FROM assets GROUP BY status")->fetchAll();

// ── Monthly additions (last 6 months) ─────────────────────────────────────────
$monthly = db()->query("SELECT DATE_FORMAT(created_at,'%b %Y') mo, COUNT(*) cnt
    FROM assets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY created_at")->fetchAll();

// ── Recent activity ───────────────────────────────────────────────────────────
$recent_log = db()->query("SELECT al.*, a.name asset_name, a.tag, u.name uname
    FROM asset_activity_log al
    JOIN assets a ON a.id=al.asset_id
    LEFT JOIN users u ON u.id=al.user_id
    ORDER BY al.created_at DESC LIMIT 10")->fetchAll();

// ── Assets needing attention ──────────────────────────────────────────────────
$attention = db()->query("SELECT a.*, c.icon cat_icon
    FROM assets a LEFT JOIN asset_categories c ON c.id=a.category_id
    WHERE a.status='in_repair'
       OR (a.warranty_expiry IS NOT NULL AND a.warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
    LIMIT 5")->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Assets — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box}
.pw{padding:28px 36px 60px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.btn-new{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#FF3D33;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none}
.btn-new:hover{background:#c0170e}

/* Stat cards */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:12px;margin-bottom:22px}
.sc{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:18px 20px;position:relative;overflow:hidden}
.sc::before{content:'';position:absolute;top:0;left:0;width:3px;height:100%;background:var(--accent,#e8eaee)}
.sc .sv{font-size:28px;font-weight:900;color:#1a1a1a;letter-spacing:-1px;margin-bottom:2px}
.sc .sl{font-size:11px;color:#aaa;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.sc .ss{font-size:11px;color:#bbb;margin-top:3px}

/* Charts grid */
.chart-grid{display:grid;grid-template-columns:1fr 1fr 1.4fr;gap:16px;margin-bottom:20px}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:18px 20px}
.panel h2{font-size:12px;font-weight:800;color:#1a1a1a;margin:0 0 14px;text-transform:uppercase;letter-spacing:.5px}
.chart-wrap{position:relative;height:180px}

/* Quick nav */
.qnav{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.qn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;background:#fff;border:1.5px solid #e8eaee;border-radius:8px;font-size:12.5px;font-weight:600;color:#444;text-decoration:none}
.qn:hover{border-color:#aaa;color:#1a1a1a}
.qn-alert{border-color:#fca5a5;color:#dc2626;background:#fff8f8}

/* Bottom grid */
.bottom-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:16px}

/* Activity log */
.log-item{display:flex;gap:10px;padding:9px 0;border-bottom:1px solid #f5f6f8;font-size:12.5px;align-items:flex-start}
.log-item:last-child{border-bottom:none}
.log-dot{width:8px;height:8px;border-radius:50%;background:#e8eaee;flex-shrink:0;margin-top:4px}
.log-dot.created{background:#16a34a}.log-dot.assigned{background:#0891b2}.log-dot.maintenance{background:#d97706}
.log-dot.status_change{background:#7c3aed}.log-dot.disposed{background:#dc2626}.log-dot.transferred{background:#0891b2}
.log-body{flex:1}
.log-asset{font-weight:700;color:#1a1a1a}
.log-detail{color:#888}
.log-ts{font-size:11px;color:#ccc;flex-shrink:0;white-space:nowrap}

/* Attention items */
.att-item{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f5f6f8;font-size:13px}
.att-item:last-child{border-bottom:none}
.att-icon{font-size:18px;flex-shrink:0}
.att-name{font-weight:600;color:#1a1a1a}
.att-sub{font-size:11px;color:#aaa}
.att-badge{margin-left:auto;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/">G2 Tools</a>
  <span class="topbar-title">Asset Management</span>
</div>
<div class="pw">

  <div class="ph">
    <h1>Asset Dashboard</h1>
    <?php if (is_it_admin() || is_admin()): ?>
    <a class="btn-new" href="add.php">+ Add Asset</a>
    <?php endif; ?>
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="sc" style="--accent:#16a34a"><div class="sv"><?= $stats['active'] ?></div><div class="sl">Active</div><div class="ss"><?= $stats['total'] ?> total</div></div>
    <div class="sc" style="--accent:#d97706"><div class="sv"><?= $stats['in_repair'] ?></div><div class="sl">In Repair</div></div>
    <div class="sc" style="--accent:#0891b2"><div class="sv"><?= $assigned_count ?></div><div class="sl">Assigned</div></div>
    <div class="sc" style="--accent:#888"><div class="sv"><?= $stats['retired'] ?></div><div class="sl">Retired</div></div>
    <div class="sc" style="--accent:#FF3D33"><div class="sv"><?= $stats['disposed'] ?></div><div class="sl">Disposed</div></div>
    <div class="sc" style="--accent:#9333ea"><div class="sv"><?= $stats['lost'] ?></div><div class="sl">Lost</div></div>
    <div class="sc" style="--accent:#1d6f42"><div class="sv" style="font-size:18px">QAR <?= number_format($stats['total_value'] / 1000, 1) ?>k</div><div class="sl">Total Value</div></div>
    <?php if ($overdue_maintenance): ?><div class="sc" style="--accent:#f59e0b;background:#fffbeb"><div class="sv" style="color:#d97706"><?= $overdue_maintenance ?></div><div class="sl" style="color:#d97706">Maintenance Due</div></div><?php endif; ?>
  </div>

  <!-- Charts -->
  <div class="chart-grid">
    <div class="panel">
      <h2>By Status</h2>
      <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
    </div>
    <div class="panel">
      <h2>By Category</h2>
      <div class="chart-wrap"><canvas id="catChart"></canvas></div>
    </div>
    <div class="panel">
      <h2>Assets Added (Last 6 Months)</h2>
      <div class="chart-wrap"><canvas id="monthChart"></canvas></div>
    </div>
  </div>

  <!-- Quick nav -->
  <div class="qnav">
    <a class="qn" href="list.php">📋 All Assets</a>
    <?php if (is_it_admin() || is_admin()): ?>
    <a class="qn" href="add.php">➕ Add Asset</a>
    <a class="qn" href="import.php">⬆ Bulk Import</a>
    <a class="qn" href="qr-labels.php">🔲 QR Labels</a>
    <a class="qn" href="transfer.php">↔ Transfer</a>
    <a class="qn" href="disposal.php">🗑 Disposal</a>
    <a class="qn" href="lookups.php">🏷️ Lookups</a>
    <a class="qn" href="custom-fields.php">⚙ Custom Fields</a>
    <?php endif; ?>
    <a class="qn" href="depreciation.php">📉 Depreciation</a>
    <a class="qn" href="report.php">📊 Reports</a>
    <a class="qn" href="audit-log.php">📜 Audit Log</a>
    <?php if ($overdue_maintenance): ?><a class="qn qn-alert" href="report.php?view=maintenance">⚠ <?= $overdue_maintenance ?> Maintenance Due</a><?php endif; ?>
    <?php if ($pending_disposal): ?><a class="qn qn-alert" href="disposal.php">🗑 <?= $pending_disposal ?> Pending Disposal</a><?php endif; ?>
  </div>

  <!-- Bottom grid -->
  <div class="bottom-grid">
    <!-- Recent activity -->
    <div class="panel">
      <h2>Recent Activity</h2>
      <?php foreach ($recent_log as $l): ?>
      <div class="log-item">
        <div class="log-dot <?= $l['action'] ?>"></div>
        <div class="log-body">
          <span class="log-asset"><a href="view.php?id=<?= $l['asset_id'] ?>" style="color:inherit;text-decoration:none"><?= htmlspecialchars($l['asset_name']) ?></a></span>
          <span class="log-detail"> — <?= htmlspecialchars($l['action']) ?><?= $l['detail'] ? ': '.htmlspecialchars(substr($l['detail'],0,60)) : '' ?></span>
          <?php if ($l['uname']): ?><span style="color:#bbb;font-size:11px"> by <?= htmlspecialchars($l['uname']) ?></span><?php endif; ?>
        </div>
        <div class="log-ts"><?= date('d M', strtotime($l['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$recent_log): ?><p style="color:#ccc;font-size:13px;text-align:center;padding:24px 0">No activity yet.</p><?php endif; ?>
    </div>

    <!-- Needs attention -->
    <div class="panel">
      <h2>Needs Attention</h2>
      <?php foreach ($attention as $a): ?>
      <div class="att-item">
        <div class="att-icon"><?= $a['cat_icon'] ?? '📦' ?></div>
        <div>
          <div class="att-name"><a href="view.php?id=<?= $a['id'] ?>" style="color:inherit;text-decoration:none"><?= htmlspecialchars($a['name']) ?></a></div>
          <div class="att-sub"><?= htmlspecialchars($a['tag']) ?></div>
        </div>
        <?php if ($a['status']==='in_repair'): ?>
        <span class="att-badge" style="background:#fffbeb;color:#d97706">In Repair</span>
        <?php elseif ($a['warranty_expiry']): ?>
        <span class="att-badge" style="background:#fef2f2;color:#dc2626">Warranty Expiring</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if (!$attention): ?><p style="color:#ccc;font-size:13px;text-align:center;padding:24px 0">All clear ✓</p><?php endif; ?>
    </div>
  </div>

</div>
</div>
<script>
const RED='#FF3D33',BLUE='#0891b2',GREEN='#16a34a',AMBER='#d97706',GREY='#888',PURPLE='#9333ea';

// Status donut
new Chart(document.getElementById('statusChart'),{
  type:'doughnut',
  data:{
    labels:<?= json_encode(array_column($status_data,'status')) ?>,
    datasets:[{data:<?= json_encode(array_column($status_data,'cnt')) ?>,
      backgroundColor:[GREEN,AMBER,GREY,RED,PURPLE,'#e8eaee'],borderWidth:2,borderColor:'#fff'}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:10}}}}
});

// Category donut
new Chart(document.getElementById('catChart'),{
  type:'doughnut',
  data:{
    labels:<?= json_encode(array_map(fn($r)=>$r['icon'].' '.$r['name'],$cat_data)) ?>,
    datasets:[{data:<?= json_encode(array_column($cat_data,'cnt')) ?>,
      backgroundColor:['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#84cc16','#f97316'],borderWidth:2,borderColor:'#fff'}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:10}}}}
});

// Monthly bar
new Chart(document.getElementById('monthChart'),{
  type:'bar',
  data:{
    labels:<?= json_encode(array_column($monthly,'mo')) ?>,
    datasets:[{label:'Assets Added',data:<?= json_encode(array_column($monthly,'cnt')) ?>,
      backgroundColor:RED,borderRadius:5}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
    scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}
});
</script>
</body>
</html>
