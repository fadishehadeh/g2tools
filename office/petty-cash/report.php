<?php
session_start();
require '../../config.php';
require_login();
$user = current_user();

// ── Office selection — staff locked to their assigned office ──────────────────
$user_office = $user['office'] ?? null;
if ($user_office === '') $user_office = null;

if (is_admin()) {
    $office = $_GET['office'] ?? $user_office ?? 'doha';
    if (!array_key_exists($office, OFFICES)) $office = 'doha';
} else {
    if (!$user_office) { header('Location: index.php'); exit; }
    $office = $user_office;
}
$o = OFFICES[$office];

// ── Filters ───────────────────────────────────────────────────────────────────
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');
$cat    = $_GET['cat']    ?? '';
$status = $_GET['status'] ?? '';
$yr     = (int)date('Y', strtotime($from));  // year for YTD

$categories = ['Transport','Meals & Entertainment','Office Supplies','Utilities','Maintenance','Other'];
$statuses   = ['pending','approved','rejected','paid'];

// ── Data fetch ────────────────────────────────────────────────────────────────
function fetch_rows(string $from, string $to, string $cat, string $status, string $office): array {
    $sql = "SELECT r.id, r.amount, r.category, r.description, r.status, r.created_at, r.paid_at,
                   u.name AS employee,
                   rev.name AS reviewer
            FROM petty_cash_requests r
            JOIN users u ON u.id = r.user_id
            LEFT JOIN users rev ON rev.id = r.reviewed_by
            WHERE r.office = ?
              AND DATE(r.created_at) BETWEEN ? AND ?";
    $params = [$office, $from, $to];
    if ($cat)    { $sql .= " AND r.category = ?"; $params[] = $cat; }
    if ($status) { $sql .= " AND r.status = ?";   $params[] = $status; }
    $sql .= " ORDER BY r.created_at DESC";
    $stmt = db()->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll();
}

// YTD rows (Jan 1 of filter year → today, no cat/status filter, for the trend panel)
function fetch_ytd_rows(int $year, string $office): array {
    $stmt = db()->prepare(
        "SELECT r.amount, r.created_at, r.category, u.name AS employee
         FROM petty_cash_requests r
         JOIN users u ON u.id = r.user_id
         WHERE r.office = ?
           AND YEAR(r.created_at) = ?
           AND r.status != 'rejected'
         ORDER BY r.created_at"
    );
    $stmt->execute([$office, $year]);
    return $stmt->fetchAll();
}

$rows     = fetch_rows($from, $to, $cat, $status, $office);
$ytd_rows = fetch_ytd_rows($yr, $office);

// ── Aggregations ──────────────────────────────────────────────────────────────
$total = array_sum(array_column($rows, 'amount'));
$by_cat = []; $by_status = []; $by_emp = [];
foreach ($rows as $r) {
    $by_cat[$r['category']]  = ($by_cat[$r['category']]  ?? 0) + $r['amount'];
    $by_status[$r['status']] = ($by_status[$r['status']] ?? 0) + 1;
    $by_emp[$r['employee']]  = ($by_emp[$r['employee']]  ?? 0) + $r['amount'];
}
arsort($by_cat); arsort($by_emp);

// Monthly trend with YTD running total
$by_month = []; $ytd_by_month = [];
foreach ($ytd_rows as $r) {
    $mo = date('Y-m', strtotime($r['created_at']));
    $by_month[$mo] = ($by_month[$mo] ?? 0) + $r['amount'];
}
ksort($by_month);
$ytd_running = 0;
foreach ($by_month as $mo => $amt) {
    $ytd_running += $amt;
    $ytd_by_month[$mo] = $ytd_running;
}

