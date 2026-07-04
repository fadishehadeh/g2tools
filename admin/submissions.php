<?php
session_start();
require '../config.php';
require_admin();

$user = current_user();

// ── Filters & sort ────────────────────────────────────────────────────────────
$filter_type = $_GET['type']      ?? '';
$filter_user = (int)($_GET['user_id'] ?? 0);
$search      = trim($_GET['q']    ?? '');
$date_from   = $_GET['from']      ?? '';
$date_to     = $_GET['to']        ?? '';
$sort        = $_GET['sort']      ?? 'created_at';
$dir         = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$allowed_sorts = ['id', 'form_type', 'user_name', 'created_at'];
if (!in_array($sort, $allowed_sorts)) $sort = 'created_at';

$sql    = "SELECT s.id, s.form_type, s.form_data, s.created_at,
                  COALESCE(u.name, '— Public —') AS user_name,
                  COALESCE(u.email, '') AS user_email
           FROM form_submissions s
           LEFT JOIN users u ON s.user_id = u.id
           WHERE 1=1";
$params = [];

if ($filter_type) { $sql .= " AND s.form_type = ?";  $params[] = $filter_type; }
if ($filter_user) { $sql .= " AND s.user_id = ?";    $params[] = $filter_user; }
if ($search) {
    $like = '%' . $search . '%';
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR s.form_data LIKE ? OR s.form_type LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($date_from) { $sql .= " AND DATE(s.created_at) >= ?"; $params[] = $date_from; }
if ($date_to)   { $sql .= " AND DATE(s.created_at) <= ?"; $params[] = $date_to;   }

$sort_col = $sort === 'user_name' ? 'u.name' : 's.' . $sort;
$sql .= " ORDER BY $sort_col $dir";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$submissions = $stmt->fetchAll();

$users_list = db()->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

const FORM_LABELS = [
    'amex'           => 'Credit Card Auth',
    'accountability' => 'Accountability',
    'debit_note'     => 'Debit Note',
    'credit_note'    => 'Credit Note',
    'vendor_recon'   => 'Vendor Recon',
    'vendor_reg'     => 'Vendor Registration',
];
const FORM_BADGE_COLORS = [
    'amex'           => ['#fff0f0','#FF3D33'],
    'accountability' => ['#f0f4ff','#4466dd'],
    'debit_note'     => ['#fff8ec','#d97706'],
    'credit_note'    => ['#f0fdf4','#16a34a'],
    'vendor_recon'   => ['#f5f3ff','#7c3aed'],
    'vendor_reg'     => ['#ecfeff','#0891b2'],
];
function form_label(string $t): string {
    return FORM_LABELS[$t] ?? ucwords(str_replace('_',' ',$t));
}
function form_badge(string $t): string {
    [$bg,$fg] = FORM_BADGE_COLORS[$t] ?? ['#f5f5f5','#888'];
    return '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:'.$bg.';color:'.$fg.'">'.htmlspecialchars(form_label($t)).'</span>';
}
function form_summary(string $t, array $d): string {
    return match($t) {
        'amex'           => (empty($d['co_name']) ? '' : $d['co_name'].' — ') . ($d['merchant'] ?? '—'),
        'accountability' => ($d['item_name'] ?? '—'),
        'debit_note'     => (empty($d['co_name']) ? '' : $d['co_name'].' — ') . 'To: '.($d['to_name'] ?? '—'),
        'credit_note'    => (empty($d['co_name']) ? '' : $d['co_name'].' — ') . 'To: '.($d['to_name'] ?? '—'),
        'vendor_recon'   => (empty($d['co_name']) ? '' : $d['co_name'].' — ') . ($d['vendor_name'] ?? '—'),
        'vendor_reg'     => ($d['legal_name'] ?? '—') . (empty($d['city']) ? '' : ', '.$d['city']),
        default          => '—',
    };
}
function form_serial(string $t, array $d): string {
    return match($t) {
        'amex'           => $d['serial_number'] ?? ($d['serial'] ?? ''),
        'accountability' => '',
        'vendor_reg'     => '',
        default          => $d['serial'] ?? '',
    };
}

// ── Sort link helper ──────────────────────────────────────────────────────────
function sort_link(string $col, string $label, string $cur_sort, string $cur_dir, array $base): string {
    $new_dir = ($cur_sort === $col && $cur_dir === 'ASC') ? 'DESC' : 'ASC';
    $params  = array_merge($base, ['sort' => $col, 'dir' => $new_dir]);
    $url     = '?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== 0 && $v !== null));
    $icon    = '';
    if ($cur_sort === $col) $icon = ' <span style="color:#FF3D33">' . ($cur_dir === 'ASC' ? '↑' : '↓') . '</span>';
    return "<a href=\"$url\" style=\"text-decoration:none;color:inherit\">$label$icon</a>";
}

$base_params = array_filter([
    'q'       => $search,
    'type'    => $filter_type,
    'user_id' => $filter_user ?: null,
    'from'    => $date_from,
    'to'      => $date_to,
], fn($v) => $v !== '' && $v !== null);

$export_qs = $base_params ? '&' . http_build_query($base_params) : '';

// ── CSV Export ────────────────────────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="G2_Submissions_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['#', 'Form Type', 'Company / Details', 'Serial', 'Submitted By', 'Email', 'Date']);
    foreach ($submissions as $s) {
        $data = json_decode($s['form_data'], true);
        fputcsv($out, [
            $s['id'], form_label($s['form_type']),
            form_summary($s['form_type'], $data),
            form_serial($s['form_type'], $data),
            $s['user_name'], $s['user_email'],
            date('d M Y H:i', strtotime($s['created_at'])),
        ]);
    }
    fclose($out);
    exit;
}

