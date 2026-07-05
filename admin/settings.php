<?php
session_start();
require '../config.php';
require_login();
if (!is_superadmin()) { header('Location: /'); exit; }

$success = $error = '';

// ── Data actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data_action'])) {
    $action = $_POST['data_action'];

    if ($action === 'seed') {
        try {
            db()->beginTransaction();

            // Seed asset locations
            db()->exec("INSERT IGNORE INTO asset_locations (name, office) VALUES
                ('Head Office - Doha', 'doha'),
                ('IT Room - Doha', 'doha'),
                ('Meeting Room A', 'doha'),
                ('Beirut Office', 'beirut'),
                ('Storage', 'doha')");

            // Seed asset departments
            db()->exec("INSERT IGNORE INTO asset_departments (name) VALUES
                ('IT'),('Finance'),('HR'),('Operations'),('Management')");

            // Get category/location/dept IDs
            $catLaptop = db()->query("SELECT id FROM asset_categories WHERE name='Laptops' LIMIT 1")->fetchColumn();
            $catPhone  = db()->query("SELECT id FROM asset_categories WHERE name LIKE '%Phone%' OR name LIKE '%Mobile%' LIMIT 1")->fetchColumn();
            $catDesk   = db()->query("SELECT id FROM asset_categories WHERE name LIKE '%Desktop%' LIMIT 1")->fetchColumn();
            $catFurn   = db()->query("SELECT id FROM asset_categories WHERE name LIKE '%Furn%' LIMIT 1")->fetchColumn();
            $locHQ     = db()->query("SELECT id FROM asset_locations WHERE name LIKE '%Head Office%' LIMIT 1")->fetchColumn();
            $locIT     = db()->query("SELECT id FROM asset_locations WHERE name LIKE '%IT Room%' LIMIT 1")->fetchColumn();
            $deptIT    = db()->query("SELECT id FROM asset_departments WHERE name='IT' LIMIT 1")->fetchColumn();
            $deptFin   = db()->query("SELECT id FROM asset_departments WHERE name='Finance' LIMIT 1")->fetchColumn();
            $uid       = $_SESSION['g2_user']['id'];

            $mockAssets = [
                ['IT-1001','MacBook Pro 14"','Apple','MacBook Pro M3','C02XK1JXJG5H',$catLaptop,$locHQ,$deptIT,'2023-03-15',7200.00,'2026-03-15','active','straight_line',5,500],
                ['IT-1002','Dell XPS 15','Dell','XPS 15 9530','5CG1234ABC',$catLaptop,$locHQ,$deptFin,'2022-11-01',5800.00,'2025-11-01','active','straight_line',5,400],
                ['IT-1003','HP EliteBook 840','HP','EliteBook 840 G10','HP88ABC12',$catLaptop,$locIT,$deptIT,'2023-07-20',4500.00,'2026-07-20','active','straight_line',5,300],
                ['IT-1004','iPhone 15 Pro','Apple','iPhone 15 Pro','DNPXM123456789',$catPhone,$locHQ,$deptIT,'2023-09-22',2100.00,'2025-09-22','active','double_declining',3,100],
                ['IT-1005','Samsung Galaxy S24','Samsung','Galaxy S24 Ultra','R58N123456789',$catPhone,$locHQ,$deptFin,'2024-01-10',1800.00,'2026-01-10','active','double_declining',3,100],
                ['IT-1006','iMac 27"','Apple','iMac 27 2022','C07Z12345678',$catDesk,$locHQ,$deptIT,'2022-05-01',3200.00,'2025-05-01','in_repair','straight_line',5,200],
                ['IT-1007','Standing Desk','Ergotron','WorkFit-D',null,$catFurn,$locHQ,$deptFin,'2021-01-15',850.00,null,'active','straight_line',10,50],
                ['IT-1008','Cisco IP Phone','Cisco','8861 IP Phone','FTX2201WXYZ',$catPhone,$locIT,$deptIT,'2020-06-01',320.00,null,'retired','none',null,null],
            ];

            $stmt = db()->prepare("INSERT IGNORE INTO assets (tag,name,brand,model,serial_number,category_id,location_id,department_id,purchase_date,purchase_value,warranty_expiry,status,depreciation_method,useful_life_years,salvage_value,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            foreach ($mockAssets as $a) {
                $stmt->execute(array_merge($a, [$uid]));
                $newId = (int)db()->lastInsertId();
                if ($newId) {
                    db()->prepare("INSERT INTO asset_activity_log (asset_id,user_id,action,detail) VALUES (?,?,?,?)")
                      ->execute([$newId,$uid,'created','Seeded via mockup data']);
                }
            }

            // Seed petty cash float if empty
            $hasFloat = db()->query("SELECT COUNT(*) FROM petty_cash_float")->fetchColumn();
            if (!$hasFloat) {
                db()->exec("INSERT INTO petty_cash_float (office, amount, updated_by, updated_at) VALUES ('doha', 5000.00, 1, NOW()), ('beirut', 3000.00, 1, NOW())");
            }

            db()->commit();
            $success = '✓ Mockup data seeded successfully — assets, locations, departments added.';
        } catch (Exception $e) { db()->rollBack(); $error = 'Seed failed: '.$e->getMessage(); }
    }

    if ($action === 'clear_assets') {
        if ($_POST['confirm_clear'] ?? '' === 'DELETE') {
            try {
                db()->exec("DELETE FROM asset_activity_log");
                db()->exec("DELETE FROM asset_assignments");
                db()->exec("DELETE FROM asset_maintenance");
                db()->exec("DELETE FROM asset_transfers");
                db()->exec("DELETE FROM asset_disposals");
                db()->exec("DELETE FROM asset_import_log");
                db()->exec("DELETE FROM assets");
                $success = '✓ All asset data cleared.';
            } catch (Exception $e) { $error = 'Clear failed: '.$e->getMessage(); }
        } else {
            $error = 'Type DELETE in the confirmation field to clear asset data.';
        }
    }

    if ($action === 'reset_settings') {
        try {
            db()->exec("DELETE FROM asset_settings");
            db()->exec("DELETE FROM settings WHERE `key` NOT IN ('finance_email_1','finance_email_2','finance_email_3')");
            $success = '✓ Settings reset to defaults.';
        } catch (Exception $e) { $error = 'Reset failed: '.$e->getMessage(); }
    }

    if ($action !== 'seed' && $action !== 'clear_assets' && $action !== 'reset_settings') {
        // fall through to email settings save below
    }
}

// ── Email settings save ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['data_action'])) {
    $fields = ['finance_email_1','finance_email_2','finance_email_3'];
    try {
        $stmt = db()->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            if ($val && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email: " . htmlspecialchars($val);
                break;
            }
            $stmt->execute([$f, $val]);
        }
        if (!$error) $success = 'Settings saved.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$e1 = get_setting('finance_email_1');
$e2 = get_setting('finance_email_2');
$e3 = get_setting('finance_email_3');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
  *, *::before, *::after { box-sizing: border-box; }
  .page-wrap { padding: 40px 48px 80px; max-width: 860px; }
  .page-header { margin-bottom: 28px; }
  .page-header h1 { font-size: 24px; font-weight: 800; color: #1a1a1a; }
  .page-header p  { font-size: 13px; color: #999; margin-top: 4px; }

  .panel { background: #fff; border-radius: 14px; border: 1px solid #e8eaee;
           box-shadow: 0 2px 10px rgba(0,0,0,.05); padding: 28px 28px; margin-bottom: 24px; }
  .panel h2 { font-size: 14px; font-weight: 700; color: #1a1a1a; margin-bottom: 6px; }
  .panel .hint { font-size: 12px; color: #aaa; margin-bottom: 20px; }

  .field { margin-bottom: 16px; }
  .field label { display: block; font-size: 12px; font-weight: 600; color: #666;
                 text-transform: uppercase; letter-spacing: .3px; margin-bottom: 5px; }
  .field input { width: 100%; padding: 10px 14px; border: 1.5px solid #e8eaee; border-radius: 8px;
                 font-size: 14px; font-family: inherit; color: #1a1a1a; transition: border-color .18s; }
  .field input:focus { outline: none; border-color: #FF3D33; box-shadow: 0 0 0 3px rgba(255,61,51,.1); }

  .save-btn { padding: 11px 28px; background: #FF3D33; color: #fff; border: none;
              border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: background .2s; }
  .save-btn:hover { background: #c0170e; }

  .msg { padding: 11px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; }
  .msg-ok  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
  .msg-err { background: #fff5f5; border: 1px solid #fca5a5; color: #dc2626; }

  .action-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 4px; }
  .action-card {
    border: 1.5px solid #e8eaee; border-radius: 12px; padding: 22px 20px 20px;
    display: flex; flex-direction: column; gap: 0; background: #fafbfc;
  }
  .ac-icon { font-size: 28px; margin-bottom: 12px; line-height: 1; }
  .action-card h3 { font-size: 13px; font-weight: 700; color: #1a1a1a; margin: 0 0 8px; }
  .action-card p  { font-size: 12px; color: #999; line-height: 1.55; flex: 1; margin: 0 0 16px; }
  .btn-action {
    width: 100%; padding: 9px 12px; border-radius: 7px;
    font-size: 12.5px; font-weight: 700; cursor: pointer;
    transition: opacity .15s, transform .1s; border: 1.5px solid transparent;
  }
  .btn-action:hover { opacity: .85; }
  .btn-action:active { transform: scale(.98); }
  .btn-green { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
  .btn-red   { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
  .btn-grey  { background: #f1f5f9; color: #555;    border-color: #e0e2e8; }
  .confirm-row { display: flex; gap: 6px; }
  .confirm-row input {
    flex: 1; min-width: 0; padding: 8px 10px;
    border: 1.5px solid #fecaca; border-radius: 6px;
    font-size: 12px; font-family: inherit; background: #fff;
  }
  .confirm-row input:focus { outline: none; border-color: #dc2626; }
  .confirm-row button {
    padding: 8px 13px; background: #dc2626; color: #fff;
    border: none; border-radius: 6px; font-size: 12px;
    font-weight: 700; cursor: pointer; white-space: nowrap;
  }
  .confirm-row button:hover { background: #b91c1c; }
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar"><span class="topbar-title">Settings</span></div>
<div class="page-wrap">
  <div class="page-header">
    <h1>Settings</h1>
    <p>System configuration for G2 Tools.</p>
  </div>

  <?php if ($success): ?><div class="msg msg-ok"><?= $success ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="msg msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- ── Data Tools ── -->
  <div class="panel">
    <h2>Data Tools</h2>
    <p class="hint">Manage system data — seed sample content for testing, clear records, or reset configuration.</p>
    <div class="action-grid">

      <!-- Seed mockup -->
      <div class="action-card">
        <div class="ac-icon">🌱</div>
        <h3>Seed Mockup Data</h3>
        <p>Populate the system with sample assets, locations, and departments for testing and demos.</p>
        <form method="POST">
          <input type="hidden" name="data_action" value="seed">
          <button type="submit" class="btn-action btn-green">Seed Mockup Data</button>
        </form>
      </div>

      <!-- Clear assets -->
      <div class="action-card">
        <div class="ac-icon">🗑️</div>
        <h3>Clear Asset Data</h3>
        <p>Permanently delete all assets, assignments, maintenance records, transfers, and disposals.</p>
        <form method="POST">
          <input type="hidden" name="data_action" value="clear_assets">
          <div class="confirm-row">
            <input type="text" name="confirm_clear" placeholder='Type DELETE'>
            <button type="submit">Clear</button>
          </div>
        </form>
      </div>

      <!-- Reset settings -->
      <div class="action-card">
        <div class="ac-icon">↺</div>
        <h3>Reset Settings</h3>
        <p>Clear all asset settings and custom configuration back to factory defaults.</p>
        <form method="POST" onsubmit="return confirm('Reset all settings to defaults?')">
          <input type="hidden" name="data_action" value="reset_settings">
          <button type="submit" class="btn-action btn-grey">Reset to Defaults</button>
        </form>
      </div>

    </div>
  </div>

  <form method="POST">
    <div class="panel">
      <h2>Finance Email Recipients</h2>
      <p class="hint">All generated forms (Debit Notes, Credit Notes, Vendor Recon, AMEX) will be sent to these addresses. Leave blank to disable.</p>
      <div class="field">
        <label>Finance Email 1</label>
        <input type="email" name="finance_email_1" value="<?= htmlspecialchars($e1) ?>" placeholder="finance@example.com">
      </div>
      <div class="field">
        <label>Finance Email 2</label>
        <input type="email" name="finance_email_2" value="<?= htmlspecialchars($e2) ?>" placeholder="finance2@example.com">
      </div>
      <div class="field">
        <label>Finance Email 3</label>
        <input type="email" name="finance_email_3" value="<?= htmlspecialchars($e3) ?>" placeholder="finance3@example.com">
      </div>
    </div>
    <button type="submit" class="save-btn">Save Settings</button>
  </form>
</div>
</div>
</body>
</html>
