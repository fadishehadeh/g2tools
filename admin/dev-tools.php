<?php
session_start();
require '../config.php';
require_login();
if (!is_admin()) { header('Location: /'); exit; }

$msg   = '';
$mtype = 'ok';

// ── Helpers ──────────────────────────────────────────────────────────────────

function curl_post(string $url, array $fields): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Cookie: PHPSESSID=' . session_id()],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    // generate.php redirects to confirm on success (302)
    return $code === 302 || $code === 200;
}

$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://localhost' . BASE_URL;

// ── Action: Generate sample PDFs ─────────────────────────────────────────────
if ($_POST['action'] ?? '' === 'gen_pdfs') {
    $uid = current_user()['id'];
    $before = glob(STORAGE_PATH . '*.pdf');

    $samples = [
        ['url' => "$base/amex/generate.php", 'fields' => [
            'company'           => 'g2',
            'card_type'         => 'AMEX',
            'cardholder_name'   => 'KRIKOR KHAJIKIAN Grey World Wide LLC',
            'card_last4'        => '5007',
            'merchant'          => 'Adobe Systems',
            'po_number'         => 'PO-2026-0042',
            'billable'          => 'YES',
            'client_name'       => 'Ooredoo',
            'nature_of_expense' => '',
            'currency'          => 'USD',
            'amount'            => '299.00',
            'authorized_name'   => 'Fadi Shehadeh',
            'finance_approval'  => 'Sara Al-Khatib',
            'finance_date'      => '16/07/2026',
            'mgmt_approval'     => 'Krikor Khajikian',
            'mgmt_date'         => '16/07/2026',
        ]],
        ['url' => "$base/accountability/generate.php", 'fields' => [
            'company'        => 'g2',
            'received_by'    => 'Ahmad Nasser',
            'position'       => 'Senior Designer',
            'department'     => 'Creative',
            'item_name'      => 'MacBook Pro 14" (M4)',
            'serial_number'  => 'FVFXQ2ABCD',
            'received_date'  => '16/07/2026',
            'estimated_life' => '3 Years',
            'request_by'     => 'Fadi Shehadeh',
        ]],
        ['url' => "$base/finance/debit-note/generate.php", 'fields' => [
            'company'          => 'g2',
            'to_name'          => 'Al Mana Fashion Group',
            'attention'        => 'Finance Department',
            'dn_date'          => '16/07/2026',
            'currency'         => 'QAR',
            'desc[]'           => ['Social Media Management — June 2026', 'Campaign Creative Production'],
            'amt[]'            => ['15000.00', '8500.00'],
            'total'            => '23500.00',
            'prepared_by'      => 'Cresencia Candava',
            'approved_by'      => 'Krikor Khajikian',
            'attach_invoice'   => '1',
            'attach_contract'  => '0',
            'attach_email'     => '0',
        ]],
        ['url' => "$base/finance/credit-note/generate.php", 'fields' => [
            'company'          => 'g2',
            'to_name'          => 'Vodafone Qatar',
            'attention'        => 'Accounts Payable',
            'dn_date'          => '16/07/2026',
            'currency'         => 'QAR',
            'desc[]'           => ['Credit for overpayment — May 2026'],
            'amt[]'            => ['5000.00'],
            'total'            => '5000.00',
            'prepared_by'      => 'Cresencia Candava',
            'approved_by'      => 'Krikor Khajikian',
            'attach_invoice'   => '1',
            'attach_contract'  => '0',
            'attach_email'     => '1',
        ]],
        ['url' => "$base/finance/vendor-recon/generate.php", 'fields' => [
            'company'            => 'g2',
            'vendor_name'        => 'PrintZone LLC',
            'vendor_no'          => 'V-00418',
            'recon_date'         => '16/07/2026',
            'currency'           => 'QAR',
            'grey_soa_balance'   => '47250.00',
            'vendor_soa_balance' => '46900.00',
            'variance'           => '350.00',
            'net_grey'           => '47250.00',
            'r_date[]'           => ['01/06/2026', '15/06/2026', '30/06/2026'],
            'r_inv[]'            => ['INV-1041', 'INV-1078', 'INV-1095'],
            'r_particular[]'     => ['Printing — Brochures', 'Large Format Print', 'Business Cards x500'],
            'r_po[]'             => ['PO-2026-0038', 'PO-2026-0039', 'PO-2026-0040'],
            'r_amt[]'            => ['18500.00', '22000.00', '6750.00'],
            'total_recon'        => '47250.00',
            'prepared_by'        => 'Cresencia Candava',
            'reviewed_by'        => 'Fadi Shehadeh',
            'approved_by'        => 'Krikor Khajikian',
        ]],
    ];

    $ok = 0;
    foreach ($samples as $s) {
        if (curl_post($s['url'], $s['fields'])) $ok++;
        usleep(200000); // 0.2s gap between requests
    }

    // Find newly created PDFs
    $after    = glob(STORAGE_PATH . '*.pdf');
    $new_pdfs = array_diff($after, $before);

    if (empty($new_pdfs)) {
        $msg   = "Requests sent ($ok/".count($samples).") but no new PDFs found in storage/pdfs/. Check that XAMPP is running and you are logged in as superadmin.";
        $mtype = 'warn';
    } else {
        // Build ZIP in memory
        $zip_path = sys_get_temp_dir() . '/g2_sample_forms_' . date('Ymd_His') . '.zip';
        $zip = new ZipArchive();
        $zip->open($zip_path, ZipArchive::CREATE);
        foreach ($new_pdfs as $f) {
            $zip->addFile($f, basename($f));
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="g2_sample_forms_' . date('Ymd_His') . '.zip"');
        header('Content-Length: ' . filesize($zip_path));
        readfile($zip_path);
        @unlink($zip_path);
        exit;
    }
}

// ── Action: Seed mockup data ─────────────────────────────────────────────────
if ($_POST['action'] ?? '' === 'seed') {
    $uid = current_user()['id'];
    $db  = db();
    $now = date('Y-m-d H:i:s');
    $cnt = 0;

    // Finance form submissions (no PDF files — just DB records for history/reporting)
    $amex_samples = [
        ['G2 Group','amex', json_encode(['company'=>'G2 Group','merchant'=>'Adobe Creative Cloud','serial_number'=>'G2-00001','po_number'=>'PO-2026-0001','billable'=>'YES','client_name'=>'Ooredoo','currency'=>'USD','amount'=>'599.99','authorized_name'=>'Fadi Shehadeh','finance_approval'=>'Sara Al-Khatib','finance_date'=>'01/06/2026','mgmt_approval'=>'Krikor Khajikian','mgmt_date'=>'01/06/2026'])],
        ['G2 Group','amex', json_encode(['company'=>'G2 Group','merchant'=>'AWS (Amazon Web Services)','serial_number'=>'G2-00002','po_number'=>'PO-2026-0002','billable'=>'NO','nature_of_expense'=>'Cloud hosting — internal servers','currency'=>'USD','amount'=>'1240.00','authorized_name'=>'Ahmad Nasser','finance_approval'=>'Sara Al-Khatib','finance_date'=>'05/06/2026','mgmt_approval'=>'Krikor Khajikian','mgmt_date'=>'05/06/2026'])],
        ['Pin & Notch','amex', json_encode(['company'=>'Pin & Notch','merchant'=>'Canva Pro','serial_number'=>'PN-00001','po_number'=>'PO-2026-0003','billable'=>'NO','nature_of_expense'=>'Design subscriptions','currency'=>'USD','amount'=>'149.99','authorized_name'=>'Lara Hassan','finance_approval'=>'Sara Al-Khatib','finance_date'=>'10/06/2026','mgmt_approval'=>'Krikor Khajikian','mgmt_date'=>'10/06/2026'])],
        ['Grey','amex', json_encode(['company'=>'Grey','merchant'=>'Marriott — Doha','serial_number'=>'Grey-00001','po_number'=>'PO-2026-0004','billable'=>'YES','client_name'=>'QNB Group','currency'=>'QAR','amount'=>'8500.00','authorized_name'=>'Karim Mansour','finance_approval'=>'Sara Al-Khatib','finance_date'=>'15/06/2026','mgmt_approval'=>'Krikor Khajikian','mgmt_date'=>'15/06/2026'])],
        ['G2 Group','amex', json_encode(['company'=>'G2 Group','merchant'=>'Microsoft 365','serial_number'=>'G2-00003','po_number'=>'PO-2026-0005','billable'=>'NO','nature_of_expense'=>'Annual M365 subscription','currency'=>'USD','amount'=>'2400.00','authorized_name'=>'Fadi Shehadeh','finance_approval'=>'Sara Al-Khatib','finance_date'=>'20/06/2026','mgmt_approval'=>'Krikor Khajikian','mgmt_date'=>'20/06/2026'])],
    ];
    $stmt = $db->prepare("INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename,created_at) VALUES (?,?,?,NULL,?)");
    foreach ($amex_samples as $i => [$co, $ft, $fd]) {
        $stmt->execute([$uid, $ft, $fd, date('Y-m-d H:i:s', strtotime("-".($i*5)." days"))]);
        $cnt++;
    }

    $acc_samples = [
        json_encode(['company'=>'G2 Group','received_by'=>'Ahmad Nasser','position'=>'Senior Designer','department'=>'Creative','item_name'=>'MacBook Pro 14" M4','serial_number'=>'FVFXQ2ABCD','received_date'=>'01/06/2026','estimated_life'=>'3 Years','request_by'=>'Fadi Shehadeh']),
        json_encode(['company'=>'G2 Group','received_by'=>'Lara Hassan','position'=>'Account Manager','department'=>'Client Services','item_name'=>'iPhone 16 Pro','serial_number'=>'F2LN8WXYZ','received_date'=>'10/06/2026','estimated_life'=>'2 Years','request_by'=>'Fadi Shehadeh']),
        json_encode(['company'=>'Grey','received_by'=>'Karim Mansour','position'=>'Creative Director','department'=>'Creative','item_name'=>'Sony A7 IV Camera','serial_number'=>'5041234','received_date'=>'15/06/2026','estimated_life'=>'4 Years','request_by'=>'Krikor Khajikian']),
    ];
    $stmt2 = $db->prepare("INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename,created_at) VALUES (?,?,?,NULL,?)");
    foreach ($acc_samples as $i => $fd) {
        $stmt2->execute([$uid, 'accountability', $fd, date('Y-m-d H:i:s', strtotime("-".($i*7)." days"))]);
        $cnt++;
    }

    $dn_samples = [
        json_encode(['company'=>'G2 Group','to_name'=>'Al Mana Fashion Group','attention'=>'Finance','dn_date'=>'01/06/2026','currency'=>'QAR','items'=>[['desc'=>'Social Media Management','amt'=>15000],['desc'=>'Campaign Creative Production','amt'=>8500]],'total'=>'23500','prepared_by'=>'Cresencia Candava','approved_by'=>'Krikor Khajikian']),
        json_encode(['company'=>'Grey','to_name'=>'Vodafone Qatar','attention'=>'Marketing','dn_date'=>'15/06/2026','currency'=>'QAR','items'=>[['desc'=>'PR Event Management — June','amt'=>35000]],'total'=>'35000','prepared_by'=>'Cresencia Candava','approved_by'=>'Krikor Khajikian']),
    ];
    $stmt3 = $db->prepare("INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename,created_at) VALUES (?,?,?,NULL,?)");
    foreach ($dn_samples as $i => $fd) {
        $stmt3->execute([$uid, 'debit_note', $fd, date('Y-m-d H:i:s', strtotime("-".($i*10)." days"))]);
        $cnt++;
    }

    $cn_samples = [
        json_encode(['company'=>'G2 Group','to_name'=>'Ooredoo Qatar','attention'=>'Accounts Payable','dn_date'=>'20/06/2026','currency'=>'QAR','items'=>[['desc'=>'Credit for overpayment — May 2026','amt'=>5000]],'total'=>'5000','prepared_by'=>'Cresencia Candava','approved_by'=>'Krikor Khajikian']),
    ];
    foreach ($cn_samples as $fd) {
        $db->prepare("INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename,created_at) VALUES (?,?,?,NULL,?)")
           ->execute([$uid, 'credit_note', $fd, date('Y-m-d H:i:s', strtotime('-3 days'))]);
        $cnt++;
    }

    $vr_samples = [
        json_encode(['company'=>'G2 Group','vendor_name'=>'PrintZone LLC','vendor_no'=>'V-00418','recon_date'=>'30/06/2026','currency'=>'QAR','grey_soa_balance'=>47250,'vendor_soa_balance'=>46900,'variance'=>350,'prepared_by'=>'Cresencia Candava','reviewed_by'=>'Fadi Shehadeh','approved_by'=>'Krikor Khajikian']),
    ];
    foreach ($vr_samples as $fd) {
        $db->prepare("INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename,created_at) VALUES (?,?,?,NULL,?)")
           ->execute([$uid, 'vendor_recon', $fd, date('Y-m-d H:i:s', strtotime('-1 day'))]);
        $cnt++;
    }

    // Petty cash requests
    $pc_doha_cats  = ['Transport','Meals & Entertainment','Office Supplies','Utilities','Maintenance'];
    $pc_beirut_cats = ['Transport','Meals & Entertainment','Office Supplies','Communication','Other'];

    $pc_doha = [
        [45.00, 'Transport', 'Uber to client meeting — Lusail'],
        [320.00, 'Meals & Entertainment', 'Team lunch — 8 pax'],
        [85.50, 'Office Supplies', 'Printer ink cartridges x4'],
        [150.00, 'Transport', 'Airport taxi for client pickup'],
        [220.00, 'Meals & Entertainment', 'Dinner with client — Qatar National Bank'],
        [45.00, 'Office Supplies', 'Whiteboard markers and notebooks'],
        [500.00, 'Utilities', 'Parking permit — monthly renewal'],
        [75.00, 'Maintenance', 'Coffee machine filter replacement'],
        [180.00, 'Transport', 'Careem rides — weekly (3 trips)'],
        [95.00, 'Meals & Entertainment', 'Catering for internal strategy session'],
    ];
    $pc_beirut = [
        [35.00, 'Transport', 'Taxi to DHL for courier pickup'],
        [85.00, 'Meals & Entertainment', 'Working lunch — new client onboarding'],
        [42.50, 'Office Supplies', 'A4 paper ream x5'],
        [60.00, 'Transport', 'Airport transfer — visiting GM'],
        [120.00, 'Communication', 'Mobile top-up reimbursement — 4 staff'],
        [25.00, 'Other', 'Bank charges — wire transfer fee'],
        [55.00, 'Meals & Entertainment', 'Coffee meeting with supplier'],
        [38.00, 'Office Supplies', 'Stapler, scissors, tape'],
    ];

    $pc_stmt = $db->prepare("INSERT INTO petty_cash_requests (user_id,office,amount,category,description,status,created_at) VALUES (?,?,?,?,?,?,?)");
    $statuses = ['paid','paid','unpaid','paid','unpaid','paid','unpaid','paid','unpaid','paid'];
    foreach ($pc_doha as $i => [$amt, $cat, $desc]) {
        $pc_stmt->execute([$uid, 'doha', $amt, $cat, $desc, $statuses[$i] ?? 'unpaid', date('Y-m-d H:i:s', strtotime("-".($i*3)." days"))]);
        $cnt++;
    }
    $statuses2 = ['paid','paid','unpaid','paid','paid','unpaid','paid','unpaid'];
    foreach ($pc_beirut as $i => [$amt, $cat, $desc]) {
        $pc_stmt->execute([$uid, 'beirut', $amt, $cat, $desc, $statuses2[$i] ?? 'unpaid', date('Y-m-d H:i:s', strtotime("-".($i*4)." days"))]);
        $cnt++;
    }

    $msg = "Seeded $cnt mockup records successfully — form submissions, and petty cash requests for Doha & Beirut.";
}

// ── Action: Purge all data ────────────────────────────────────────────────────
if ($_POST['action'] ?? '' === 'purge') {
    $scope = $_POST['scope'] ?? 'all';
    $db    = db();
    $tables = [];

    if (in_array($scope, ['all','forms'])) {
        $tables[] = 'form_submissions';
    }
    if (in_array($scope, ['all','petty'])) {
        $tables[] = 'petty_cash_requests';
    }
    if ($scope === 'all') {
        // Also wipe asset data but keep lookup tables (categories/locations/depts)
        foreach (['asset_activity_log','asset_assignments','asset_disposals','asset_maintenance','asset_transfers','asset_import_log','assets'] as $t) {
            $tables[] = $t;
        }
    }

    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $t) {
        try { $db->exec("TRUNCATE TABLE `$t`"); } catch (\Exception $e) { /* table may not exist */ }
    }
    $db->exec('SET FOREIGN_KEY_CHECKS=1');

    // Also delete generated PDF files from storage
    if (in_array($scope, ['all','forms'])) {
        foreach (glob(STORAGE_PATH . '*.pdf') as $f) @unlink($f);
    }

    $msg   = 'Purged: ' . implode(', ', $tables) . '. PDF storage cleared.';
    $mtype = 'warn';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dev Tools — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
.dt-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px;max-width:1100px}
.dt-card{background:#fff;border:1.5px solid #e8eaee;border-radius:14px;padding:28px 28px 24px;display:flex;flex-direction:column;gap:14px}
.dt-card h2{font-size:16px;font-weight:800;color:#1a1a1a;margin:0}
.dt-card p{font-size:13px;color:#777;margin:0;line-height:1.6}
.dt-badge{display:inline-block;font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 10px;border-radius:20px}
.badge-blue{background:#eff6ff;color:#1d4ed8}
.badge-green{background:#f0fdf4;color:#15803d}
.badge-red{background:#fff5f5;color:#b91c1c}
.dt-btn{display:inline-block;padding:10px 20px;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;width:100%}
.btn-blue{background:#1d4ed8;color:#fff}
.btn-blue:hover{background:#1e40af}
.btn-green{background:#15803d;color:#fff}
.btn-green:hover{background:#166534}
.btn-red{background:#b91c1c;color:#fff}
.btn-red:hover{background:#991b1b}
.dt-radio{display:flex;gap:12px;flex-wrap:wrap}
.dt-radio label{display:flex;align-items:center;gap:6px;font-size:13px;color:#555;cursor:pointer}
.flash{padding:13px 18px;border-radius:9px;font-size:13px;margin-bottom:24px;max-width:760px}
.flash.ok{background:#f0fdf4;border:1px solid #86efac;color:#15803d}
.flash.warn{background:#fffbeb;border:1px solid #fcd34d;color:#92400e}
.scope-info{font-size:11px;color:#aaa;margin-top:4px}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <span class="topbar-title">Dev Tools</span>
  <span style="font-size:12px;color:#aaa;margin-left:12px">Superadmin only</span>
</div>
<div style="padding:28px 32px">

  <?php if ($msg): ?>
  <div class="flash <?= $mtype ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="dt-grid">

    <!-- Generate sample PDFs -->
    <div class="dt-card">
      <div><span class="dt-badge badge-blue">PDF Generator</span></div>
      <h2>Generate All Sample Forms</h2>
      <p>Generates one real PDF for each form type (AMEX, Accountability, Debit Note, Credit Note, Vendor Recon) using realistic mockup data, then downloads them as a ZIP.</p>
      <form method="POST" onsubmit="this.querySelector('button').textContent='Generating…';this.querySelector('button').disabled=true">
        <input type="hidden" name="action" value="gen_pdfs">
        <button class="dt-btn btn-blue" type="submit">⬇ Generate &amp; Download ZIP</button>
      </form>
    </div>

    <!-- Seed mockup data -->
    <div class="dt-card">
      <div><span class="dt-badge badge-green">Data Seeder</span></div>
      <h2>Generate Mockup Data</h2>
      <p>Inserts realistic sample records into the database: 5 AMEX, 3 Accountability, 2 Debit Note, 1 Credit Note, 1 Vendor Recon, 10 Doha + 8 Beirut petty cash requests.</p>
      <form method="POST" onsubmit="return confirm('Seed mockup data into the database?')">
        <input type="hidden" name="action" value="seed">
        <button class="dt-btn btn-green" type="submit">🌱 Seed Mockup Data</button>
      </form>
    </div>

    <!-- Purge all data -->
    <div class="dt-card">
      <div><span class="dt-badge badge-red">Data Purge</span></div>
      <h2>Delete All Data</h2>
      <p>Truncates selected tables and deletes generated PDF files. <strong>This cannot be undone.</strong></p>
      <form method="POST" onsubmit="return confirm('⚠️ This will permanently delete data. Are you sure?')">
        <input type="hidden" name="action" value="purge">
        <div class="dt-radio" style="margin-bottom:10px">
          <label><input type="radio" name="scope" value="all" checked> Everything</label>
          <label><input type="radio" name="scope" value="forms"> Forms only</label>
          <label><input type="radio" name="scope" value="petty"> Petty Cash only</label>
        </div>
        <div class="scope-info">Assets lookup tables (categories/locations/departments) are never deleted.</div>
        <button class="dt-btn btn-red" type="submit" style="margin-top:12px">🗑 Delete Data</button>
      </form>
    </div>

  </div>
</div>
</div>
</body>
</html>
