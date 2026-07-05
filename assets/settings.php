<?php
session_start();
require '../config.php';
require_login();
require_it_admin();

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

// ── Settings store: simple key-value table ────────────────────────────────────
// CREATE TABLE IF NOT EXISTS asset_settings (k VARCHAR(100) PRIMARY KEY, v TEXT);

function as_get(string $k, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try { $r = db()->query("SELECT k,v FROM asset_settings"); $cache = $r->fetchAll(PDO::FETCH_KEY_PAIR); }
        catch (\Exception $e) { $cache = []; }
    }
    return $cache[$k] ?? $default;
}

function as_set(string $k, string $v): void {
    db()->prepare("INSERT INTO asset_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=?")->execute([$k,$v,$v]);
}

// ── Ensure table exists ───────────────────────────────────────────────────────
try {
    db()->exec("CREATE TABLE IF NOT EXISTS asset_settings (k VARCHAR(100) PRIMARY KEY, v TEXT NOT NULL DEFAULT '')");
} catch (\Exception $e) {}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['company_name','company_currency','default_depreciation_method','default_useful_life',
             'default_salvage_pct','asset_tag_prefix','asset_tag_next','low_value_threshold',
             'email_maintenance_due','email_warranty_expiry'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) as_set($k, trim($_POST[$k]));
    }
    $_SESSION['flash'] = ['type'=>'ok','msg'=>'Settings saved.'];
    header('Location: settings.php'); exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Asset Settings — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
.pw{padding:28px 36px 60px;max-width:700px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.flash{padding:11px 16px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:16px}
.flash-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.section{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:22px 24px;margin-bottom:16px}
.section h2{font-size:13px;font-weight:800;color:#1a1a1a;margin:0 0 16px;padding-bottom:10px;border-bottom:1px solid #f0f0f0}
.fg{display:grid;gap:14px}
.fg-2{grid-template-columns:1fr 1fr}
.frow{display:flex;flex-direction:column;gap:5px}
label.fl{font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px}
input[type=text],input[type=number],input[type=email],select{width:100%;padding:9px 11px;border:1.5px solid #e0e2e8;border-radius:7px;font-size:13px;font-family:inherit;outline:none}
input:focus,select:focus{border-color:#FF3D33}
.hint{font-size:11px;color:#bbb;margin-top:2px}
.btn-save{padding:10px 22px;background:#FF3D33;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer}
.btn-save:hover{opacity:.88}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f5f6f8}
.toggle-row:last-child{border-bottom:none}
.tl{font-size:13px;font-weight:600;color:#1a1a1a}
.tl-sub{font-size:11px;color:#aaa}
.toggle-wrap{position:relative;display:inline-block;width:38px;height:22px}
.toggle-wrap input{opacity:0;width:0;height:0}
.toggle-wrap .slider{position:absolute;inset:0;background:#e0e2e8;border-radius:11px;cursor:pointer;transition:.2s}
.toggle-wrap input:checked + .slider{background:#FF3D33}
.toggle-wrap .slider:before{content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s}
.toggle-wrap input:checked + .slider:before{transform:translateX(16px)}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">Settings</span>
</div>
<div class="pw">

  <?php if ($flash): ?>
  <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="ph"><h1>Asset Settings</h1></div>

  <form method="POST">

    <div class="section">
      <h2>General</h2>
      <div class="fg fg-2">
        <div class="frow">
          <label class="fl">Company Name</label>
          <input type="text" name="company_name" value="<?= htmlspecialchars(as_get('company_name','G2 Group')) ?>">
        </div>
        <div class="frow">
          <label class="fl">Currency</label>
          <select name="company_currency">
            <?php foreach(['QAR','USD','EUR','GBP','LBP','AED'] as $c): ?>
            <option value="<?= $c ?>" <?= as_get('company_currency','QAR')===$c?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="frow">
          <label class="fl">Asset Tag Prefix</label>
          <input type="text" name="asset_tag_prefix" value="<?= htmlspecialchars(as_get('asset_tag_prefix','IT')) ?>" placeholder="e.g. IT, ASSET">
          <span class="hint">Used when auto-generating asset tags</span>
        </div>
        <div class="frow">
          <label class="fl">Next Tag Number</label>
          <input type="number" name="asset_tag_next" value="<?= htmlspecialchars(as_get('asset_tag_next','1001')) ?>" min="1">
        </div>
        <div class="frow">
          <label class="fl">Low-Value Asset Threshold (<?= as_get('company_currency','QAR') ?>)</label>
          <input type="number" name="low_value_threshold" value="<?= htmlspecialchars(as_get('low_value_threshold','500')) ?>" min="0" step="50">
          <span class="hint">Assets below this value are highlighted as low-value</span>
        </div>
      </div>
    </div>

    <div class="section">
      <h2>Depreciation Defaults</h2>
      <div class="fg fg-2">
        <div class="frow">
          <label class="fl">Default Method</label>
          <select name="default_depreciation_method">
            <option value="none" <?= as_get('default_depreciation_method')==='none'?'selected':'' ?>>None</option>
            <option value="straight_line" <?= as_get('default_depreciation_method')==='straight_line'?'selected':'' ?>>Straight Line</option>
            <option value="double_declining" <?= as_get('default_depreciation_method')==='double_declining'?'selected':'' ?>>Double Declining Balance</option>
          </select>
        </div>
        <div class="frow">
          <label class="fl">Default Useful Life (years)</label>
          <input type="number" name="default_useful_life" value="<?= htmlspecialchars(as_get('default_useful_life','5')) ?>" min="1" max="50">
        </div>
        <div class="frow">
          <label class="fl">Default Salvage Value %</label>
          <input type="number" name="default_salvage_pct" value="<?= htmlspecialchars(as_get('default_salvage_pct','10')) ?>" min="0" max="100">
          <span class="hint">% of purchase value as salvage (used when no salvage value is entered)</span>
        </div>
      </div>
    </div>

    <div class="section">
      <h2>Notifications</h2>
      <div class="toggle-row">
        <div>
          <div class="tl">Maintenance Due Alerts</div>
          <div class="tl-sub">Show dashboard alert when maintenance is overdue</div>
        </div>
        <label class="toggle-wrap">
          <input type="hidden" name="email_maintenance_due" value="0">
          <input type="checkbox" name="email_maintenance_due" value="1" <?= as_get('email_maintenance_due','1')==='1'?'checked':'' ?>>
          <span class="slider"></span>
        </label>
      </div>
      <div class="toggle-row">
        <div>
          <div class="tl">Warranty Expiry Alerts</div>
          <div class="tl-sub">Show dashboard alert when warranty expiring within 30 days</div>
        </div>
        <label class="toggle-wrap">
          <input type="hidden" name="email_warranty_expiry" value="0">
          <input type="checkbox" name="email_warranty_expiry" value="1" <?= as_get('email_warranty_expiry','1')==='1'?'checked':'' ?>>
          <span class="slider"></span>
        </label>
      </div>
    </div>

    <button type="submit" class="btn-save">Save Settings</button>

  </form>

</div>
</div>
</body>
</html>
