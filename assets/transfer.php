<?php
session_start();
require '../config.php';
require_login();
require_can('assets');
require '_lib.php';

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_it_admin()) {
    $aid      = (int)$_POST['asset_id'];
    $to_loc   = $_POST['to_location_id'] !== '' ? (int)$_POST['to_location_id'] : null;
    $to_dept  = $_POST['to_department_id'] !== '' ? (int)$_POST['to_department_id'] : null;
    $to_user  = $_POST['to_user_id'] !== '' ? (int)$_POST['to_user_id'] : null;
    $reason   = trim($_POST['reason'] ?? '');
    $date     = $_POST['transfer_date'] ?: date('Y-m-d');

    // Fetch current asset state for from_* values
    $asset = db()->prepare("SELECT * FROM assets WHERE id=?");
    $asset->execute([$aid]); $asset = $asset->fetch();

    if ($asset) {
        db()->prepare("INSERT INTO asset_transfers
            (asset_id, from_location_id, from_department_id, to_location_id, to_department_id, to_user_id, transferred_by, transferred_at, reason)
            VALUES (?,?,?,?,?,?,?,?,?)")
          ->execute([
            $aid,
            $asset['location_id'], $asset['department_id'],
            $to_loc, $to_dept, $to_user,
            $_SESSION['g2_user']['id'], $date, $reason
          ]);

        // Update asset
        $updates = []; $params = [];
        if ($to_loc  !== null) { $updates[] = "location_id=?";   $params[] = $to_loc; }
        if ($to_dept !== null) { $updates[] = "department_id=?"; $params[] = $to_dept; }
        if ($updates) {
            $params[] = $aid;
            db()->prepare("UPDATE assets SET ".implode(',',$updates)." WHERE id=?")->execute($params);
        }

        // Update assignment if reassigning to user
        if ($to_user) {
            // Return any current assignment
            db()->prepare("UPDATE asset_assignments SET returned_at=NOW() WHERE asset_id=? AND returned_at IS NULL")->execute([$aid]);
            db()->prepare("INSERT INTO asset_assignments (asset_id,user_id,assigned_by,assigned_at) VALUES (?,?,?,?)")
              ->execute([$aid,$to_user,$_SESSION['g2_user']['id'],$date]);
        }

        asset_log($aid,'transferred','Transfer: '.$reason);
        $_SESSION['flash'] = ['type'=>'ok','msg'=>'Transfer recorded.'];
    }
    header('Location: transfer.php'); exit;
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$transfers = db()->query("SELECT at2.*, a.tag, a.name asset_name,
    fl.name from_loc_name, fd.name from_dept_name,
    tl.name to_loc_name, td.name to_dept_name,
    tu.name to_user_name, tb.name transferred_by_name
    FROM asset_transfers at2
    JOIN assets a ON a.id=at2.asset_id
    LEFT JOIN asset_locations fl ON fl.id=at2.from_location_id
    LEFT JOIN asset_departments fd ON fd.id=at2.from_department_id
    LEFT JOIN asset_locations tl ON tl.id=at2.to_location_id
    LEFT JOIN asset_departments td ON td.id=at2.to_department_id
    LEFT JOIN users tu ON tu.id=at2.to_user_id
    LEFT JOIN users tb ON tb.id=at2.transferred_by
    ORDER BY at2.transferred_at DESC LIMIT 100")->fetchAll();

$assets      = db()->query("SELECT id,tag,name,location_id,department_id FROM assets WHERE status='active' ORDER BY name")->fetchAll();
$locations   = db()->query("SELECT * FROM asset_locations ORDER BY name")->fetchAll();
$departments = db()->query("SELECT * FROM asset_departments ORDER BY name")->fetchAll();
$users_list  = db()->query("SELECT id,name FROM users WHERE is_active=1 ORDER BY name")->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Asset Transfer — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<script src="/form-validate.js" defer></script>
<style>
.pw{padding:28px 36px 60px;max-width:1040px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.flash{padding:11px 16px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:16px}
.flash-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:20px 22px;margin-bottom:16px}
.panel h2{font-size:13px;font-weight:800;color:#1a1a1a;margin:0 0 14px}
.fg{display:grid;gap:12px}
.fg-3{grid-template-columns:1fr 1fr 1fr}
.fg-2{grid-template-columns:1fr 1fr}
label.fl{font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px}
select,input[type=text],input[type=date],textarea{width:100%;padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit;outline:none}
select:focus,input:focus,textarea:focus{border-color:#FF3D33}
.btn{padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn-red{background:#FF3D33;color:#fff}.btn-red:hover{opacity:.88}
table{width:100%;border-collapse:collapse;font-size:13px}
th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#aaa;padding:10px 14px;border-bottom:1.5px solid #eef0f3;text-align:left;background:#fafbfc;white-space:nowrap}
td{padding:10px 14px;border-bottom:1px solid #f5f6f8;vertical-align:middle}
tr:last-child td{border-bottom:none}tr:hover td{background:#fafbfc}
.arr{color:#aaa;font-size:14px;vertical-align:middle}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">Asset Transfer</span>
</div>
<div class="pw">

  <?php if ($flash): ?>
  <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <?php if (is_it_admin()): ?>
  <div class="panel">
    <h2>New Transfer</h2>
    <form method="POST" data-validate>
      <div class="fg fg-3" style="margin-bottom:12px">
        <div>
          <label class="fl">Asset</label>
          <select name="asset_id" required>
            <option value="">— Select asset —</option>
            <?php foreach ($assets as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['tag'].' — '.$a['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="fl">Transfer Date</label>
          <input type="date" name="transfer_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div>
          <label class="fl">Reason</label>
          <input type="text" name="reason" placeholder="e.g. Office relocation" required>
        </div>
      </div>
      <div class="fg fg-3">
        <div>
          <label class="fl">New Location</label>
          <select name="to_location_id">
            <option value="">— No change —</option>
            <?php foreach ($locations as $l): ?>
            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="fl">New Department</label>
          <select name="to_department_id">
            <option value="">— No change —</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="fl">Reassign To User (optional)</label>
          <select name="to_user_id">
            <option value="">— No reassignment —</option>
            <?php foreach ($users_list as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="margin-top:14px">
        <button type="submit" class="btn btn-red">↔ Record Transfer</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h2>Transfer History <span style="font-weight:400;color:#aaa">(<?= count($transfers) ?>)</span></h2>
    <?php if (!$transfers): ?>
    <p style="color:#ccc;text-align:center;padding:32px 0">No transfers yet.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>Asset</th><th>From</th><th></th><th>To</th><th>User</th><th>Date</th><th>By</th><th>Reason</th></tr></thead>
      <tbody>
      <?php foreach ($transfers as $t): ?>
      <tr>
        <td>
          <div style="font-weight:600"><a href="view.php?id=<?= $t['asset_id'] ?>" style="color:inherit;text-decoration:none"><?= htmlspecialchars($t['asset_name']) ?></a></div>
          <div style="font-size:11px;color:#aaa;font-family:monospace"><?= htmlspecialchars($t['tag']) ?></div>
        </td>
        <td style="color:#888;font-size:12px">
          <?php if ($t['from_loc_name']): ?><div><?= htmlspecialchars($t['from_loc_name']) ?></div><?php endif; ?>
          <?php if ($t['from_dept_name']): ?><div style="color:#bbb"><?= htmlspecialchars($t['from_dept_name']) ?></div><?php endif; ?>
          <?php if (!$t['from_loc_name'] && !$t['from_dept_name']): ?>—<?php endif; ?>
        </td>
        <td class="arr">→</td>
        <td style="color:#555;font-size:12px">
          <?php if ($t['to_loc_name']): ?><div><?= htmlspecialchars($t['to_loc_name']) ?></div><?php endif; ?>
          <?php if ($t['to_dept_name']): ?><div style="color:#888"><?= htmlspecialchars($t['to_dept_name']) ?></div><?php endif; ?>
          <?php if (!$t['to_loc_name'] && !$t['to_dept_name']): ?>—<?php endif; ?>
        </td>
        <td style="color:#555;font-size:12px"><?= $t['to_user_name'] ? htmlspecialchars($t['to_user_name']) : '—' ?></td>
        <td style="color:#888;white-space:nowrap;font-size:12px"><?= date('d M Y',strtotime($t['transferred_at'])) ?></td>
        <td style="color:#888;font-size:12px"><?= htmlspecialchars($t['transferred_by_name']??'—') ?></td>
        <td style="color:#555;font-size:12px;max-width:180px"><?= htmlspecialchars($t['reason']??'') ?></td>
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