// ── Excel export ──────────────────────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'xlsx') {
    require 'xlsx.php';
    $xls = new XlsxWriter();

    // Sheet 1: Raw data
    $data_rows = [['#','Date','Employee','Category','Description','Amount ('.$o['currency'].')','Status','Reviewed By','Paid At']];
    foreach ($rows as $r) {
        $data_rows[] = [
            $r['id'],
            date('d M Y', strtotime($r['created_at'])),
            $r['employee'],
            $r['category'],
            $r['description'],
            (float)$r['amount'],
            $r['status'],
            $r['reviewer'] ?? '',
            $r['paid_at'] ? date('d M Y', strtotime($r['paid_at'])) : '',
        ];
    }
    $data_rows[] = ['','','','','Total', $total, '', '', ''];
    $xls->addSheet('Requests', $data_rows,
        ['text','text','text','text','text','number','text','text','text']);

    // Sheet 2: Pivot — category × month
    $pivot_cats  = array_keys($by_cat ?: ['' => 0]);
    $pivot_months = array_keys($by_month ?: []);
    // rebuild pivot from raw data (no cat/status filter applied)
    $all_rows = fetch_rows(date('Y-01-01'), date('Y-m-d'), '', '', $office);
    $pivot = [];
    $p_months = []; $p_cats = [];
    foreach ($all_rows as $r) {
        $mo = date('M Y', strtotime($r['created_at']));
        $c  = $r['category'];
        $p_months[$mo] = true; $p_cats[$c] = true;
        $pivot[$c][$mo] = ($pivot[$c][$mo] ?? 0) + $r['amount'];
    }
    $p_months = array_keys($p_months); $p_cats = array_keys($p_cats);
    $header = array_merge(['Category'], $p_months, ['Total']);
    $pivot_types = array_fill(0, count($header), 'number');
    $pivot_types[0] = 'text';
    $pivot_rows = [$header];
    $col_totals = array_fill(0, count($p_months), 0);
    foreach ($p_cats as $c) {
        $row_vals = [$c];
        $row_total = 0;
        foreach ($p_months as $mi => $mo) {
            $v = $pivot[$c][$mo] ?? 0;
            $row_vals[] = $v;
            $row_total += $v;
            $col_totals[$mi] += $v;
        }
        $row_vals[] = $row_total;
        $pivot_rows[] = $row_vals;
    }
    $totals_row = ['Total'];
    foreach ($col_totals as $ct) $totals_row[] = $ct;
    $totals_row[] = array_sum($col_totals);
    $pivot_rows[] = $totals_row;
    $xls->addSheet('Pivot — Category×Month', $pivot_rows, $pivot_types);

    // Sheet 3: By Employee
    $emp_types = ['text','number','text'];
    $emp_rows  = [['Employee', 'Total ('.$o['currency'].')', '% of Total']];
    foreach ($by_emp as $emp => $amt) {
        $emp_rows[] = [$emp, (float)$amt, $total > 0 ? round($amt/$total*100,1).'%' : '0%'];
    }
    $xls->addSheet('By Employee', $emp_rows, $emp_types);

    // Sheet 4: Monthly YTD
    $ytd_types = ['text','number','number'];
    $ytd_xls   = [['Month', 'Monthly ('.$o['currency'].')', 'YTD ('.$o['currency'].')']];
    foreach ($by_month as $mo => $amt) {
        $ytd_xls[] = [date('M Y', strtotime($mo.'-01')), (float)$amt, (float)$ytd_by_month[$mo]];
    }
    $xls->addSheet('Monthly Trend', $ytd_xls, $ytd_types);

    $xls->output('petty_cash_'.$office.'_'.$from.'_'.$to.'.xlsx');
}

// ── CSV export (kept for quick access) ───────────────────────────────────────
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="petty_cash_'.$office.'_'.$from.'_'.$to.'.csv"');
    $f = fopen('php://output', 'w');
    fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($f, ['#','Date','Employee','Category','Description','Amount ('.$o['currency'].')','Status','Approved By','Paid At']);
    foreach ($rows as $r) {
        fputcsv($f, [$r['id'],date('d M Y',strtotime($r['created_at'])),$r['employee'],$r['category'],$r['description'],number_format($r['amount'],2),$r['status'],$r['reviewer']??'',$r['paid_at']?date('d M Y',strtotime($r['paid_at'])):'']);
    }
    fclose($f); exit;
}

