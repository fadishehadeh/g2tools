<?php
session_start();
require '../config.php';
require_login();
require_can('assets');
require '_lib.php';

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

// ── PDF Certificate ───────────────────────────────────────────────────────────
if (($_GET['cert'] ?? '') && is_it_admin()) {
    $did = (int)$_GET['cert'];
    $d   = db()->prepare("SELECT ad.*, a.tag, a.name asset_name, a.brand, a.model, a.serial_number,
        a.purchase_date, a.purchase_value, c.name cat_name,
        u.name disposed_by_name, ab.name approved_by_name
        FROM asset_disposals ad
        JOIN assets a ON a.id=ad.asset_id
        LEFT JOIN asset_categories c ON c.id=a.category_id
        LEFT JOIN users u ON u.id=ad.disposed_by
        LEFT JOIN users ab ON ab.id=ad.approved_by
        WHERE ad.id=?");
    $d->execute([$did]); $d = $d->fetch();
    if ($d) {
        require_once '../amex/lib/fpdf.php';
        class PDF_Disposal extends FPDF {
            function Header(){}
            function Footer(){
                $this->SetY(-13); $this->SetFont('Arial','I',8);
                $this->SetTextColor(180,180,180);
                $this->Cell(0,6,'G2 Group — Asset Disposal Certificate — Confidential',0,0,'C');
            }
        }
        $pdf = new PDF_Disposal('P','mm','A4');
        $pdf->AddPage(); $pdf->SetMargins(22,22,22); $pdf->SetAutoPageBreak(true,20);
        $lm=22; $pw=166;
        $logo = __DIR__.'/../logo.png';
        if(file_exists($logo)) $pdf->Image($logo,$lm,14,30,15,'PNG');
        $pdf->SetDrawColor(255,61,51); $pdf->SetLineWidth(0.7);
        $pdf->Line($lm,34,$lm+$pw,34); $pdf->SetLineWidth(0.2); $pdf->SetDrawColor(180,180,180);
        $pdf->SetFont('Arial','B',15); $pdf->SetTextColor(30,30,30);
        $pdf->SetXY($lm,38); $pdf->Cell(0,9,'ASSET DISPOSAL CERTIFICATE',0,1,'C');
        $pdf->SetFont('Arial','I',9); $pdf->SetTextColor(150,150,150);
        $pdf->SetX($lm); $pdf->Cell(0,6,'Certificate No: DISP-'.str_pad($d['id'],5,'0',STR_PAD_LEFT).'   |   Date: '.date('d M Y'),0,1,'C');
        $pdf->Ln(4);
        $section = function(string $t) use ($pdf,$lm,$pw){ $pdf->SetFont('Arial','B',9); $pdf->SetTextColor(255,61,51); $pdf->SetFillColor(255,248,248); $pdf->SetX($lm); $pdf->Cell($pw,7,'  '.$t,0,1,'L',true); $pdf->Ln(3); };
        $row = function(string $l, string $v) use ($pdf,$lm,$pw){ if(!trim($v)) return; $pdf->SetFont('Arial','',9); $pdf->SetTextColor(100,100,100); $pdf->SetX($lm); $pdf->Cell(52,6,$l,0,0); $pdf->SetFont('Arial','',9); $pdf->SetTextColor(20,20,20); $pdf->MultiCell($pw-52,6,$v,0,'L'); $pdf->Ln(1); };
        $section('ASSET DETAILS');
        $row('Asset Tag',  $d['tag']);
        $row('Asset Name', $d['asset_name']);
        $row('Category',   $d['cat_name']??'');
        $row('Brand/Model',trim($d['brand'].' '.$d['model']));
        $row('Serial No',  $d['serial_number']??'');
        $row('Purchase Date', $d['purchase_date']?date('d M Y',strtotime($d['purchase_date'])):'');
        $row('Purchase Value','QAR '.number_format($d['purchase_value']??0,2));
        $pdf->Ln(3);
        $section('DISPOSAL DETAILS');
        $row('Disposal Date',   date('d M Y',strtotime($d['disposed_at'])));
        $row('Reason',          ucfirst(str_replace('_',' ',$d['reason'])));
        $row('Method',          ucfirst($d['method']));
        $row('Proceeds',        $d['proceeds'] ? 'QAR '.number_format($d['proceeds'],2) : 'None');
        $row('Disposed By',     $d['disposed_by_name']??'');
        $row('Approved By',     $d['approved_by_name']??'');
        if($d['notes']) $row('Notes',$d['notes']);
        $pdf->Ln(8);
        $pdf->SetFont('Arial','',9); $pdf->SetTextColor(70,70,70);
        $pdf->SetX($lm); $pdf->MultiCell($pw,5.5,'This certificate confirms that the above-described asset has been officially disposed of from the G2 Group asset register.',0,'L');
        $pdf->Ln(10);
        $pdf->SetDrawColor(80,80,80); $pdf->SetLineWidth(0.4);
        $pdf->Line($lm,$pdf->GetY(),$lm+70,$pdf->GetY());
        $pdf->Ln(3); $pdf->SetFont('Arial','',8); $pdf->SetTextColor(130,130,130);
        $pdf->SetX($lm); $pdf->Cell(70,5,'Authorized Signature',0,0,'C');
        $pdf->SetX($lm+90); $pdf->Cell(76,5,'Date: '.date('d M Y'),0,1);
        db()->prepare("UPDATE asset_disposals SET certificate_generated=1 WHERE id=?")->execute([$d['id']]);
        $pdf->Output('D','disposal_cert_'.$d['tag'].'_'.date('Ymd').'.pdf'); exit;
    }
}

// ── POST Actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit' && is_it_admin()) {
        $aid    = (int)$_POST['asset_id'];
        $reason = $_POST['reason'] ?? 'other';
        $method = $_POST['method'] ?? 'other';
        $date   = $_POST['disposed_at'] ?? date('Y-m-d');
        $proceeds = $_POST['proceeds'] !== '' ? (float)$_POST['proceeds'] : null;
        $notes  = trim($_POST['notes'] ?? '');

        // Check asset exists and not already disposed
        $exists = db()->prepare("SELECT id FROM asset_disposals WHERE asset_id=?");
        $exists->execute([$aid]);
        if (!$exists->fetchColumn()) {
            db()->prepare("INSERT INTO asset_disposals (asset_id,reason,method,proceeds,disposed_by,disposed_at,notes) VALUES (?,?,?,?,?,?,?)")
              ->execute([$aid,$reason,$method,$proceeds,$_SESSION['g2_user']['id'],$date,$notes]);
            db()->prepare("UPDATE assets SET status='disposed' WHERE id=?")->execute([$aid]);
            // Return any active assignment
            db()->prepare("UPDATE asset_assignments SET returned_at=NOW() WHERE asset_id=? AND returned_at IS NULL")->execute([$aid]);
            asset_log($aid,'disposed','Submitted for disposal: '.$reason);
            $_SESSION['flash'] = ['type'=>'ok','msg'=>'Disposal recorded. Awaiting approval.'];
        } else {
            $_SESSION['flash'] = ['type'=>'err','msg'=>'Asset already has a disposal record.'];
        }
        header('Location: disposal.php'); exit;
    }

    if ($action === 'approve' && is_it_admin()) {
        $did = (int)$_POST['disposal_id'];
        db()->prepare("UPDATE asset_disposals SET approved_by=?,approved_at=NOW() WHERE id=?")->execute([$_SESSION['g2_user']['id'],$did]);
        $aid = db()->prepare("SELECT asset_id FROM asset_disposals WHERE id=?");
        $aid->execute([$did]); $aid = $aid->fetchColumn();
        if ($aid) asset_log($aid,'disposal_approved','Disposal approved');
        $_SESSION['flash'] = ['type'=>'ok','msg'=>'Disposal approved.'];
        header('Location: disposal.php'); exit;
    }
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$disposals = db()->query("SELECT ad.*, a.tag, a.name asset_name, a.purchase_value,
    c.name cat_name, u.name disposed_by_name, ab.name approved_by_name
    FROM asset_disposals ad
    JOIN assets a ON a.id=ad.asset_id
    LEFT JOIN asset_categories c ON c.id=a.category_id
    LEFT JOIN users u ON u.id=ad.disposed_by
    LEFT JOIN users ab ON ab.id=ad.approved_by
    ORDER BY ad.created_at DESC")->fetchAll();

// Assets eligible for disposal (active/retired/lost, no existing disposal)
$eligible = db()->query("SELECT a.id,a.tag,a.name,a.purchase_value,c.name cat_name
    FROM assets a
    LEFT JOIN asset_categories c ON c.id=a.category_id
    LEFT JOIN asset_disposals ad ON ad.asset_id=a.id
    WHERE a.status != 'disposed' AND ad.id IS NULL
    ORDER BY a.name")->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Asset Disposal — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
.pw{padding:28px 36px 60px;max-width:1000px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a;margin:0}
.flash{padding:11px 16px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:16px}
.flash-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.flash-err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:20px 22px;margin-bottom:16px}
.panel h2{font-size:13px;font-weight:800;color:#1a1a1a;margin:0 0 14px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#aaa;padding:10px 14px;border-bottom:1.5px solid #eef0f3;text-align:left;background:#fafbfc;white-space:nowrap}
td{padding:10px 14px;border-bottom:1px solid #f5f6f8;vertical-align:middle}
tr:last-child td{border-bottom:none}tr:hover td{background:#fafbfc}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
.btn-sm{padding:5px 12px;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:4px}
.btn-red{background:#FF3D33;color:#fff}.btn-red:hover{opacity:.88}
.btn-green{background:#dcfce7;color:#166534}
.btn-grey{background:#f1f5f9;color:#444}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">Asset Disposal</span>
</div>
<div class="pw">

  <?php if ($flash): ?>
  <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <!-- Submit new disposal -->
  <?php if (is_it_admin() && $eligible): ?>
  <div class="panel">
    <h2>New Disposal Request</h2>
    <form method="POST">
      <input type="hidden" name="action" value="submit">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px">
        <div>
          <label style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Asset</label>
          <select name="asset_id" required style="width:100%;padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
            <option value="">— Select asset —</option>
            <?php foreach ($eligible as $e): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['tag'].' — '.$e['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Reason</label>
          <select name="reason" required style="width:100%;padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
            <?php foreach(['end_of_life'=>'End of Life','damaged'=>'Damaged','sold'=>'Sold','donated'=>'Donated','stolen'=>'Stolen','other'=>'Other'] as $k=>$v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Disposal Method</label>
          <select name="method" required style="width:100%;padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
            <?php foreach(['trash'=>'Trash','sold'=>'Sold','donated'=>'Donated','returned'=>'Returned to Vendor','recycled'=>'Recycled','other'=>'Other'] as $k=>$v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Disposal Date</label>
          <input type="date" name="disposed_at" value="<?= date('Y-m-d') ?>" required style="width:100%;padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Proceeds (QAR, if sold)</label>
          <input type="number" name="proceeds" step="0.01" placeholder="0.00" style="width:100%;padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Notes</label>
          <input type="text" name="notes" placeholder="Optional notes" style="width:100%;padding:8px 10px;border:1.5px solid #e0e2e8;border-radius:6px;font-size:13px;font-family:inherit">
        </div>
      </div>
      <button class="btn-sm btn-red" type="submit">Submit Disposal</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Disposal list -->
  <div class="panel">
    <h2>Disposal Records <span style="font-weight:400;color:#aaa">(<?= count($disposals) ?>)</span></h2>
    <?php if (!$disposals): ?>
    <p style="color:#ccc;text-align:center;padding:32px 0">No disposals recorded yet.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>Asset</th><th>Reason</th><th>Method</th><th>Date</th><th style="text-align:right">Proceeds</th><th>Disposed By</th><th>Approval</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($disposals as $d): ?>
      <tr>
        <td>
          <div style="font-weight:600"><?= htmlspecialchars($d['asset_name']) ?></div>
          <div style="font-size:11px;color:#aaa;font-family:monospace"><?= htmlspecialchars($d['tag']) ?></div>
        </td>
        <td><?= htmlspecialchars(ucfirst(str_replace('_',' ',$d['reason']))) ?></td>
        <td><?= htmlspecialchars(ucfirst($d['method'])) ?></td>
        <td style="color:#888;white-space:nowrap"><?= date('d M Y',strtotime($d['disposed_at'])) ?></td>
        <td style="text-align:right"><?= $d['proceeds'] ? 'QAR '.number_format($d['proceeds'],2) : '—' ?></td>
        <td style="color:#555"><?= htmlspecialchars($d['disposed_by_name']??'—') ?></td>
        <td>
          <?php if ($d['approved_by']): ?>
            <span class="badge" style="background:#f0fdf4;color:#16a34a">✓ <?= htmlspecialchars($d['approved_by_name']) ?></span>
          <?php else: ?>
            <?php if (is_it_admin()): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="disposal_id" value="<?= $d['id'] ?>">
              <button class="btn-sm btn-green" type="submit">Approve</button>
            </form>
            <?php else: ?>
            <span class="badge" style="background:#fffbeb;color:#d97706">Pending</span>
            <?php endif; ?>
          <?php endif; ?>
        </td>
        <td>
          <div style="display:flex;gap:6px">
            <a class="btn-sm btn-grey" href="view.php?id=<?= $d['asset_id'] ?>">View</a>
            <?php if ($d['approved_by'] && is_it_admin()): ?>
            <a class="btn-sm btn-grey" href="disposal.php?cert=<?= $d['id'] ?>">📄 Certificate</a>
            <?php endif; ?>
          </div>
        </td>
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
