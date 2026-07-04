<?php
session_start();
require '../config.php';
require_login();
require_can('assets');
require_it_admin();
require '_lib.php';

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

// ── CSV Template download ─────────────────────────────────────────────────────
if (($_GET['template'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="asset_import_template.csv"');
    $f = fopen('php://output','w');
    fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($f,['tag','name','brand','model','serial_number','category','location','department',
        'purchase_date','purchase_value','warranty_expiry','status','depreciation_method',
        'useful_life_years','salvage_value','notes']);
    fputcsv($f,['IT-001','MacBook Pro 14"','Apple','MacBook Pro','SN123456','Laptops','Head Office','IT',
        '2024-01-15','7500.00','2027-01-15','active','straight_line','5','500','For CEO']);
    fclose($f); exit;
}

// ── POST: process upload ──────────────────────────────────────────────────────
$errors = []; $imported = 0; $skipped = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK || !$file['size']) {
        $errors[] = 'Upload failed or file is empty.';
    } else {
        $handle = fopen($file['tmp_name'],'r');
        // Read header
        $header = fgetcsv($handle);
        if (!$header) { $errors[] = 'Could not read CSV header.'; }
        else {
            $header = array_map('strtolower', array_map('trim', $header));
            $col = array_flip($header);
            $need = ['tag','name'];
            foreach ($need as $n) {
                if (!isset($col[$n])) $errors[] = "Missing required column: $n";
            }
        }
        if (!$errors) {
            // Preload lookup maps
            $cats  = db()->query("SELECT id,name FROM asset_categories")->fetchAll(PDO::FETCH_KEY_PAIR);
            $cats  = array_change_key_case(array_flip($cats), CASE_LOWER);
            $locs  = db()->query("SELECT id,name FROM asset_locations")->fetchAll(PDO::FETCH_KEY_PAIR);
            $locs  = array_change_key_case(array_flip($locs), CASE_LOWER);
            $depts = db()->query("SELECT id,name FROM asset_departments")->fetchAll(PDO::FETCH_KEY_PAIR);
            $depts = array_change_key_case(array_flip($depts), CASE_LOWER);

            $valid_statuses = ['active','in_repair','retired','disposed','lost'];
            $valid_methods  = ['none','straight_line','double_declining'];
            $rownum = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rownum++;
                if (count($row) < 2) { $skipped++; continue; }
                $r = [];
                foreach ($header as $i => $h) $r[$h] = trim($row[$i] ?? '');

                $tag  = $r['tag']  ?? '';
                $name = $r['name'] ?? '';
                if (!$tag || !$name) { $errors[] = "Row $rownum: missing tag or name."; $skipped++; continue; }

                // Check duplicate tag
                $dup = db()->prepare("SELECT id FROM assets WHERE tag=?"); $dup->execute([$tag]);
                if ($dup->fetchColumn()) { $errors[] = "Row $rownum: tag '$tag' already exists — skipped."; $skipped++; continue; }

                $status = in_array($r['status']??'',$valid_statuses) ? $r['status'] : 'active';
                $method = in_array($r['depreciation_method']??'',$valid_methods) ? $r['depreciation_method'] : 'none';

                $cat_id  = isset($r['category'])  && isset($cats[strtolower($r['category'])])  ? $cats[strtolower($r['category'])]  : null;
                $loc_id  = isset($r['location'])   && isset($locs[strtolower($r['location'])])  ? $locs[strtolower($r['location'])]  : null;
                $dept_id = isset($r['department']) && isset($depts[strtolower($r['department'])]) ? $depts[strtolower($r['department'])] : null;

                $pv = $r['purchase_value'] !== '' ? (float)$r['purchase_value'] : null;
                $sv = $r['salvage_value']  !== '' ? (float)$r['salvage_value']  : null;
                $ul = $r['useful_life_years'] !== '' ? (int)$r['useful_life_years'] : null;
                $pd = $r['purchase_date']    ?: null;
                $we = $r['warranty_expiry']  ?: null;

                db()->prepare("INSERT INTO assets
                    (tag,name,brand,model,serial_number,category_id,location_id,department_id,
                     purchase_date,purchase_value,warranty_expiry,status,depreciation_method,
                     useful_life_years,salvage_value,notes,created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                  ->execute([$tag,$name,$r['brand']??'',$r['model']??'',$r['serial_number']??'',
                    $cat_id,$loc_id,$dept_id,$pd,$pv,$we,$status,$method,$ul,$sv,$r['notes']??'']);

                $newId = (int)db()->lastInsertId();
                asset_log($newId,'created','Imported via bulk upload');
                $imported++;
            }
            fclose($handle);

            // Log import
            db()->prepare("INSERT INTO asset_import_log (imported_by,row_count,error_count,created_at) VALUES (?,?,?,NOW())")
              ->execute([$_SESSION['g2_user']['id'],$imported,count($errors)]);
        }
    }
    if ($imported) {
        $_SESSION['flash'] = ['type'=>'ok','msg'=>"Imported $imported asset(s).".($errors?' Some rows had errors — see below.':'')];
        if (!$errors) { header('Location: list.php'); exit; }
    }
}

$recent_logs = db()->query("SELECT il.*, u.name uname FROM asset_import_log il LEFT JOIN users u ON u.id=il.imported_by ORDER BY il.created_at DESC LIMIT 10")->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bulk Import — G2 Tools</title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<link rel="stylesheet" href="/g2forms/form.css">
<style>
.pw{padding:28px 36px 60px;max-width:800px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.flash{padding:11px 16px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:16px}
.flash-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:22px 24px;margin-bottom:16px}
.panel h2{font-size:13px;font-weight:800;color:#1a1a1a;margin:0 0 14px}
.dl-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:#f1f5f9;color:#1a1a1a;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;border:none;cursor:pointer}
.dl-btn:hover{background:#e2e8f0}
.btn-red{padding:9px 18px;background:#FF3D33;color:#fff;border-radius:8px;font-size:13px;font-weight:700;border:none;cursor:pointer}
.drop-area{border:2px dashed #d1d5db;border-radius:10px;padding:40px;text-align:center;cursor:pointer;transition:border-color .2s;background:#fafbfc}
.drop-area:hover,.drop-area.over{border-color:#FF3D33;background:#fff8f8}
.drop-area input{display:none}
.err-list{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:14px 16px;margin-top:14px}
.err-list li{font-size:13px;color:#dc2626;padding:2px 0}
table{width:100%;border-collapse:collapse;font-size:13px}
th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#aaa;padding:9px 12px;border-bottom:1.5px solid #eef0f3;text-align:left;background:#fafbfc}
td{padding:9px 12px;border-bottom:1px solid #f5f6f8}
tr:last-child td{border-bottom:none}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">Bulk Import</span>
</div>
<div class="pw">

  <?php if ($flash): ?>
  <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="panel">
    <h2>Step 1 — Download Template</h2>
    <p style="font-size:13px;color:#555;margin:0 0 14px">Download the CSV template, fill it in, then upload. Do not change the column headers.</p>
    <a class="dl-btn" href="?template=1">⬇ Download CSV Template</a>
    <div style="margin-top:14px;font-size:12px;color:#aaa;line-height:1.6">
      <strong style="color:#555">Required:</strong> tag, name<br>
      <strong style="color:#555">Optional:</strong> brand, model, serial_number, category, location, department, purchase_date (YYYY-MM-DD), purchase_value, warranty_expiry (YYYY-MM-DD), status (active/in_repair/retired/disposed/lost), depreciation_method (none/straight_line/double_declining), useful_life_years, salvage_value, notes
    </div>
  </div>

  <div class="panel">
    <h2>Step 2 — Upload CSV</h2>
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
      <div class="drop-area" id="dropArea" onclick="document.getElementById('csv_file').click()">
        <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" onchange="showName(this)">
        <div id="dropText">
          <div style="font-size:32px;margin-bottom:8px">📂</div>
          <div style="font-size:14px;font-weight:700;color:#1a1a1a">Click or drag CSV file here</div>
          <div id="fileName" style="font-size:12px;color:#aaa;margin-top:6px">No file chosen</div>
        </div>
      </div>
      <div style="margin-top:14px">
        <button type="submit" class="btn-red">⬆ Import Assets</button>
      </div>
    </form>
    <?php if ($errors): ?>
    <ul class="err-list">
      <?php foreach ($errors as $e): ?>
      <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php if ($imported > 0): ?>
    <div style="margin-top:10px;font-size:13px;color:#16a34a;font-weight:700">✓ <?= $imported ?> asset<?= $imported!==1?'s':'' ?> imported. <?= $skipped ? "$skipped skipped." : '' ?></div>
    <?php endif; ?>
  </div>

  <?php if ($recent_logs): ?>
  <div class="panel">
    <h2>Import History</h2>
    <table>
      <thead><tr><th>Date</th><th>Imported By</th><th>Rows Imported</th><th>Errors</th></tr></thead>
      <tbody>
      <?php foreach ($recent_logs as $l): ?>
      <tr>
        <td style="color:#888"><?= date('d M Y H:i',strtotime($l['created_at'])) ?></td>
        <td><?= htmlspecialchars($l['uname']??'—') ?></td>
        <td style="font-weight:700;color:#16a34a"><?= $l['row_count'] ?></td>
        <td><?= $l['error_count'] ? '<span style="color:#dc2626;font-weight:700">'.$l['error_count'].'</span>' : '—' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>
</div>
<script>
function showName(input) {
  document.getElementById('fileName').textContent = input.files[0]?.name ?? 'No file chosen';
}
const da = document.getElementById('dropArea');
da.addEventListener('dragover', e => { e.preventDefault(); da.classList.add('over'); });
da.addEventListener('dragleave', () => da.classList.remove('over'));
da.addEventListener('drop', e => {
  e.preventDefault(); da.classList.remove('over');
  const f = e.dataTransfer.files[0];
  if (f) {
    const dt = new DataTransfer(); dt.items.add(f);
    const inp = document.getElementById('csv_file');
    inp.files = dt.files;
    showName(inp);
  }
});
</script>
</body>
</html>