// ── PDF Export ────────────────────────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'pdf') {
    require '../amex/lib/fpdf.php';
    class ReportPDF extends FPDF {
        function Header() {
            $logo = __DIR__ . '/../logo.png';
            if (file_exists($logo)) $this->Image($logo, 15, 10, 22, 11, 'PNG');
            $this->SetFont('Arial', 'B', 13); $this->SetTextColor(30, 30, 30);
            $this->SetXY(42, 12); $this->Cell(0, 8, 'Submissions Report', 0, 1);
            $this->SetFont('Arial', '', 8); $this->SetTextColor(160, 160, 160);
            $this->SetX(42); $this->Cell(0, 5, 'Generated ' . date('d M Y, H:i'), 0, 1);
            $this->SetDrawColor(255, 61, 51); $this->SetLineWidth(0.6);
            $this->Line(15, 26, 282, 26); $this->SetLineWidth(0.2); $this->Ln(4);
        }
        function Footer() {
            $this->SetY(-12); $this->SetFont('Arial', 'I', 7); $this->SetTextColor(180, 180, 180);
            $this->Cell(0, 5, 'G2 Group  —  Internal Use Only  |  Page ' . $this->PageNo(), 0, 0, 'C');
        }
    }
    $pdf = new ReportPDF('L', 'mm', 'A4');
    $pdf->AddPage(); $pdf->SetMargins(15, 32, 15); $pdf->SetAutoPageBreak(true, 18);
    $cols = [12, 36, 76, 30, 48, 48, 32];
    $hdrs = ['#', 'Form', 'Details', 'Serial', 'Submitted By', 'Email', 'Date'];
    $pdf->SetFillColor(248,249,251); $pdf->SetDrawColor(220,220,220);
    $pdf->SetTextColor(140,140,140); $pdf->SetFont('Arial','B',7);
    foreach ($hdrs as $i => $h) $pdf->Cell($cols[$i], 7, strtoupper($h), 'B', 0, 'L', true);
    $pdf->Ln();
    $pdf->SetFont('Arial','',8.5); $pdf->SetTextColor(40,40,40); $fill = false;
    foreach ($submissions as $s) {
        $data = json_decode($s['form_data'], true);
        $pdf->SetFillColor($fill?250:255,$fill?250:255,$fill?250:255);
        $pdf->Cell($cols[0],6.5,$s['id'],0,0,'L',true);
        $pdf->Cell($cols[1],6.5,form_label($s['form_type']),0,0,'L',true);
        $pdf->Cell($cols[2],6.5,form_summary($s['form_type'],$data),0,0,'L',true);
        $pdf->Cell($cols[3],6.5,form_serial($s['form_type'],$data),0,0,'L',true);
        $pdf->Cell($cols[4],6.5,$s['user_name'],0,0,'L',true);
        $pdf->Cell($cols[5],6.5,$s['user_email'],0,0,'L',true);
        $pdf->Cell($cols[6],6.5,date('d M Y H:i',strtotime($s['created_at'])),0,1,'L',true);
        $fill = !$fill;
    }
    $pdf->SetFont('Arial','B',8); $pdf->SetTextColor(100,100,100);
    $pdf->SetFillColor(248,249,251); $pdf->Ln(2);
    $pdf->Cell(array_sum($cols),6,'Total: '.count($submissions).' submission(s)',0,1,'R',true);
    $pdf->Output('D','G2_Submissions_'.date('Ymd_His').'.pdf');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Submissions — G2 Admin</title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<style>
  *, *::before, *::after { box-sizing: border-box; }

  .page-wrap { padding: 36px 40px 60px; }
  .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
  .page-header h1 { font-size: 22px; font-weight: 800; color: #1a1a1a; }

  .export-btns { display: flex; gap: 10px; }
  .btn-export { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px;
                border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; transition: opacity .15s; }
  .btn-pdf { background: #FF3D33; color: #fff; }
  .btn-csv { background: #1d7e4e; color: #fff; }
  .btn-export:hover { opacity: .85; }

  /* Filters */
  .filters-card { background: #fff; border-radius: 12px; border: 1px solid #e8eaee;
                  padding: 18px 20px; margin-bottom: 20px; }
  .filters-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
  .filter-group { display: flex; flex-direction: column; gap: 5px; }
  .filter-group label { font-size: 11px; font-weight: 700; color: #aaa; text-transform: uppercase; letter-spacing: 0.4px; }
  .filter-group input, .filter-group select {
    padding: 8px 12px; border: 1.5px solid #dde1e7; border-radius: 7px;
    font-size: 13px; font-family: inherit; outline: none; background: #fff;
  }
  .filter-group input:focus, .filter-group select:focus { border-color: #FF3D33; }
  .fg-search { flex: 1; min-width: 220px; }
  .fg-search input { width: 100%; padding-left: 34px; }
  .search-wrap { position: relative; }
  .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #bbb; font-size: 15px; pointer-events: none; }
  .filter-actions { display: flex; gap: 8px; align-items: flex-end; padding-bottom: 1px; }
  .btn-apply { padding: 9px 20px; background: #1a1a1a; color: #fff; border: none;
               border-radius: 7px; font-size: 13px; font-weight: 700; cursor: pointer; }
  .btn-apply:hover { background: #333; }
  .clear-link { font-size: 13px; color: #bbb; text-decoration: none; white-space: nowrap; }
  .clear-link:hover { color: #FF3D33; }

  /* Active filters chips */
  .active-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
  .chip { display: inline-flex; align-items: center; gap: 6px; background: #fff3f2;
          border: 1px solid #ffd5d3; color: #FF3D33; padding: 3px 10px 3px 12px;
          border-radius: 20px; font-size: 12px; font-weight: 600; }
  .chip a { color: #FF3D33; text-decoration: none; font-size: 14px; line-height: 1; }
  .chip a:hover { color: #c0170e; }

  /* Stats */
  .stat-row { display: flex; gap: 16px; margin-bottom: 20px; }
  .stat { background: #fff; border-radius: 10px; border: 1px solid #e8eaee; padding: 16px 22px; flex: 1; }
  .stat .n { font-size: 28px; font-weight: 800; color: #1a1a1a; }
  .stat .l { font-size: 12px; color: #999; margin-top: 2px; }

  /* Table */
  .table-card { background: #fff; border-radius: 14px; border: 1px solid #e8eaee;
                box-shadow: 0 2px 10px rgba(0,0,0,.05); overflow: hidden; }
  table { width: 100%; border-collapse: collapse; }
  thead tr { background: #f8f9fb; }
  th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; color: #999;
       text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eee;
       white-space: nowrap; user-select: none; }
  th a { color: inherit; }
  td { padding: 13px 16px; font-size: 13px; color: #333; border-bottom: 1px solid #f3f3f3; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #fafafa; }

  .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .badge-amex { background: #fff0f0; color: #FF3D33; }
  .badge-acc  { background: #f0f4ff; color: #4466dd; }
  .user-chip { display: inline-flex; align-items: center; gap: 8px; }
  .avatar { width: 28px; height: 28px; border-radius: 50%; background: #FF3D33; color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; }
  .dl-btn { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px;
            background: #FF3D33; color: #fff; border-radius: 20px; font-size: 11px;
            font-weight: 700; text-decoration: none; }
  .dl-btn:hover { background: #e8302a; }
  .no-results { text-align: center; padding: 60px; color: #bbb; font-size: 15px; }

</style>
</head>
<body>

<?php require '../_sidebar.php'; ?>

<div class="main-content">
<div class="topbar"><span class="topbar-title">All Submissions</span></div>
<div class="page-wrap">

  <div class="page-header">
    <h1>All Submissions</h1>
    <div class="export-btns">
      <a class="btn-export btn-pdf" href="?export=pdf<?= $export_qs ?>">⬇ Export PDF</a>
      <a class="btn-export btn-csv" href="?export=csv<?= $export_qs ?>">⬇ Export Excel</a>
    </div>
  </div>

  <?php
    $total = count($submissions);
    $by_type = [];
    foreach ($submissions as $s) $by_type[$s['form_type']] = ($by_type[$s['form_type']] ?? 0) + 1;
    $stat_colors = ['amex'=>'#FF3D33','accountability'=>'#4466dd','debit_note'=>'#d97706','credit_note'=>'#16a34a','vendor_recon'=>'#7c3aed','vendor_reg'=>'#0891b2'];
  ?>
  <div class="stat-row">
    <div class="stat"><div class="n"><?= $total ?></div><div class="l">Showing</div></div>
    <?php foreach (FORM_LABELS as $type => $label): if (($by_type[$type] ?? 0) > 0): ?>
    <div class="stat">
      <div class="n" style="color:<?= $stat_colors[$type] ?? '#888' ?>"><?= $by_type[$type] ?></div>
      <div class="l"><?= $label ?></div>
    </div>
    <?php endif; endforeach; ?>
  </div>

  <!-- Filters -->
  <div class="filters-card">
    <form class="filters-row" method="GET">
      <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
      <input type="hidden" name="dir"  value="<?= htmlspecialchars($dir) ?>">

      <div class="filter-group fg-search">
        <label>Search</label>
        <div class="search-wrap">
          <span class="search-icon">⌕</span>
          <input type="text" name="q" placeholder="Name, email, merchant, item, company…"
                 value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>

      <div class="filter-group">
        <label>Form type</label>
        <select name="type">
          <option value="">All types</option>
          <?php foreach (FORM_LABELS as $type => $label): ?>
          <option value="<?= $type ?>" <?= $filter_type === $type ? 'selected':'' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label>User</label>
        <select name="user_id">
          <option value="0">All users</option>
          <?php foreach ($users_list as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $filter_user === (int)$u['id'] ? 'selected':'' ?>>
              <?= htmlspecialchars($u['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label>Date from</label>
        <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>">
      </div>

      <div class="filter-group">
        <label>Date to</label>
        <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>">
      </div>

      <div class="filter-actions">
        <button class="btn-apply" type="submit">Apply</button>
        <a class="clear-link" href="/g2forms/admin/submissions.php">Clear</a>
      </div>
    </form>

    <!-- Active filter chips -->
    <?php
      $chips = [];
      if ($search)      $chips[] = ['Search: ' . $search,      'q'];
      if ($filter_type) $chips[] = [form_label($filter_type),  'type'];
      if ($filter_user) {
          foreach ($users_list as $ul) if ((int)$ul['id'] === $filter_user) $chips[] = [$ul['name'], 'user_id'];
      }
      if ($date_from) $chips[] = ['From: ' . $date_from, 'from'];
      if ($date_to)   $chips[] = ['To: ' . $date_to,     'to'];
    ?>
    <?php if ($chips): ?>
    <div class="active-filters">
      <?php foreach ($chips as [$label, $param]):
        $without = array_filter($base_params, fn($k) => $k !== $param, ARRAY_FILTER_USE_KEY);
        $href    = '/g2forms/admin/submissions.php' . ($without ? '?' . http_build_query($without) : '');
      ?>
        <span class="chip"><?= htmlspecialchars($label) ?><a href="<?= $href ?>" title="Remove">×</a></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Table -->
  <div class="table-card">
    <?php if (empty($submissions)): ?>
      <div class="no-results">No submissions found.</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th><?= sort_link('id',         '#',             $sort, $dir, $base_params) ?></th>
          <th><?= sort_link('form_type',  'Form',          $sort, $dir, $base_params) ?></th>
          <th>Details</th>
          <th>Serial</th>
          <th><?= sort_link('user_name',  'Submitted By',  $sort, $dir, $base_params) ?></th>
          <th><?= sort_link('created_at', 'Date',          $sort, $dir, $base_params) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($submissions as $s):
          $data     = json_decode($s['form_data'], true);
          $initials = strtoupper(substr($s['user_name'], 0, 1));
        ?>
        <tr>
          <td style="color:#ccc"><?= $s['id'] ?></td>
          <td><?= form_badge($s['form_type']) ?></td>
          <td><?= htmlspecialchars(form_summary($s['form_type'], $data)) ?></td>
          <td style="font-family:monospace;font-size:12px;color:#555">
            <?php $ser = form_serial($s['form_type'], $data); echo $ser ? htmlspecialchars($ser) : '<span style="color:#ddd">—</span>'; ?>
          </td>
          <td>
            <div class="user-chip">
              <div class="avatar" style="background:<?= $s['user_name']==='— Public —'?'#888':'#FF3D33' ?>"><?= $s['user_name']==='— Public —'?'P':$initials ?></div>
              <div>
                <div style="font-weight:600"><?= htmlspecialchars($s['user_name']) ?></div>
                <div style="color:#bbb;font-size:11px"><?= htmlspecialchars($s['user_email']) ?></div>
              </div>
            </div>
          </td>
          <td style="color:#888;white-space:nowrap"><?= date('d M Y, H:i', strtotime($s['created_at'])) ?></td>
          <td><a class="dl-btn" href="/g2forms/download.php?id=<?= $s['id'] ?>">⬇ PDF</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>
</div><!-- .main-content -->
</body>
</html>