// ── PDF export ────────────────────────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'pdf') {
    require_once '../../amex/lib/fpdf.php';

    // FPDF is Latin-1 only — convert UTF-8 strings before passing to any Cell/MultiCell
    function pc_str(string $s): string {
        return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s) ?: $s;
    }

    class PDF_PettyCash extends FPDF {
        public string $office_label = '';
        public string $period = '';
        function Header() {
            $logo = __DIR__.'/../../logo.png';
            if (file_exists($logo)) $this->Image($logo, 14, 12, 28, 14, 'PNG');
            $this->SetFont('Arial','B',13);
            $this->SetTextColor(20,20,20);
            $this->SetXY(50, 12);
            $this->Cell(0, 6, pc_str('Petty Cash Report - '.$this->office_label), 0, 1, 'L');
            $this->SetFont('Arial','',9);
            $this->SetTextColor(150,150,150);
            $this->SetX(50);
            $this->Cell(0, 5, pc_str('Period: '.$this->period), 0, 1, 'L');
            $this->SetDrawColor(255,61,51);
            $this->SetLineWidth(0.6);
            $this->Line(14, 32, 283, 32);
            $this->SetLineWidth(0.2);
            $this->SetDrawColor(200,200,200);
            $this->Ln(4);
        }
        function Footer() {
            $this->SetY(-13);
            $this->SetFont('Arial','I',8);
            $this->SetTextColor(180,180,180);
            $this->Cell(0, 5, pc_str('G2 Group - Petty Cash Report - Page '.$this->PageNo()), 0, 0, 'C');
        }
    }

    $pdf = new PDF_PettyCash('L','mm','A4');
    $pdf->office_label = $o['label'];
    $pdf->period = date('d M Y', strtotime($from)).' - '.date('d M Y', strtotime($to));
    $pdf->AddPage();
    $pdf->SetMargins(14,38,14);
    $pdf->SetAutoPageBreak(true, 18);

    // Summary row
    $total_amt = array_sum(array_column($rows, 'amount'));
    $pending   = count(array_filter($rows, fn($r)=>$r['status']==='pending'));
    $paid      = count(array_filter($rows, fn($r)=>$r['status']==='paid'));

    $pdf->SetFont('Arial','B',9); $pdf->SetTextColor(80,80,80);
    $pdf->Cell(55,7,'Total Requests: '.count($rows),0,0);
    $pdf->Cell(65,7,'Total Amount: '.$o['currency'].' '.number_format($total_amt,2),0,0);
    $pdf->Cell(40,7,'Pending: '.$pending,0,0);
    $pdf->Cell(40,7,'Paid: '.$paid,0,1);
    $pdf->Ln(2);

    // Column widths — total 255 = fits A4 landscape (297 - 14*2 margins = 269 usable)
    // #, Date, Employee, Category, Description, Amount, Status, Approved By, Paid Date
    $widths  = [10, 24, 38, 32, 68, 26, 20, 32, 25];
    $headers = ['#', 'Date', 'Employee', 'Category', 'Description', 'Amt ('.$o['currency'].')', 'Status', 'Approved By', 'Paid Date'];

    $pdf->SetFillColor(30,30,30); $pdf->SetTextColor(255,255,255);
    $pdf->SetFont('Arial','B',8);
    foreach ($headers as $i => $h) {
        $align = ($i === 5) ? 'R' : 'L';
        $pdf->Cell($widths[$i], 7, pc_str($h), 0, 0, $align, true);
    }
    $pdf->Ln();

    $pdf->SetFont('Arial','',8); $fill = false;
    foreach ($rows as $r) {
        $bg = $fill ? [248,249,251] : [255,255,255];
        $pdf->SetFillColor(...$bg);
        $status_colors_pdf = ['pending'=>[180,120,0],'approved'=>[22,120,50],'rejected'=>[180,30,30],'paid'=>[8,110,160]];
        $desc = mb_strimwidth($r['description'] ?? '', 0, 50, '...');
        $cells = [
            $r['id'],
            date('d M Y', strtotime($r['created_at'])),
            $r['employee'] ?? '',
            $r['category'] ?? '',
            $desc,
            number_format($r['amount'], 2),
            ucfirst($r['status']),
            $r['reviewer'] ?? '-',
            $r['paid_at'] ? date('d M Y', strtotime($r['paid_at'])) : '-',
        ];
        foreach ($cells as $i => $cell) {
            $align = ($i === 5) ? 'R' : 'L';
            if ($i === 6) {
                [$sr,$sg,$sb] = $status_colors_pdf[$r['status']] ?? [100,100,100];
                $pdf->SetTextColor($sr,$sg,$sb);
            } else {
                $pdf->SetTextColor(40,40,40);
            }
            $pdf->Cell($widths[$i], 6, pc_str((string)$cell), 0, 0, $align, true);
        }
        $pdf->Ln();
        $pdf->SetDrawColor(230,232,236);
        $pdf->Line(14, $pdf->GetY(), 269, $pdf->GetY());
        $fill = !$fill;
    }

    // Total row
    $pdf->Ln(1);
    $pdf->SetFillColor(245,246,248); $pdf->SetTextColor(20,20,20);
    $pdf->SetFont('Arial','B',8);
    $pdf->Cell(array_sum(array_slice($widths,0,5)), 7, 'Total', 0, 0, 'L', true);
    $pdf->Cell($widths[5], 7, number_format($total_amt,2), 0, 0, 'R', true);
    $pdf->Cell(array_sum(array_slice($widths,6)), 7, '', 0, 1, 'L', true);

    $pdf->Output('D', 'petty_cash_'.$office.'_'.date('Ymd').'.pdf');
    exit;
}

