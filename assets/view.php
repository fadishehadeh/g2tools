<?php
session_start();
require '../config.php';
require_login();
require_can('assets');

$id = (int)($_GET['id'] ?? 0);
$a  = db()->prepare("SELECT a.*, c.name cat_name, c.icon cat_icon, l.name loc_name, d.name dept_name
  FROM assets a
  LEFT JOIN asset_categories c ON c.id=a.category_id
  LEFT JOIN asset_locations l ON l.id=a.location_id
  LEFT JOIN asset_departments d ON d.id=a.department_id
  WHERE a.id=?");
$a->execute([$id]); $a = $a->fetch();
if (!$a) { header('Location: list.php'); exit; }

// Current assignment
$assign = db()->prepare("SELECT aa.*, u.name emp_name FROM asset_assignments aa JOIN users u ON u.id=aa.user_id WHERE aa.asset_id=? AND aa.returned_at IS NULL ORDER BY aa.assigned_at DESC LIMIT 1");
$assign->execute([$id]); $assign = $assign->fetch();

// Assignment history
$history = db()->query("SELECT aa.*, u.name emp_name, ab.name by_name FROM asset_assignments aa JOIN users u ON u.id=aa.user_id LEFT JOIN users ab ON ab.id=aa.assigned_by WHERE aa.asset_id=$id ORDER BY aa.assigned_at DESC")->fetchAll();

// Maintenance
$maint = db()->query("SELECT m.*, u.name by_name FROM asset_maintenance m LEFT JOIN users u ON u.id=m.performed_by WHERE m.asset_id=$id ORDER BY m.performed_at DESC")->fetchAll();

// Activity log
$logs = db()->query("SELECT al.*, u.name uname FROM asset_activity_log al LEFT JOIN users u ON u.id=al.user_id WHERE al.asset_id=$id ORDER BY al.created_at DESC LIMIT 30")->fetchAll();

$users_list = db()->query("SELECT id,name FROM users WHERE is_active=1 ORDER BY name")->fetchAll();

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
require '_lib.php';

// Depreciation schedule
$depr_schedule = asset_depreciation_schedule($a);
$book_value = asset_book_value($a);

// Transfer history
$transfers = db()->query("SELECT at2.*, fl.name from_loc, tl.name to_loc, fd.name from_dept, td.name to_dept, tu.name to_user_name, tb.name by_name
    FROM asset_transfers at2
    LEFT JOIN asset_locations fl ON fl.id=at2.from_location_id
    LEFT JOIN asset_locations tl ON tl.id=at2.to_location_id
    LEFT JOIN asset_departments fd ON fd.id=at2.from_department_id
    LEFT JOIN asset_departments td ON td.id=at2.to_department_id
    LEFT JOIN users tu ON tu.id=at2.to_user_id
    LEFT JOIN users tb ON tb.id=at2.transferred_by
    WHERE at2.asset_id=$id ORDER BY at2.transferred_at DESC")->fetchAll();

// Disposal
$disposal = db()->query("SELECT ad.*, u.name by_name, ab.name approved_by_name FROM asset_disposals ad LEFT JOIN users u ON u.id=ad.disposed_by LEFT JOIN users ab ON ab.id=ad.approved_by WHERE ad.asset_id=$id LIMIT 1")->fetch();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign' && is_it_admin()) {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            // Return current if any
            if ($assign) db()->prepare("UPDATE asset_assignments SET returned_at=NOW() WHERE id=?")->execute([$assign['id']]);
            db()->prepare("INSERT INTO asset_assignments (asset_id,user_id,assigned_by,notes) VALUES (?,?,?,?)")
              ->execute([$id,$uid,$_SESSION['g2_user']['id'],trim($_POST['assign_notes']??'')]);
            db()->prepare("INSERT INTO asset_activity_log (asset_id,user_id,action,detail) VALUES (?,?,?,?)")
              ->execute([$id,$_SESSION['g2_user']['id'],'assigned','Assigned to user #'.$uid]);
            $_SESSION['flash'] = ['type'=>'ok','msg'=>'Asset assigned.'];
        }
    } elseif ($action === 'unassign' && is_it_admin() && $assign) {
        db()->prepare("UPDATE asset_assignments SET returned_at=NOW() WHERE id=?")->execute([$assign['id']]);
        db()->prepare("INSERT INTO asset_activity_log (asset_id,user_id,action,detail) VALUES (?,?,?,?)")
          ->execute([$id,$_SESSION['g2_user']['id'],'returned','Asset returned']);
        $_SESSION['flash'] = ['type'=>'ok','msg'=>'Asset returned.'];
    } elseif ($action === 'status' && is_it_admin()) {
        $ns = $_POST['new_status'] ?? '';
        $allowed = ['active','in_repair','retired','disposed','lost'];
        if (in_array($ns, $allowed)) {
            db()->prepare("UPDATE assets SET status=? WHERE id=?")->execute([$ns,$id]);
            db()->prepare("INSERT INTO asset_activity_log (asset_id,user_id,action,detail) VALUES (?,?,?,?)")
              ->execute([$id,$_SESSION['g2_user']['id'],'status_change','Status changed to '.$ns]);
            $_SESSION['flash'] = ['type'=>'ok','msg'=>'Status updated.'];
        }
    } elseif ($action === 'maintenance' && is_it_admin()) {
        $desc = trim($_POST['maint_desc'] ?? '');
        $date = $_POST['maint_date'] ?? date('Y-m-d');
        if ($desc) {
            db()->prepare("INSERT INTO asset_maintenance (asset_id,type,description,cost,vendor,performed_at,next_due,performed_by) VALUES (?,?,?,?,?,?,?,?)")
              ->execute([$id,$_POST['maint_type']??'service',$desc,($_POST['maint_cost']?:(null)),$_POST['maint_vendor']??null,$date,$_POST['maint_next']??null,$_SESSION['g2_user']['id']]);
            db()->prepare("INSERT INTO asset_activity_log (asset_id,user_id,action,detail) VALUES (?,?,?,?)")
              ->execute([$id,$_SESSION['g2_user']['id'],'maintenance',$desc]);
            $_SESSION['flash'] = ['type'=>'ok','msg'=>'Maintenance record added.'];
        }
    }
    header("Location: view.php?id=$id"); exit;
}

