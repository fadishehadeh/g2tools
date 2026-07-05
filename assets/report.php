<?php
session_start();
require '../config.php';
require_login();
require_can('assets');

$cat    = (int)($_GET['cat'] ?? 0);
$status = $_GET['status'] ?? '';
$loc    = (int)($_GET['loc'] ?? 0);

$sql = "SELECT a.*, c.name cat_name, c.icon cat_icon, l.name loc_name, d.name dept_name,
               u.name assigned_to
        FROM assets a
        LEFT JOIN asset_categories c ON c.id=a.category_id
        LEFT JOIN asset_locations l ON l.id=a.location_id
        LEFT JOIN asset_departments d ON d.id=a.department_id
        LEFT JOIN asset_assignments aa ON aa.asset_id=a.id AND aa.returned_at IS NULL
        LEFT JOIN users u ON u.id=aa.user_id
        WHERE 1=1";
$params=[];
if ($cat)    { $sql.=" AND a.category_id=?"; $params[]=$cat; }
if ($status) { $sql.=" AND a.status=?"; $params[]=$status; }
if ($loc)    { $sql.=" AND a.location_id=?"; $params[]=$loc; }
$sql .= " ORDER BY a.name";
$stmt=db()->prepare($sql); $stmt->execute($params);
$assets=$stmt->fetchAll();

$categories = db()->query("SELECT * FROM asset_categories ORDER BY name")->fetchAll();
$locations  = db()->query("SELECT * FROM asset_locations ORDER BY name")->fetchAll();

if (($_GET['export']??'')==='xlsx') {
    require '../office/petty-cash/xlsx.php';
    $xls = new XlsxWriter();

    // Sheet 1: All assets
    $rows=[['Tag','Name','Category','Brand','Model','Serial','Location','Department','Assigned To','Status','Purchase Date','Value (QAR)','Warranty Expiry']];
    foreach ($assets as $a) $rows[]=[$a['tag'],$a['name'],$a['cat_name']??'',$a['brand']??'',$a['model']??'',$a['serial_number']??'',$a['loc_name']??'',$a['dept_name']??'',$a['assigned_to']??'',ucfirst(str_replace('_',' ',$a['status'])),$a['purchase_date']??'',(float)($a['purchase_value']??0),$a['warranty_expiry']??''];
    $types=['text','text','text','text','text','text','text','text','text','text','text','number','text'];
    $xls->addSheet('Assets',$rows,$types);

    // Sheet 2: By category summary
    $by_cat=[]; foreach($assets as $a){ $k=$a['cat_name']??'Uncategorized'; $by_cat[$k]=['count'=>($by_cat[$k]['count']??0)+1,'value'=>($by_cat[$k]['value']??0)+$a['purchase_value']]; }
    $cat_rows=[['Category','Count','Total Value (QAR)']];
    foreach($by_cat as $k=>$v) $cat_rows[]=[$k,$v['count'],(float)$v['value']];
    $xls->addSheet('By Category',$cat_rows,['text','number','number']);

    // Sheet 3: By status
    $by_st=[]; foreach($assets as $a){ $k=ucfirst(str_replace('_',' ',$a['status'])); $by_st[$k]=($by_st[$k]??0)+1; }
    $st_rows=[['Status','Count']];
    foreach($by_st as $k=>$v) $st_rows[]=[$k,$v];
    $xls->addSheet('By Status',$st_rows,['text','number']);

    $xls->output('assets_report_'.date('Y-m-d').'.xlsx');
}