$qs = http_build_query(array_filter(['from'=>$from,'to'=>$to,'cat'=>$cat,'status'=>$status]));
$status_colors = ['pending'=>['#fffbeb','#d97706'],'approved'=>['#f0fdf4','#16a34a'],'rejected'=>['#fef2f2','#dc2626'],'paid'=>['#ecfeff','#0891b2']];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Petty Cash Report — <?= htmlspecialchars($o['label']) ?></title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<style>
*,*::before,*::after{box-sizing:border-box}
.pw{padding:30px 36px 60px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:20px;font-weight:800;color:#1a1a1a}
.export-btns{display:flex;gap:8px;flex-wrap:wrap}
.export-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none}
.btn-xlsx{background:#1d6f42;color:#fff}.btn-xlsx:hover{opacity:.88}
.btn-csv{background:#f1f5f9;color:#555}.btn-csv:hover{background:#e2e8f0}
.btn-pdf{background:#b91c1c;color:#fff}.btn-pdf:hover{opacity:.88}
.office-tabs{display:flex;gap:0;margin-bottom:24px;background:#f5f6f8;border-radius:12px;padding:4px;width:fit-content}
.ot{padding:9px 22px;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;color:#888;transition:all .15s}
.ot.active{background:#fff;color:#1a1a1a;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.filters{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.fg{display:flex;flex-direction:column;gap:4px}
.fg label{font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px}
.fg input,.fg select{padding:8px 12px;border:1.5px solid #dde1e7;border-radius:7px;font-size:13px;font-family:inherit;outline:none}
.fg input:focus,.fg select:focus{border-color:#FF3D33}
.btn-apply{padding:9px 20px;background:#1a1a1a;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer}
.clear-link{font-size:13px;color:#bbb;text-decoration:none}.clear-link:hover{color:#FF3D33}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:14px;margin-bottom:22px}
.card{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:18px 20px}
.card .val{font-size:22px;font-weight:800;color:#1a1a1a;margin-bottom:3px}
.card .lbl{font-size:12px;color:#aaa}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px}
.panel{background:#fff;border:1px solid #e8eaee;border-radius:12px;padding:20px 22px;margin-bottom:16px}
.panel h2{font-size:13px;font-weight:800;color:#1a1a1a;margin:0 0 16px}
.bar-row{display:flex;align-items:center;gap:10px;margin-bottom:9px;font-size:13px}
.bar-label{width:140px;color:#555;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex-shrink:0}
.bar-track{flex:1;background:#f5f6f8;border-radius:20px;height:9px;overflow:hidden}
.bar-fill{height:100%;border-radius:20px;transition:width .4s}
.bar-amt{width:86px;text-align:right;color:#1a1a1a;font-weight:700;font-size:12px;flex-shrink:0}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#aaa;padding:10px 12px;border-bottom:1.5px solid #eef0f3;text-align:left;white-space:nowrap}
td{padding:10px 12px;border-bottom:1px solid #f5f6f8;vertical-align:middle}
tr:last-child td{border-bottom:none}tr:hover td{background:#fafbfc}
.status-badge{display:inline-block;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:700}
.empty{text-align:center;padding:48px;color:#ccc;font-size:14px}
/* YTD trend table */
.trend-tbl{width:100%;border-collapse:collapse;font-size:12.5px}
.trend-tbl th{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#aaa;padding:7px 10px;border-bottom:1.5px solid #eef0f3;text-align:right}
.trend-tbl th:first-child{text-align:left}
.trend-tbl td{padding:7px 10px;border-bottom:1px solid #f5f6f8;text-align:right}
.trend-tbl td:first-child{text-align:left;color:#555}
.trend-tbl tr:last-child td{border-bottom:none;font-weight:700}
.ytd-cell{color:#7c3aed;font-weight:700}
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php?office=<?= $office ?>">← Petty Cash</a>
  <span class="topbar-title">Report — <?= htmlspecialchars($o['flag'].' '.$o['label']) ?></span>
</div>
<div class="pw">


  <div class="ph">
    <h1><?= $o['flag'] ?> <?= htmlspecialchars($o['label']) ?> — Petty Cash Report</h1>
    <div class="export-btns">
      <a class="export-btn btn-xlsx" href="?office=<?= $office ?>&<?= $qs ?>&export=xlsx">⬇ Excel (with pivot)</a>
      <a class="export-btn btn-csv"  href="?office=<?= $office ?>&<?= $qs ?>&export=csv">CSV</a>
      <a class="export-btn btn-pdf"  href="?office=<?= $office ?>&<?= $qs ?>&export=pdf">⬇ PDF</a>
    </div>
  </div>

  <form class="filters" method="GET">
    <input type="hidden" name="office" value="<?= $office ?>">
    <div class="fg"><label>From</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></div>
    <div class="fg"><label>To</label><input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></div>
    <div class="fg">
      <label>Category</label>
      <select name="cat">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?><option value="<?= $c ?>" <?= $cat===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="fg">
      <label>Status</label>
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;gap:8px;align-items:flex-end">
      <button class="btn-apply" type="submit">Apply</button>
      <a class="clear-link" href="report.php?office=<?= $office ?>">Clear</a>
    </div>
  </form>

  <!-- Summary cards -->
  <div class="cards">
    <div class="card"><div class="val"><?= $o['currency'] ?> <?= number_format($total,2) ?></div><div class="lbl">Total Spent</div></div>
    <div class="card"><div class="val"><?= count($rows) ?></div><div class="lbl">Requests</div></div>
    <div class="card"><div class="val"><?= count($rows) ? $o['currency'].' '.number_format($total/count($rows),2) : '—' ?></div><div class="lbl">Avg per Request</div></div>
    <div class="card"><div class="val"><?= ($by_status['paid'] ?? 0) ?></div><div class="lbl">Paid</div></div>
    <div class="card"><div class="val"><?= ($by_status['pending'] ?? 0) ?></div><div class="lbl">Pending</div></div>
    <div class="card"><div class="val" style="color:#dc2626"><?= ($by_status['rejected'] ?? 0) ?></div><div class="lbl">Rejected</div></div>
  </div>

  <!-- Three panels: Category | Employee | Monthly YTD -->
  <div class="grid3">

    <!-- By Category -->
    <div class="panel">
      <h2>Spend by Category</h2>
      <?php if (empty($by_cat)): ?>
        <p style="color:#ccc;font-size:13px">No data</p>
      <?php else: $max_cat = max($by_cat);
        foreach ($by_cat as $cn => $ca): $pct = $max_cat>0 ? round($ca/$max_cat*100) : 0; ?>
        <div class="bar-row">
          <div class="bar-label" title="<?= htmlspecialchars($cn) ?>"><?= htmlspecialchars($cn) ?></div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#FF3D33"></div></div>
          <div class="bar-amt"><?= number_format($ca,2) ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- By Employee -->
    <div class="panel">
      <h2>Spend by Employee</h2>
      <?php if (empty($by_emp)): ?>
        <p style="color:#ccc;font-size:13px">No data</p>
      <?php else: $max_emp = max($by_emp);
        foreach ($by_emp as $en => $ea): $pct = $max_emp>0 ? round($ea/$max_emp*100) : 0; ?>
        <div class="bar-row">
          <div class="bar-label" title="<?= htmlspecialchars($en) ?>"><?= htmlspecialchars($en) ?></div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#0891b2"></div></div>
          <div class="bar-amt"><?= number_format($ea,2) ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Monthly trend + YTD -->
    <div class="panel">
      <h2>Monthly Trend <span style="font-weight:400;color:#7c3aed;font-size:11px">YTD <?= $yr ?></span></h2>
      <?php if (empty($by_month)): ?>
        <p style="color:#ccc;font-size:13px">No data</p>
      <?php else: ?>
      <table class="trend-tbl">
        <thead><tr><th style="text-align:left">Month</th><th>Monthly</th><th>YTD</th></tr></thead>
        <tbody>
        <?php foreach ($by_month as $mo => $amt): ?>
        <tr>
          <td><?= date('M Y', strtotime($mo.'-01')) ?></td>
          <td><?= number_format($amt,2) ?></td>
          <td class="ytd-cell"><?= number_format($ytd_by_month[$mo],2) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
          <td>Total</td>
          <td><?= number_format(array_sum($by_month),2) ?></td>
          <td class="ytd-cell"><?= number_format(end($ytd_by_month) ?: 0,2) ?></td>
        </tr></tfoot>
      </table>
      <?php endif; ?>
    </div>

  </div>

  <!-- Requests table -->
  <div class="panel">
    <h2>All Requests <?php if(count($rows)): ?><span style="font-weight:400;color:#aaa">(<?= count($rows) ?>)</span><?php endif; ?></h2>
    <?php if (empty($rows)): ?>
      <div class="empty">No requests match the selected filters.</div>
    <?php else: ?>
    <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>Date</th><th>Employee</th><th>Category</th><th>Description</th>
        <th style="text-align:right">Amount (<?= $o['currency'] ?>)</th>
        <th>OCR</th><th>Status</th><th>Reviewed By</th><th>Paid</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r):
        [$bg,$fg] = $status_colors[$r['status']] ?? ['#f5f5f5','#888'];
        $ocr_amt   = $r['ocr_amount'] ?? null;
        $ocr_flag  = ($ocr_amt !== null && $ocr_amt > 0 && abs($ocr_amt - $r['amount']) > 0.5);
      ?>
      <tr>
        <td style="color:#ccc"><?= $r['id'] ?></td>
        <td style="color:#888;white-space:nowrap"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
        <td style="font-weight:600"><?= htmlspecialchars($r['employee']) ?></td>
        <td><?= htmlspecialchars($r['category']) ?></td>
        <td style="color:#555;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['description']) ?></td>
        <td style="font-weight:700;text-align:right"><?= number_format($r['amount'],2) ?></td>
        <td style="white-space:nowrap">
          <?php if ($ocr_flag): ?>
            <span title="Receipt reads <?= $o['currency'] ?> <?= number_format($ocr_amt,2) ?>" style="background:#fef2f2;color:#dc2626;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;cursor:help">⚠ <?= number_format($ocr_amt,2) ?></span>
          <?php elseif ($ocr_amt > 0): ?>
            <span style="background:#f0fdf4;color:#16a34a;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700">✓ OK</span>
          <?php elseif (!empty($r['receipt'])): ?>
            <span style="color:#ccc;font-size:11px">—</span>
          <?php else: ?>
            <span style="color:#e5e7eb;font-size:11px">no receipt</span>
          <?php endif; ?>
        </td>
        <td><span class="status-badge" style="background:<?= $bg ?>;color:<?= $fg ?>"><?= ucfirst($r['status']) ?></span></td>
        <td style="color:#888"><?= htmlspecialchars($r['reviewer'] ?? '—') ?></td>
        <td style="color:#888;white-space:nowrap"><?= $r['paid_at'] ? date('d M Y',strtotime($r['paid_at'])) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5" style="font-weight:700;color:#555;padding-top:12px">Total</td>
          <td style="font-weight:800;text-align:right;padding-top:12px"><?= number_format($total,2) ?></td>
          <td colspan="4"></td>
        </tr>
      </tfoot>
    </table>
    </div>
    <?php endif; ?>
  </div>

</div>
</div>
</body>
</html>