$status_colors = ['active'=>['#f0fdf4','#16a34a'],'in_repair'=>['#fffbeb','#d97706'],'retired'=>['#f5f6f8','#888'],'disposed'=>['#fef2f2','#dc2626'],'lost'=>['#fdf4ff','#9333ea']];
[$sbg,$sfg] = $status_colors[$a['status']] ?? ['#f5f6f8','#888'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($a['name']) ?> — Assets</title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<link rel="stylesheet" href="/g2forms/form.css">
<style>
.pw{padding:28px 36px 60px;max-width:960px}
.asset-hero{display:flex;align-items:flex-start;gap:20px;margin-bottom:24px;flex-wrap:wrap}
.asset-icon{width:64px;height:64px;background:#f5f6f8;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:30px;flex-shrink:0}
.asset-title h1{font-size:22px;font-weight:800;color:#1a1a1a;margin:0 0 4px}
.asset-tag{font-size:13px;color:#aaa;font-family:monospace;margin-bottom:8px}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:20px 22px;margin-bottom:16px}
.panel h2{font-size:13px;font-weight:800;color:#1a1a1a;margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid #f0f1f3}
.dl{display:grid;grid-template-columns:140px 1fr;gap:8px 12px;font-size:13px}
.dl dt{color:#aaa;font-weight:600}
.dl dd{color:#1a1a1a;margin:0}
.maint-row{padding:10px 0;border-bottom:1px solid #f5f6f8;font-size:13px}
.maint-row:last-child{border-bottom:none}
.log-row{display:flex;gap:10px;padding:7px 0;border-bottom:1px solid #f5f6f8;font-size:12.5px}
.log-row:last-child{border-bottom:none}
.log-action{font-weight:700;color:#1a1a1a;white-space:nowrap}
.log-ts{color:#ccc;font-size:11px;white-space:nowrap}
.status-badge{display:inline-block;padding:3px 11px;border-radius:20px;font-size:12px;font-weight:700}
.flash{padding:11px 16px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:16px}
.flash-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.btn-sm{padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;border:none;cursor:pointer;text-decoration:none;display:inline-block}
.btn-red{background:#FF3D33;color:#fff}.btn-red:hover{background:#c0170e}
.btn-grey{background:#f1f5f9;color:#444}.btn-grey:hover{background:#e2e8f0}
.btn-edit-link{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;background:#f1f5f9;color:#444;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none}
.btn-edit-link:hover{background:#e2e8f0}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="list.php">← Assets</a>
  <span class="topbar-title"><?= htmlspecialchars($a['name']) ?></span>
</div>
<div class="pw">

  <?php if ($flash): ?>
  <div class="flash flash-ok"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>
  <?php if ($_GET['created'] ?? false): ?>
  <div class="flash flash-ok">✅ Asset created successfully.</div>
  <?php endif; ?>

  <div class="asset-hero">
    <div class="asset-icon"><?= $a['cat_icon'] ?? '📦' ?></div>
    <div class="asset-title">
      <h1><?= htmlspecialchars($a['name']) ?></h1>
      <div class="asset-tag"><?= htmlspecialchars($a['tag']) ?></div>
      <span class="status-badge" style="background:<?= $sbg ?>;color:<?= $sfg ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span>
    </div>
    <?php if (is_it_admin()): ?>
    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn-edit-link" href="edit.php?id=<?= $id ?>">✏️ Edit</a>
      <form method="POST" style="display:flex;gap:6px;align-items:center">
        <input type="hidden" name="action" value="status">
        <select name="new_status" style="padding:7px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:12px;font-family:inherit">
          <?php foreach (['active','in_repair','retired','disposed','lost'] as $s): ?>
          <option value="<?= $s ?>" <?= $a['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn-sm btn-grey" type="submit">Set Status</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <!-- Details -->
  <div class="panel">
    <h2>Details</h2>
    <dl class="dl">
      <dt>Brand / Model</dt><dd><?= htmlspecialchars(implode(' ', array_filter([$a['brand'],$a['model']])) ?: '—') ?></dd>
      <dt>Serial Number</dt><dd><?= htmlspecialchars($a['serial_number'] ?? '—') ?></dd>
      <dt>Category</dt><dd><?= $a['cat_icon'] ?? '' ?> <?= htmlspecialchars($a['cat_name'] ?? '—') ?></dd>
      <dt>Location</dt><dd><?= htmlspecialchars($a['loc_name'] ?? '—') ?></dd>
      <dt>Department</dt><dd><?= htmlspecialchars($a['dept_name'] ?? '—') ?></dd>
      <dt>Purchase Date</dt><dd><?= $a['purchase_date'] ? date('d M Y', strtotime($a['purchase_date'])) : '—' ?></dd>
      <dt>Purchase Value</dt><dd><?= $a['purchase_value'] ? 'QAR '.number_format($a['purchase_value'],2) : '—' ?></dd>
      <dt>Warranty Expiry</dt><dd><?= $a['warranty_expiry'] ? date('d M Y', strtotime($a['warranty_expiry'])) : '—' ?></dd>
      <?php if ($a['notes']): ?><dt>Notes</dt><dd><?= nl2br(htmlspecialchars($a['notes'])) ?></dd><?php endif; ?>
    </dl>
  </div>

  <!-- Assignment -->
  <div class="panel">
    <h2>Assignment</h2>
    <?php if ($assign): ?>
    <p style="font-size:13px;margin:0 0 12px">Currently assigned to <strong><?= htmlspecialchars($assign['emp_name']) ?></strong> since <?= date('d M Y', strtotime($assign['assigned_at'])) ?></p>
    <?php if (is_it_admin()): ?>
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="unassign">
      <button class="btn-sm btn-grey" type="submit">Mark as Returned</button>
    </form>
    <?php endif; ?>
    <?php else: ?>
    <p style="font-size:13px;color:#aaa;margin:0 0 12px">Not currently assigned.</p>
    <?php endif; ?>

    <?php if (is_it_admin()): ?>
    <form method="POST" style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input type="hidden" name="action" value="assign">
      <select name="user_id" style="padding:7px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
        <option value="">— Select employee —</option>
        <?php foreach ($users_list as $ul): ?><option value="<?= $ul['id'] ?>"><?= htmlspecialchars($ul['name']) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="assign_notes" placeholder="Note (optional)" style="padding:7px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit;flex:1;min-width:160px">
      <button class="btn-sm btn-red" type="submit">Assign</button>
    </form>
    <?php endif; ?>

    <?php if ($history): ?>
    <table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:16px">
      <tr style="color:#aaa"><th style="text-align:left;padding:4px 0;font-weight:700;font-size:10px;text-transform:uppercase">Employee</th><th style="text-align:left;padding:4px 0;font-weight:700;font-size:10px;text-transform:uppercase">Assigned</th><th style="text-align:left;padding:4px 0;font-weight:700;font-size:10px;text-transform:uppercase">Returned</th></tr>
      <?php foreach ($history as $h): ?>
      <tr style="border-top:1px solid #f5f6f8">
        <td style="padding:6px 0;color:#444"><?= htmlspecialchars($h['emp_name']) ?></td>
        <td style="padding:6px 0;color:#888"><?= date('d M Y', strtotime($h['assigned_at'])) ?></td>
        <td style="padding:6px 0;color:#888"><?= $h['returned_at'] ? date('d M Y', strtotime($h['returned_at'])) : '<span style="color:#16a34a;font-weight:600">Active</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <!-- Maintenance -->
  <div class="panel">
    <h2>Maintenance History</h2>
    <?php foreach ($maint as $m): ?>
    <div class="maint-row">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-weight:700;color:#1a1a1a"><?= ucfirst($m['type']) ?></span>
        <span style="color:#aaa;font-size:11px"><?= date('d M Y', strtotime($m['performed_at'])) ?></span>
      </div>
      <div style="color:#555;margin-top:2px"><?= htmlspecialchars($m['description']) ?></div>
      <div style="display:flex;gap:12px;margin-top:4px;font-size:11px;color:#aaa">
        <?php if ($m['vendor']): ?><span>Vendor: <?= htmlspecialchars($m['vendor']) ?></span><?php endif; ?>
        <?php if ($m['cost']): ?><span>Cost: QAR <?= number_format($m['cost'],2) ?></span><?php endif; ?>
        <?php if ($m['next_due']): ?><span>Next: <?= date('d M Y', strtotime($m['next_due'])) ?></span><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$maint): ?><p style="color:#ccc;font-size:13px;margin:0">No maintenance records.</p><?php endif; ?>

    <?php if (is_it_admin()): ?>
    <form method="POST" style="margin-top:16px;padding-top:14px;border-top:1px solid #f0f1f3">
      <input type="hidden" name="action" value="maintenance">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
        <select name="maint_type" style="padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
          <?php foreach (['service','repair','inspection','upgrade','other'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?>
        </select>
        <input type="date" name="maint_date" value="<?= date('Y-m-d') ?>" style="padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
        <input type="text" name="maint_vendor" placeholder="Vendor (optional)" style="padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
        <input type="number" name="maint_cost" placeholder="Cost (optional)" step="0.01" style="padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
        <input type="date" name="maint_next" placeholder="Next due" style="padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
      </div>
      <textarea name="maint_desc" rows="2" required placeholder="Description of work done…" style="width:100%;padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit;resize:vertical;margin-bottom:8px"></textarea>
      <button class="btn-sm btn-red" type="submit">Add Record</button>
    </form>
    <?php endif; ?>
  </div>

  <!-- QR Code -->
  <div class="panel" style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">
    <div>
      <h2 style="margin-bottom:10px">QR Code</h2>
      <img src="<?= asset_qr_url($a['tag'], 160) ?>" alt="QR Code" style="width:120px;height:120px;border:1px solid #e8eaee;border-radius:8px;display:block">
    </div>
    <div style="flex:1;min-width:200px">
      <h2 style="margin-bottom:10px">Quick Links</h2>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn-sm btn-grey" href="qr-labels.php?id=<?= $id ?>">🔲 Print Label</a>
        <?php if (is_it_admin()): ?>
        <a class="btn-sm btn-grey" href="transfer.php">↔ Transfer</a>
        <a class="btn-sm btn-grey" href="disposal.php">🗑 Disposal</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Depreciation Schedule -->
  <?php if ($depr_schedule): ?>
  <div class="panel" id="depreciation">
    <h2>Depreciation Schedule (<?= ucfirst(str_replace('_',' ',$a['depreciation_method'])) ?>)</h2>
    <div style="display:flex;gap:20px;margin-bottom:14px;flex-wrap:wrap">
      <div><span style="font-size:11px;color:#aaa;text-transform:uppercase;letter-spacing:.4px;display:block">Cost</span><span style="font-size:16px;font-weight:800">QAR <?= number_format($a['purchase_value'],2) ?></span></div>
      <div><span style="font-size:11px;color:#aaa;text-transform:uppercase;letter-spacing:.4px;display:block">Book Value Today</span><span style="font-size:16px;font-weight:800;color:#16a34a">QAR <?= number_format($book_value,2) ?></span></div>
      <div><span style="font-size:11px;color:#aaa;text-transform:uppercase;letter-spacing:.4px;display:block">Accumulated Depr</span><span style="font-size:16px;font-weight:800;color:#FF3D33">QAR <?= number_format($a['purchase_value']-$book_value,2) ?></span></div>
      <div><span style="font-size:11px;color:#aaa;text-transform:uppercase;letter-spacing:.4px;display:block">Salvage Value</span><span style="font-size:16px;font-weight:800;color:#888">QAR <?= number_format($a['salvage_value']??0,2) ?></span></div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:12.5px">
      <thead><tr style="background:#fafbfc;border-bottom:1.5px solid #eef0f3">
        <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px">Year</th>
        <th style="padding:8px 12px;text-align:right;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px">Depreciation</th>
        <th style="padding:8px 12px;text-align:right;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px">Book Value</th>
      </tr></thead>
      <tbody>
      <?php foreach ($depr_schedule as $ds): ?>
      <tr style="border-bottom:1px solid #f5f6f8">
        <td style="padding:8px 12px;color:#555"><?= $ds['year'] ?></td>
        <td style="padding:8px 12px;text-align:right;color:#FF3D33">QAR <?= number_format($ds['depreciation'],2) ?></td>
        <td style="padding:8px 12px;text-align:right;font-weight:700;color:#16a34a">QAR <?= number_format($ds['book_value'],2) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Transfer History -->
  <?php if ($transfers): ?>
  <div class="panel">
    <h2>Transfer History</h2>
    <table style="width:100%;border-collapse:collapse;font-size:12.5px">
      <thead><tr style="background:#fafbfc;border-bottom:1.5px solid #eef0f3">
        <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px">Date</th>
        <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px">From</th>
        <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px">To</th>
        <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px">By</th>
        <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px">Reason</th>
      </tr></thead>
      <tbody>
      <?php foreach ($transfers as $t): ?>
      <tr style="border-bottom:1px solid #f5f6f8">
        <td style="padding:8px 12px;color:#888;white-space:nowrap"><?= date('d M Y',strtotime($t['transferred_at'])) ?></td>
        <td style="padding:8px 12px;color:#555"><?= htmlspecialchars(implode(' / ',array_filter([$t['from_loc'],$t['from_dept']])) ?: '—') ?></td>
        <td style="padding:8px 12px;color:#555"><?= htmlspecialchars(implode(' / ',array_filter([$t['to_loc'],$t['to_dept'],$t['to_user_name']])) ?: '—') ?></td>
        <td style="padding:8px 12px;color:#888"><?= htmlspecialchars($t['by_name']??'—') ?></td>
        <td style="padding:8px 12px;color:#555"><?= htmlspecialchars($t['reason']??'') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Disposal Info -->
  <?php if ($disposal): ?>
  <div class="panel" style="border-color:#fecaca">
    <h2 style="color:#dc2626">Disposal Record</h2>
    <dl class="dl">
      <dt>Reason</dt><dd><?= htmlspecialchars(ucfirst(str_replace('_',' ',$disposal['reason']))) ?></dd>
      <dt>Method</dt><dd><?= htmlspecialchars(ucfirst($disposal['method'])) ?></dd>
      <dt>Date</dt><dd><?= date('d M Y',strtotime($disposal['disposed_at'])) ?></dd>
      <dt>Disposed By</dt><dd><?= htmlspecialchars($disposal['by_name']??'—') ?></dd>
      <dt>Approved By</dt><dd><?= $disposal['approved_by'] ? htmlspecialchars($disposal['approved_by_name']) : '<span style="color:#d97706">Pending approval</span>' ?></dd>
      <?php if ($disposal['proceeds']): ?><dt>Proceeds</dt><dd>QAR <?= number_format($disposal['proceeds'],2) ?></dd><?php endif; ?>
      <?php if ($disposal['notes']): ?><dt>Notes</dt><dd><?= htmlspecialchars($disposal['notes']) ?></dd><?php endif; ?>
    </dl>
    <?php if ($disposal['approved_by'] && is_it_admin()): ?>
    <div style="margin-top:12px"><a class="btn-sm btn-grey" href="disposal.php?cert=<?= $disposal['id'] ?>">📄 Download Certificate</a></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Activity Log -->
  <div class="panel">
    <h2>Activity Log</h2>
    <?php foreach ($logs as $l): ?>
    <div class="log-row">
      <div style="flex:1">
        <span class="log-action"><?= ucfirst($l['action']) ?></span>
        <?php if ($l['detail']): ?><span style="color:#888"> — <?= htmlspecialchars($l['detail']) ?></span><?php endif; ?>
        <?php if ($l['uname']): ?><span style="color:#bbb;font-size:11px"> by <?= htmlspecialchars($l['uname']) ?></span><?php endif; ?>
      </div>
      <div class="log-ts"><?= date('d M Y H:i', strtotime($l['created_at'])) ?></div>
    </div>
    <?php endforeach; ?>
    <?php if (!$logs): ?><p style="color:#ccc;font-size:13px;margin:0">No activity yet.</p><?php endif; ?>
  </div>

</div>
</div>
</body>
</html>
