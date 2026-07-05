<?php
session_start();
require '../config.php';
require_login();
require_can('assets');
require '_lib.php';

$method = $_GET['method'] ?? '';
$cat    = (int)($_GET['cat'] ?? 0);

$sql = "SELECT a.*, c.name cat_name FROM assets a
        LEFT JOIN asset_categories c ON c.id=a.category_id
        WHERE a.purchase_value IS NOT NULL AND a.purchase_date IS NOT NULL
          AND a.depreciation_method != 'none'";
$params = [];
if ($method) { $sql .= " AND a.depreciation_method=?"; $params[] = $method; }
if ($cat)    { $sql .= " AND a.category_id=?"; $params[] = $cat; }
$sql .= " ORDER BY a.name";
$stmt = db()->prepare($sql); $stmt->execute($params);
$assets = $stmt->fetchAll();

$categories = db()->query("SELECT * FROM asset_categories ORDER BY name")->fetchAll();

// totals
$total_cost = 0; $total_bv = 0;
foreach ($assets as $a) {
    $total_cost += $a['purchase_value'];
    $total_bv   += asset_book_value($a) ?? $a['purchase_value'];
}
$total_depr = $total_cost - $total_bv;

// Export CSV
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="depreciation_'.date('Y-m-d').'.csv"');
    $f = fopen('php://output','w');
    fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($f,['Tag','Name','Category','Purchase Date','Cost (QAR)','Method','Life (yrs)','Salvage','Book Value','Accumulated Depr','% Depreciated']);
    foreach ($assets as $a) {
        $bv   = asset_book_value($a) ?? $a['purchase_value'];
        $depr = $a['purchase_value'] - $bv;
        fputcsv($f,[$a['tag'],$a['name'],$a['cat_name']??'',$a['purchase_date'],
            number_format($a['purchase_value'],2), $a['depreciation_method'],
            $a['useful_life_years']??'', number_format($a['salvage_value']??0,2),
            number_format($bv,2), number_format($depr,2),
            $a['purchase_value']>0 ? round($depr/$a['purchase_value']*100,1).'%' : '0%']);
    }
    fclose($f); exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Depreciation — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
*,*::before,*::after{box-sizing:border-box}
.pw{padding:28px 36px 60px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.btn-csv{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#1d6f42;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;align-items:center}
.filters select{padding:8px 12px;border:1.5px solid #e0e2e8;border-radius:7px;font-size:13px;font-family:inherit;outline:none}
.filters button{padding:8px 16px;background:#1a1a1a;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;margin-bottom:20px}
.card{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:16px 18px}
.card .val{font-size:20px;font-weight:800;color:#1a1a1a;margin-bottom:2px}
.card .lbl{font-size:11px;color:#aaa}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;overflow:hidden;margin-bottom:16px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#aaa;padding:10px 14px;border-bottom:1.5px solid #eef0f3;text-align:left;background:#fafbfc;white-space:nowrap}
td{padding:10px 14px;border-bottom:1px solid #f5f6f8;vertical-align:middle}
tr:last-child td{border-bottom:none}tr:hover td{background:#fafbfc}
.depr-bar{height:6px;background:#f5f6f8;border-radius:3px;overflow:hidden;margin-top:4px;min-width:80px}
.depr-fill{height:100%;background:#FF3D33;border-radius:3px}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">Depreciation</span>
</div>
<div class="pw">
  <div class="ph">
    <h1>Depreciation Schedule</h1>
    <a class="btn-csv" href="?method=<?= $method ?>&cat=<?= $cat ?>&export=csv">⬇ Export CSV</a>
  </div>

  <form class="filters" method="GET">
    <select name="method">
      <option value="">All methods</option>
      <option value="straight_line" <?= $method==='straight_line'?'selected':'' ?>>Straight Line</option>
      <option value="double_declining" <?= $method==='double_declining'?'selected':'' ?>>Double Declining Balance</option>
    </select>
    <select name="cat">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $cat==$c['id']?'selected':'' ?>><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
    <?php if ($method||$cat): ?><a href="depreciation.php" style="font-size:13px;color:#aaa;text-decoration:none">Clear</a><?php endif; ?>
  </form>

  <div class="cards">
    <div class="card"><div class="val">QAR <?= number_format($total_cost,2) ?></div><div class="lbl">Total Original Cost</div></div>
    <div class="card"><div class="val" style="color:#16a34a">QAR <?= number_format($total_bv,2) ?></div><div class="lbl">Total Book Value</div></div>
    <div class="card"><div class="val" style="color:#FF3D33">QAR <?= number_format($total_depr,2) ?></div><div class="lbl">Total Accumulated Depr</div></div>
    <div class="card"><div class="val"><?= count($assets) ?></div><div class="lbl">Assets with Depreciation</div></div>
  </div>

  <?php if (empty($assets)): ?>
  <div class="panel" style="padding:48px;text-align:center;color:#ccc">
    No assets with depreciation set up. Edit an asset and set the depreciation method and useful life.
  </div>
  <?php else: ?>
  <div class="panel">
    <table>
      <thead><tr>
        <th>Asset</th><th>Category</th><th>Method</th><th>Life</th>
        <th style="text-align:right">Cost (QAR)</th>
        <th style="text-align:right">Book Value</th>
        <th style="text-align:right">Acc. Depr</th>
        <th>% Depreciated</th>
        <th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($assets as $a):
        $bv   = asset_book_value($a) ?? $a['purchase_value'];
        $depr = $a['purchase_value'] - $bv;
        $pct  = $a['purchase_value'] > 0 ? round($depr/$a['purchase_value']*100) : 0;
        $method_label = ['straight_line'=>'SL','double_declining'=>'DDB','none'=>'—'][$a['depreciation_method']] ?? '';
      ?>
      <tr>
        <td>
          <div style="font-weight:600"><a href="view.php?id=<?= $a['id'] ?>" style="color:inherit;text-decoration:none"><?= htmlspecialchars($a['name']) ?></a></div>
          <div style="font-size:11px;color:#aaa;font-family:monospace"><?= htmlspecialchars($a['tag']) ?></div>
        </td>
        <td style="color:#555"><?= htmlspecialchars($a['cat_name'] ?? '—') ?></td>
        <td><span class="badge" style="background:#f5f6f8;color:#555"><?= $method_label ?></span></td>
        <td style="color:#555"><?= $a['useful_life_years'] ?> yr</td>
        <td style="text-align:right"><?= number_format($a['purchase_value'],2) ?></td>
        <td style="text-align:right;font-weight:700;color:#16a34a"><?= number_format($bv,2) ?></td>
        <td style="text-align:right;color:#FF3D33"><?= number_format($depr,2) ?></td>
        <td>
          <div style="font-size:12px;color:#555"><?= $pct ?>%</div>
          <div class="depr-bar"><div class="depr-fill" style="width:<?= $pct ?>%"></div></div>
        </td>
        <td><a href="view.php?id=<?= $a['id'] ?>#depreciation" style="font-size:12px;color:#FF3D33;font-weight:700;text-decoration:none">Schedule →</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="font-weight:800;background:#fafbfc">
          <td colspan="4" style="padding:10px 14px">Total</td>
          <td style="text-align:right;padding:10px 14px"><?= number_format($total_cost,2) ?></td>
          <td style="text-align:right;padding:10px 14px;color:#16a34a"><?= number_format($total_bv,2) ?></td>
          <td style="text-align:right;padding:10px 14px;color:#FF3D33"><?= number_format($total_depr,2) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>
</div>
</body>
</html>
