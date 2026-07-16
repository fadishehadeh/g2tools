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
if (($_POST['action'] ?? '') === 'gen_pdfs') {
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
if (($_POST['action'] ?? '') === 'seed') {
    $uid    = current_user()['id'];
    $db     = db();
    $errors = [];
    $cnt    = 0;

    // Helper: safe execute — catches and records errors instead of crashing
    $safe = function(string $label, callable $fn) use (&$errors, &$cnt) {
        try { $result = $fn(); $cnt++; return $result; }
        catch (\Throwable $e) { $errors[] = "$label: " . $e->getMessage(); return false; }
    };

    // ── 1. form_submissions ──
    // Detect column names to handle schema differences
    $fs_cols = array_column($db->query("DESCRIBE form_submissions")->fetchAll(), 'Field');
    $has_created_at = in_array('created_at', $fs_cols);
    $fs_sql = $has_created_at
        ? "INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename,created_at) VALUES (?,?,?,NULL,?)"
        : "INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename) VALUES (?,?,?,NULL)";

    $fs_data = [
        ['amex', json_encode(['company'=>'G2 Group','merchant'=>'Adobe Creative Cloud','serial_number'=>'G2-00001','po_number'=>'PO-2026-0001','billable'=>'YES','client_name'=>'Ooredoo','currency'=>'USD','amount'=>'599.99','authorized_name'=>'Fadi Shehadeh','finance_approval'=>'Sara Al-Khatib','finance_date'=>'01/06/2026','mgmt_approval'=>'Krikor Khajikian','mgmt_date'=>'01/06/2026']), 0],
        ['amex', json_encode(['company'=>'G2 Group','merchant'=>'AWS (Amazon Web Services)','serial_number'=>'G2-00002','po_number'=>'PO-2026-0002','billable'=>'NO','nature_of_expense'=>'Cloud hosting','currency'=>'USD','amount'=>'1240.00','authorized_name'=>'Ahmad Nasser','finance_approval'=>'Sara Al-Khatib','finance_date'=>'05/06/2026','mgmt_approval'=>'Krikor Khajikian','mgmt_date'=>'05/06/2026']), 5],
        ['amex', json_encode(['company'=>'Pin & Notch','merchant'=>'Canva Pro','serial_number'=>'PN-00001','po_number'=>'PO-2026-0003','billable'=>'NO','nature_of_expense'=>'Design subscriptions','currency'=>'USD','amount'=>'149.99','authorized_name'=>'Lara Hassan','finance_approval'=>'Sara Al-Khatib','finance_date'=>'10/06/2026','mgmt_approval'=>'Krikor Khajikian','mgmt_date'=>'10/06/2026']), 10],
        ['amex', json_encode(['company'=>'Grey','merchant'=>'Marriott Doha','serial_number'=>'Grey-00001','po_number'=>'PO-2026-0004','billable'=>'YES','client_name'=>'QNB Group','currency'=>'QAR','amount'=>'8500.00','authorized_name'=>'Karim Mansour','finance_approval'=>'Sara Al-Khatib','finance_date'=>'15/06/2026','mgmt_approval'=>'Krikor Khajikian','mgmt_date'=>'15/06/2026']), 15],
        ['amex', json_encode(['company'=>'G2 Group','merchant'=>'Microsoft 365','serial_number'=>'G2-00003','po_number'=>'PO-2026-0005','billable'=>'NO','nature_of_expense'=>'Annual M365 subscription','currency'=>'USD','amount'=>'2400.00','authorized_name'=>'Fadi Shehadeh','finance_approval'=>'Sara Al-Khatib','finance_date'=>'20/06/2026','mgmt_approval'=>'Krikor Khajikian','mgmt_date'=>'20/06/2026']), 20],
        ['accountability', json_encode(['company'=>'G2 Group','received_by'=>'Ahmad Nasser','position'=>'Senior Designer','department'=>'Creative','item_name'=>'MacBook Pro 14 M4','serial_number'=>'FVFXQ2ABCD','received_date'=>'01/06/2026','estimated_life'=>'3 Years','request_by'=>'Fadi Shehadeh']), 7],
        ['accountability', json_encode(['company'=>'G2 Group','received_by'=>'Lara Hassan','position'=>'Account Manager','department'=>'Client Services','item_name'=>'iPhone 16 Pro','serial_number'=>'F2LN8WXYZ','received_date'=>'10/06/2026','estimated_life'=>'2 Years','request_by'=>'Fadi Shehadeh']), 14],
        ['accountability', json_encode(['company'=>'Grey','received_by'=>'Karim Mansour','position'=>'Creative Director','department'=>'Creative','item_name'=>'Sony A7 IV Camera','serial_number'=>'5041234','received_date'=>'15/06/2026','estimated_life'=>'4 Years','request_by'=>'Krikor Khajikian']), 21],
        ['debit_note', json_encode(['company'=>'G2 Group','to_name'=>'Al Mana Fashion Group','attention'=>'Finance','dn_date'=>'01/06/2026','currency'=>'QAR','total'=>'23500','prepared_by'=>'Cresencia Candava','approved_by'=>'Krikor Khajikian']), 10],
        ['debit_note', json_encode(['company'=>'Grey','to_name'=>'Vodafone Qatar','attention'=>'Marketing','dn_date'=>'15/06/2026','currency'=>'QAR','total'=>'35000','prepared_by'=>'Cresencia Candava','approved_by'=>'Krikor Khajikian']), 20],
        ['credit_note', json_encode(['company'=>'G2 Group','to_name'=>'Ooredoo Qatar','attention'=>'Accounts Payable','dn_date'=>'20/06/2026','currency'=>'QAR','total'=>'5000','prepared_by'=>'Cresencia Candava','approved_by'=>'Krikor Khajikian']), 3],
        ['vendor_recon', json_encode(['company'=>'G2 Group','vendor_name'=>'PrintZone LLC','vendor_no'=>'V-00418','recon_date'=>'30/06/2026','currency'=>'QAR','grey_soa_balance'=>47250,'vendor_soa_balance'=>46900,'variance'=>350,'prepared_by'=>'Cresencia Candava','reviewed_by'=>'Fadi Shehadeh','approved_by'=>'Krikor Khajikian']), 1],
    ];

    $fs_stmt = $db->prepare($fs_sql);
    foreach ($fs_data as [$ft, $fd, $days_ago]) {
        $safe("form_submissions[$ft]", function() use ($fs_stmt, $uid, $ft, $fd, $days_ago, $has_created_at) {
            if ($has_created_at)
                $fs_stmt->execute([$uid, $ft, $fd, date('Y-m-d H:i:s', strtotime("-{$days_ago} days"))]);
            else
                $fs_stmt->execute([$uid, $ft, $fd]);
        });
    }

    // ── 2. petty_cash_requests ──
    $pc_cols = array_column($db->query("DESCRIBE petty_cash_requests")->fetchAll(), 'Field');
    $pc_has_created = in_array('created_at', $pc_cols);
    $pc_has_ocr     = in_array('ocr_amount', $pc_cols);

    $pc_col_list = 'user_id,office,amount,category,description,status' . ($pc_has_ocr ? ',ocr_amount' : '') . ($pc_has_created ? ',created_at' : '');
    $pc_placeholders = '?,?,?,?,?,?' . ($pc_has_ocr ? ',NULL' : '') . ($pc_has_created ? ',?' : '');
    $pc_stmt = $db->prepare("INSERT INTO petty_cash_requests ($pc_col_list) VALUES ($pc_placeholders)");

    $pc_rows = [
        ['doha', 45.00,  'Transport',              'Uber to client meeting — Lusail',             'paid',   0],
        ['doha', 320.00, 'Meals & Entertainment',  'Team lunch — 8 pax',                          'paid',   3],
        ['doha', 85.50,  'Office Supplies',        'Printer ink cartridges x4',                   'unpaid', 6],
        ['doha', 150.00, 'Transport',              'Airport taxi for client pickup',               'paid',   9],
        ['doha', 220.00, 'Meals & Entertainment',  'Dinner with client — Qatar National Bank',    'unpaid', 12],
        ['doha', 45.00,  'Office Supplies',        'Whiteboard markers and notebooks',             'paid',   15],
        ['doha', 500.00, 'Utilities',              'Parking permit — monthly renewal',             'unpaid', 18],
        ['doha', 75.00,  'Maintenance',            'Coffee machine filter replacement',            'paid',   21],
        ['doha', 180.00, 'Transport',              'Careem rides — weekly (3 trips)',              'unpaid', 24],
        ['doha', 95.00,  'Meals & Entertainment',  'Catering for internal strategy session',       'paid',   27],
        ['beirut', 35.00,  'Transport',            'Taxi to DHL for courier pickup',               'paid',   0],
        ['beirut', 85.00,  'Meals & Entertainment','Working lunch — new client onboarding',        'paid',   4],
        ['beirut', 42.50,  'Office Supplies',      'A4 paper ream x5',                            'unpaid', 8],
        ['beirut', 60.00,  'Transport',            'Airport transfer — visiting GM',               'paid',   12],
        ['beirut', 120.00, 'Communication',        'Mobile top-up reimbursement — 4 staff',        'paid',   16],
        ['beirut', 25.00,  'Other',                'Bank charges — wire transfer fee',             'unpaid', 20],
        ['beirut', 55.00,  'Meals & Entertainment','Coffee meeting with supplier',                 'paid',   24],
        ['beirut', 38.00,  'Office Supplies',      'Stapler, scissors, tape',                      'unpaid', 28],
    ];

    foreach ($pc_rows as [$office, $amt, $cat, $desc, $status, $days_ago]) {
        $safe("petty_cash[$office]", function() use ($pc_stmt, $uid, $office, $amt, $cat, $desc, $status, $days_ago, $pc_has_created) {
            $params = [$uid, $office, $amt, $cat, $desc, $status];
            if ($pc_has_created) $params[] = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
            $pc_stmt->execute($params);
        });
    }

    // ── 3. Assets ──
    try {
        $db->exec('SET FOREIGN_KEY_CHECKS=0');

        $cats_map = ['IT Equipment'=>'💻','Printers & Peripherals'=>'🖨','Mobile Devices'=>'📱','Furniture'=>'🪑','Vehicles'=>'🚗','AV Equipment'=>'📷'];
        $cat_ids = [];
        foreach ($cats_map as $name => $icon) {
            $r = $db->prepare("SELECT id FROM asset_categories WHERE name=?"); $r->execute([$name]);
            $id = $r->fetchColumn();
            if (!$id) { $db->prepare("INSERT INTO asset_categories (name,icon) VALUES (?,?)")->execute([$name,$icon]); $id = $db->lastInsertId(); }
            $cat_ids[$name] = $id;
        }

        $locs_map = [['Doha HQ — Floor 3','doha'],['Doha HQ — Floor 4','doha'],['Beirut Office','beirut'],['Storage Room','doha']];
        $loc_ids = [];
        foreach ($locs_map as [$name, $office]) {
            $r = $db->prepare("SELECT id FROM asset_locations WHERE name=?"); $r->execute([$name]);
            $id = $r->fetchColumn();
            if (!$id) { $db->prepare("INSERT INTO asset_locations (name,office) VALUES (?,?)")->execute([$name,$office]); $id = $db->lastInsertId(); }
            $loc_ids[$name] = $id;
        }

        $depts_map = ['Creative','Technology','Finance','Client Services','Management'];
        $dept_ids = [];
        foreach ($depts_map as $name) {
            $r = $db->prepare("SELECT id FROM asset_departments WHERE name=?"); $r->execute([$name]);
            $id = $r->fetchColumn();
            if (!$id) { $db->prepare("INSERT INTO asset_departments (name) VALUES (?)")->execute([$name]); $id = $db->lastInsertId(); }
            $dept_ids[$name] = $id;
        }

        $db->exec('SET FOREIGN_KEY_CHECKS=1');

        // Detect assets columns
        $ast_cols = array_column($db->query("DESCRIBE assets")->fetchAll(), 'Field');
        $has_dep  = in_array('depreciation_method', $ast_cols);
        $has_life = in_array('useful_life_years', $ast_cols);
        $has_salv = in_array('salvage_value', $ast_cols);
        $has_crby = in_array('created_by', $ast_cols);

        $col_list = 'tag,name,category_id,location_id,department_id,serial_number,brand,model,purchase_date,purchase_value,warranty_expiry,status,notes';
        $ph_list  = '?,?,?,?,?,?,?,?,?,?,?,?,?';
        if ($has_dep)  { $col_list .= ',depreciation_method'; $ph_list .= ",'straight_line'"; }
        if ($has_life) { $col_list .= ',useful_life_years';   $ph_list .= ',3'; }
        if ($has_salv) { $col_list .= ',salvage_value';       $ph_list .= ',500'; }
        if ($has_crby) { $col_list .= ',created_by';         $ph_list .= ',?'; }

        $ast_stmt = $db->prepare("INSERT IGNORE INTO assets ($col_list) VALUES ($ph_list)");

        $asset_rows = [
            ['G2-IT-001','MacBook Pro 14 M4','IT Equipment','Doha HQ — Floor 3','Creative','FVFXQ2ABCD','Apple','MacBook Pro 14','2025-01-15',12500.00,'2027-01-14','active','Assigned to Ahmad Nasser'],
            ['G2-IT-002','MacBook Pro 14 M4','IT Equipment','Doha HQ — Floor 4','Technology','FVFXQ2ABCE','Apple','MacBook Pro 14','2025-01-15',12500.00,'2027-01-14','active','Assigned to Fadi Shehadeh'],
            ['G2-IT-003','Dell XPS 15','IT Equipment','Doha HQ — Floor 3','Finance','SN-XPS15-009','Dell','XPS 15 9530','2024-06-01',9800.00,'2026-05-31','active','Finance workstation'],
            ['G2-IT-004','HP LaserJet Pro','Printers & Peripherals','Doha HQ — Floor 3','Creative','CNBKG12345','HP','LaserJet Pro M404dn','2023-03-10',2200.00,'2025-03-09','active','Main print room'],
            ['G2-MOB-001','iPhone 16 Pro','Mobile Devices','Doha HQ — Floor 4','Client Services','F2LN8WXYZ1','Apple','iPhone 16 Pro 256GB','2024-10-01',5500.00,'2026-09-30','active','Assigned to Lara Hassan'],
            ['G2-MOB-002','iPhone 15','Mobile Devices','Beirut Office','Client Services','F2LN8WXYB2','Apple','iPhone 15 128GB','2023-11-01',4200.00,'2025-10-31','active','Beirut team device'],
            ['G2-MOB-003','Samsung Galaxy S24','Mobile Devices','Doha HQ — Floor 3','Management','RF8N30ABCD','Samsung','Galaxy S24 Ultra','2024-02-15',4800.00,'2026-02-14','active',''],
            ['G2-AV-001','Sony A7 IV Camera','AV Equipment','Doha HQ — Floor 3','Creative','5041234ABC','Sony','A7 IV','2024-05-01',18000.00,'2026-04-30','active','Studio production camera'],
            ['G2-AV-002','DJI Mavic 3 Pro','AV Equipment','Storage Room','Creative','DJI2024PRO1','DJI','Mavic 3 Pro','2024-07-15',12000.00,'2026-07-14','active','Aerial photography'],
            ['G2-FUR-001','Executive Desk Set','Furniture','Doha HQ — Floor 4','Management','','Jasper','Executive L-Desk','2022-01-01',8500.00,null,'active','MD Office'],
            ['G2-IT-005','iPad Pro 12.9','IT Equipment','Beirut Office','Management','DMPL2ABCDE','Apple','iPad Pro M4','2024-09-01',6200.00,'2026-08-31','active',''],
            ['G2-IT-006','Dell Monitor 27','IT Equipment','Doha HQ — Floor 3','Technology','CN0A1B2C3D','Dell','UltraSharp U2723DE','2023-08-01',2800.00,'2025-07-31','active','Dual monitor setup'],
            ['G2-IT-007','MacBook Air M3','IT Equipment','Beirut Office','Creative','FVFXQ2ZZZ1','Apple','MacBook Air 15 M3','2024-03-01',9200.00,'2026-02-28','active',''],
            ['G2-VEH-001','Toyota Camry 2024','Vehicles','Doha HQ — Floor 3','Management','','Toyota','Camry XLE 2024','2024-01-10',145000.00,'2026-01-09','active','Company pool car'],
            ['G2-IT-008','Logitech MX Keys','IT Equipment','Storage Room','Technology','LGT2024001','Logitech','MX Keys for Mac','2024-11-01',650.00,'2025-10-31','in_storage','Spare peripherals'],
        ];

        foreach ($asset_rows as [$tag,$aname,$cat,$loc,$dept,$sn,$brand,$model,$pd,$val,$warr,$stat,$notes]) {
            $safe("asset[$tag]", function() use ($ast_stmt, $cat_ids, $loc_ids, $dept_ids, $uid, $has_crby, $tag,$aname,$cat,$loc,$dept,$sn,$brand,$model,$pd,$val,$warr,$stat,$notes) {
                $params = [$tag,$aname,$cat_ids[$cat]??null,$loc_ids[$loc]??null,$dept_ids[$dept]??null,$sn,$brand,$model,$pd,$val,$warr,$stat,$notes];
                if ($has_crby) $params[] = $uid;
                $ast_stmt->execute($params);
            });
        }
    } catch (\Throwable $e) {
        $errors[] = 'assets setup: ' . $e->getMessage();
    }

    // ── 4. Vendor registrations ──
    $vendor_rows = [
        [json_encode([
            'legal_name'=>'PrintZone LLC','aka_name'=>'PrintZone','company_type'=>'LLC',
            'company_license'=>'CR-2019-04182','tax_no'=>'300412834700003','co_reg_no'=>'','icv_score'=>'72',
            'annual_spend'=>'QAR 50,000 – 100,000','related_party'=>'No','related_party_desc'=>'',
            'source'=>['Referral'],'client_ref_name'=>'Ahmad Nasser',
            'zip_code'=>'','city'=>'Doha','state'=>'','country'=>'Qatar',
            'bank_account_name'=>'PrintZone LLC','bank_company_address'=>'Al Sadd, Doha, Qatar',
            'account_number'=>'0142857300001','bank_currency'=>'QAR',
            'bank_name'=>'Qatar National Bank','bank_address'=>'QNB Tower, West Bay, Doha',
            'swift_code'=>'QNBAQAQA','iban'=>'QA58QNBA000000000142857300001',
            'rep_name'=>'Mohammed Al-Hajri','rep_designation'=>'Managing Director','rep_date'=>'01/06/2026',
            'uploaded_files'=>[],
        ]), 20],
        [json_encode([
            'legal_name'=>'TechBridge Solutions W.L.L.','aka_name'=>'TechBridge','company_type'=>'WLL',
            'company_license'=>'CR-2021-09034','tax_no'=>'300891234500003','co_reg_no'=>'','icv_score'=>'85',
            'annual_spend'=>'QAR 100,000 – 250,000','related_party'=>'No','related_party_desc'=>'',
            'source'=>['Google Search'],'client_ref_name'=>'',
            'zip_code'=>'','city'=>'Doha','state'=>'','country'=>'Qatar',
            'bank_account_name'=>'TechBridge Solutions WLL','bank_company_address'=>'C-Ring Road, Doha',
            'account_number'=>'0098712300004','bank_currency'=>'USD',
            'bank_name'=>'HSBC Bank Middle East','bank_address'=>'Al Corniche, Doha',
            'swift_code'=>'BBMEQAQX','iban'=>'QA30BBME000000000098712300004',
            'rep_name'=>'Sara Al-Rashidi','rep_designation'=>'CEO','rep_date'=>'10/06/2026',
            'uploaded_files'=>[],
        ]), 10],
        [json_encode([
            'legal_name'=>'Creative Pixel Studio S.A.R.L.','aka_name'=>'CPS','company_type'=>'SARL',
            'company_license'=>'LB-2020-11203','tax_no'=>'','co_reg_no'=>'1120300','icv_score'=>'',
            'annual_spend'=>'USD 25,000 – 50,000','related_party'=>'No','related_party_desc'=>'',
            'source'=>['LinkedIn'],'client_ref_name'=>'',
            'zip_code'=>'1107','city'=>'Beirut','state'=>'Beirut Governorate','country'=>'Lebanon',
            'bank_account_name'=>'Creative Pixel Studio SARL','bank_company_address'=>'Hamra, Beirut, Lebanon',
            'account_number'=>'LB62004900000000000123456789','bank_currency'=>'USD',
            'bank_name'=>'Bankmed SAL','bank_address'=>'Minet El Hosn, Beirut',
            'swift_code'=>'BMEDLBBX','iban'=>'LB62004900000000000123456789',
            'rep_name'=>'Nour Khalil','rep_designation'=>'Co-Founder','rep_date'=>'05/07/2026',
            'uploaded_files'=>[],
        ]), 5],
    ];

    foreach ($vendor_rows as [$fd, $days_ago]) {
        $safe("vendor_reg", function() use ($db, $uid, $fd, $days_ago, $has_created_at) {
            $fs_cols = array_column($db->query("DESCRIBE form_submissions")->fetchAll(), 'Field');
            $has_ca  = in_array('created_at', $fs_cols);
            if ($has_ca)
                $db->prepare("INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename,created_at) VALUES (NULL,'vendor_reg',?,NULL,?)")
                   ->execute([$fd, date('Y-m-d H:i:s', strtotime("-{$days_ago} days"))]);
            else
                $db->prepare("INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename) VALUES (NULL,'vendor_reg',?,NULL)")
                   ->execute([$fd]);
        });
    }

    if ($errors) {
        $msg   = "Seeded $cnt records. Errors (" . count($errors) . "): " . implode(' | ', array_slice($errors, 0, 5));
        $mtype = 'warn';
    } else {
        $msg = "Seeded $cnt records — form submissions, petty cash (Doha & Beirut), and 15 assets with lookups.";
    }
}

// ── Action: Purge all data ────────────────────────────────────────────────────
if (($_POST['action'] ?? '') === 'purge') {
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

    // Delete generated PDF files but keep the directory
    if (in_array($scope, ['all','forms'])) {
        foreach (glob(STORAGE_PATH . '*.pdf') as $f) @unlink($f);
        if (!is_dir(STORAGE_PATH)) mkdir(STORAGE_PATH, 0755, true);
    }
    // Keep petty-cash upload dir intact
    $pc_dir = dirname(STORAGE_PATH) . '/petty-cash/';
    if (!is_dir($pc_dir)) mkdir($pc_dir, 0755, true);

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