$qs=http_build_query(array_filter(['cat'=>$cat,'status'=>$status,'loc'=>$loc]));
$total_val=array_sum(array_column($assets,'purchase_value'));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Asset Report — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
*,*::before,*::after{box-sizing:border-box}
.pw{padding:30px 36px 60px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.btn-xlsx{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#1d6f42;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;align-items:center}
.filters select,.filters input{padding:8px 12px;border:1.5px solid #e0e2e8;border-radius:7px;font-size:13px;font-family:inherit;outline:none}
.filters button{padding:8px 16px;background:#1a1a1a;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:20px}
.card{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:16px 18px}
.card .val{font-size:22px;font-weight:800;color:#1a1a1a;margin-bottom:2px}
.card .lbl{font-size:11px;color:#aaa}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;overflow:hidden}
table{width:100%;border-collapse:collapse;font-size:13px}
th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#aaa;padding:10px 14px;border-bottom:1.5px solid #eef0f3;text-align:left;background:#fafbfc;white-space:nowrap}
td{padding:10px 14px;border-bottom:1px solid #f5f6f8;vertical-align:middle}
tr:last-child td{border-bottom:none}tr:hover td{background:#fafbfc}
.status-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">Asset Report</span>
</div>
<div class="pw">
  <div class="ph">
    <h1>Asset Report</h1>
    <a class="btn-xlsx" href="?<?= $qs ?>&export=xlsx">⬇ Export Excel</a>
  </div>

  <form class="filters" method="GET">
    <select name="cat"><option value="">All categories</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $cat==$c['id']?'selected':'' ?>><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
    <select name="loc"><option value="">All locations</option><?php foreach($locations as $l): ?><option value="<?= $l['id'] ?>" <?= $loc==$l['id']?'selected':'' ?>><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?></select>
    <select name="status"><option value="">All statuses</option><?php foreach(['active','in_repair','retired','disposed','lost'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?></select>
    <button type="submit">Filter</button>
    <?php if($cat||$status||$loc): ?><a href="report.php" style="font-size:13px;color:#aaa;text-decoration:none">Clear</a><?php endif; ?>
  </form>

  <div class="cards">
    <div class="card"><div class="val"><?= count($assets) ?></div><div class="lbl">Assets</div></div>
    <div class="card"><div class="val">QAR <?= number_format($total_val,0) ?></div><div class="lbl">Total Value</div></div>
    <div class="card"><div class="val"><?= count(array_filter($assets,fn($a)=>$a['status']==='active')) ?></div><div class="lbl">Active</div></div>
    <div class="card"><div class="val"><?= count(array_filter($assets,fn($a)=>!empty($a['assigned_to']))) ?></div><div class="lbl">Assigned</div></div>
  </div>

  <div class="panel">
    <table>
      <thead><tr><th>Tag</th><th>Name</th><th>Category</th><th>Location</th><th>Assigned To</th><th style="text-align:right">Value (QAR)</th><th>Status</th></tr></thead>
      <tbody>
      <?php
      $sc=['active'=>['#f0fdf4','#16a34a'],'in_repair'=>['#fffbeb','#d97706'],'retired'=>['#f5f6f8','#888'],'disposed'=>['#fef2f2','#dc2626'],'lost'=>['#fdf4ff','#9333ea']];
      foreach($assets as $a): [$sbg,$sfg]=$sc[$a['status']]??['#f5f6f8','#888']; ?>
      <tr>
        <td style="font-family:monospace;color:#aaa;font-size:11px"><a href="view.php?id=<?= $a['id'] ?>" style="color:#FF3D33;text-decoration:none"><?= htmlspecialchars($a['tag']) ?></a></td>
        <td style="font-weight:600"><?= htmlspecialchars($a['name']) ?></td>
        <td><?= $a['cat_icon']??'📦' ?> <?= htmlspecialchars($a['cat_name']??'—') ?></td>
        <td style="color:#555"><?= htmlspecialchars($a['loc_name']??'—') ?></td>
        <td style="color:#555"><?= htmlspecialchars($a['assigned_to']??'—') ?></td>
        <td style="text-align:right;font-weight:600"><?= $a['purchase_value']?number_format($a['purchase_value'],2):'—' ?></td>
        <td><span class="status-badge" style="background:<?= $sbg ?>;color:<?= $sfg ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$assets): ?><tr><td colspan="7" style="text-align:center;padding:40px;color:#ccc">No assets.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
</body>
</html>
