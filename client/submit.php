<?php
// PUBLIC — no login required
if (session_status() === PHP_SESSION_NONE) session_start();
require '../config.php';
require '../mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /client/'); exit;
}

function s($k) { return isset($_POST[$k]) ? trim(strip_tags($_POST[$k])) : ''; }

$data = [
    'company_name'         => s('company_name'),
    'company_address'      => s('company_address'),
    'billing_address'      => s('billing_address'),
    'website'              => s('website'),
    'industry'             => s('industry'),
    'year_trading'         => s('year_trading'),
    'vat_number'           => s('vat_number'),
    'trade_license_no'     => s('trade_license_no'),
    'brand_product'        => s('brand_product'),
    'ceo_name'             => s('ceo_name'),
    'cfo_name'             => s('cfo_name'),
    // Client Servicing contact
    'cs_name'              => s('cs_name'),
    'cs_position'          => s('cs_position'),
    'cs_email'             => s('cs_email'),
    'cs_phone'             => s('cs_phone'),
    // Finance contact
    'fin_name'             => s('fin_name'),
    'fin_position'         => s('fin_position'),
    'fin_email'            => s('fin_email'),
    'fin_phone'            => s('fin_phone'),
    // Financial & credit
    'revenue'              => s('revenue'),
    'net_profit'           => s('net_profit'),
    'audited_financials'   => s('audited_financials'),
    'credit_check_results' => s('credit_check_results'),
    'credit_limit'         => s('credit_limit'),
    'credit_period_days'   => s('credit_period_days'),
    'related_party_checks' => s('related_party_checks'),
    // Rep
    'rep_name'             => s('rep_name'),
    'rep_designation'      => s('rep_designation'),
    'rep_date'             => s('rep_date'),
];

// ── File upload ───────────────────────────────────────────────────────────────
$upload_dir = __DIR__ . '/../storage/client-uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$ts         = date('Ymd_His');
$name_slug  = preg_replace('/[^a-z0-9]+/', '_', strtolower($data['company_name']));
$trade_file = null;

if (isset($_FILES['trade_license_file']) && $_FILES['trade_license_file']['error'] === UPLOAD_ERR_OK) {
    $f   = $_FILES['trade_license_file'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($f['size'] <= 5*1024*1024 && in_array($ext, ['pdf','jpg','jpeg','png'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);
        if (in_array($mime, ['application/pdf','image/jpeg','image/png'])) {
            $fname = $name_slug . '_trade_license_' . $ts . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], $upload_dir . $fname)) {
                $trade_file = $fname;
            }
        }
    }
}
$data['trade_license_file'] = $trade_file;

// ── Generate PDF ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/../amex/lib/fpdf.php';

class PDF_ClientReg extends FPDF {
    function Header(){}
    function Footer(){
        $this->SetY(-13); $this->SetFont('Arial','I',8);
        $this->SetTextColor(180,180,180);
        $this->Cell(0,6,'G2 Group  —  Client Credit Check & Registration  —  Confidential',0,0,'C');
    }
}

$pdf  = new PDF_ClientReg('P','mm','A4');
$pdf->AddPage(); $pdf->SetMargins(22,22,22); $pdf->SetAutoPageBreak(true,20);
$lm  = 22; $pw = 166;

$logo = __DIR__.'/../logo.png';
if (file_exists($logo)) $pdf->Image($logo,$lm,14,30,15,'PNG');

$pdf->SetDrawColor(255,61,51); $pdf->SetLineWidth(0.7);
$pdf->Line($lm,34,$lm+$pw,34);
$pdf->SetLineWidth(0.2); $pdf->SetDrawColor(180,180,180);

$pdf->SetFont('Arial','B',15); $pdf->SetTextColor(30,30,30);
$pdf->SetXY($lm,38); $pdf->Cell(0,9,'NEW CLIENT SET-UP FORM',0,1,'C');

// Placeholder for sub_id (will be set after DB insert)
$sub_id_placeholder = 'CR-' . str_pad(1, 5, '0', STR_PAD_LEFT);

$pdf->SetFont('Arial','I',9); $pdf->SetTextColor(150,150,150);
$pdf->SetX($lm); $pdf->Cell(0,6,'Date: '.date('d M Y'),0,1,'C');
$pdf->Ln(4);

$section = function(string $title) use ($pdf,$lm,$pw) {
    $pdf->SetFont('Arial','B',9); $pdf->SetTextColor(255,61,51);
    $pdf->SetFillColor(255,248,248);
    $pdf->SetX($lm); $pdf->Cell($pw,7,'  '.$title,0,1,'L',true);
    $pdf->SetDrawColor(255,180,180); $pdf->SetLineWidth(0.3);
    $pdf->Line($lm,$pdf->GetY(),$lm+$pw,$pdf->GetY());
    $pdf->SetLineWidth(0.2); $pdf->SetDrawColor(200,200,200);
    $pdf->Ln(3);
};
$row = function(string $label, string $value) use ($pdf,$lm,$pw) {
    if (trim($value)==='') return;
    $pdf->SetFont('Arial','',9); $pdf->SetTextColor(100,100,100);
    $pdf->SetX($lm); $pdf->Cell(58,6,$label,0,0);
    $pdf->SetFont('Arial','',9); $pdf->SetTextColor(20,20,20);
    $pdf->MultiCell($pw-58,6,iconv('UTF-8','windows-1252//TRANSLIT',$value),0,'L');
    $pdf->Ln(1);
};

$section('CLIENT INFORMATION');
$row('Company Name',      $data['company_name']);
$row('Company Address',   $data['company_address']);
if ($data['billing_address']) $row('Billing Address', $data['billing_address']);
$row('Website',           $data['website']);
$row('Industry',          $data['industry']);
$row('Year of Trading',   $data['year_trading']);
$row('Trade License No.', $data['trade_license_no']);
$row('VAT Number',        $data['vat_number']);
$row('Brand / Product',   $data['brand_product']);
$row('CEO',               $data['ceo_name']);
$row('CFO',               $data['cfo_name']);
$pdf->Ln(3);

$section('CLIENT SERVICING CONTACT');
$row('Name',          $data['cs_name']);
$row('Position',      $data['cs_position']);
$row('Email',         $data['cs_email']);
$row('Phone',         $data['cs_phone']);
$pdf->Ln(3);

$section('FINANCE DEPARTMENT CONTACT');
$row('Name',     $data['fin_name']);
$row('Position', $data['fin_position']);
$row('Email',    $data['fin_email']);
$row('Phone',    $data['fin_phone']);
$pdf->Ln(3);

$section('FINANCIAL RESULTS & CREDIT CHECK');
$row('Revenue (Last FY)',          $data['revenue'] . ' QAR');
$row('Net Profit Before Tax',      $data['net_profit'] . ' QAR');
$row('Audited Financials',         $data['audited_financials']);
$row('Credit Check Results',       $data['credit_check_results']);
$row('Requested Credit Limit',     $data['credit_limit'] . ' QAR');
$row('Credit Period',              $data['credit_period_days'] . ' days');
$row('Related-Party Checks',       $data['related_party_checks']);
$pdf->Ln(3);

$section('ATTACHMENTS');
$row('Trade License / Reg. Cert.', $trade_file ? 'Uploaded' : 'Not provided');
$pdf->Ln(6);

// Signature block
$pdf->SetFont('Arial','',9); $pdf->SetTextColor(70,70,70);
$pdf->SetX($lm);
$pdf->MultiCell($pw,5.5,'I, the undersigned Authorized Representative, certify that all information provided is true, accurate, and complete to the best of my knowledge.',0,'L');
$pdf->Ln(10);
$half = ($pw/2) - 10;
$pdf->SetDrawColor(80,80,80); $pdf->SetLineWidth(0.4);
$pdf->Line($lm,$pdf->GetY(),$lm+$half,$pdf->GetY());
$pdf->SetFont('Arial','',8); $pdf->SetTextColor(130,130,130);
$pdf->Ln(3);
$pdf->SetX($lm); $pdf->Cell($half,5,$data['rep_name'] ?: 'Authorized Representative',0,0,'C');
$pdf->Ln(5);
$pdf->SetX($lm); $pdf->Cell($half,5,'Signature & Date: '.($data['rep_date'] ? date('d M Y', strtotime($data['rep_date'])) : date('d M Y')),0,1,'C');

// Finance Manager approval block
$pdf->Ln(8);
$pdf->SetFont('Arial','B',8); $pdf->SetTextColor(50,50,50);
$pdf->SetX($lm); $pdf->Cell($pw,5,'FOR FINANCE USE ONLY',0,1,'C');
$pdf->SetLineWidth(0.3); $pdf->SetDrawColor(200,200,200);
$pdf->SetFillColor(248,248,248);
$pdf->SetX($lm); $pdf->Cell($pw,28,'',1,1,'L',true);
$pdf->SetY($pdf->GetY()-26);
$pdf->SetFont('Arial','',8); $pdf->SetTextColor(130,130,130);
$pdf->SetX($lm+4); $pdf->Cell(0,5,'Finance Manager Approval:',0,1);
$pdf->SetX($lm+4); $pdf->Cell(0,5,'[ ] Approved    [ ] Rejected',0,1);
$pdf->Ln(4);
$pdf->SetX($lm+4); $pdf->Cell(60,5,'Signature: _________________________',0,0);
$pdf->Cell(0,5,'Date: ______________',0,1);

// Save PDF
$pdf_filename = 'client_reg_' . $name_slug . '_' . $ts . '.pdf';
$pdf_path     = STORAGE_PATH . $pdf_filename;
if (!is_dir(STORAGE_PATH)) mkdir(STORAGE_PATH, 0755, true);
$pdf->Output('F', $pdf_path);

// ── Save to DB ────────────────────────────────────────────────────────────────
$db_cols = array_column(db()->query("DESCRIBE form_submissions")->fetchAll(), 'Field');
if (in_array('created_at', $db_cols)) {
    $stmt = db()->prepare("INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename,created_at) VALUES (NULL,'client_reg',?,?,NOW())");
} else {
    $stmt = db()->prepare("INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename) VALUES (NULL,'client_reg',?,?)");
}
$stmt->execute([json_encode($data), $pdf_filename]);
$sub_id = db()->lastInsertId();
$ref    = 'CR-' . str_pad($sub_id, 5, '0', STR_PAD_LEFT);

// ── Email to finance ──────────────────────────────────────────────────────────
$recipients = get_finance_emails();
if (!empty($recipients) && file_exists($pdf_path)) {
    $subject = "New Client Registration — {$data['company_name']} [{$ref}]";

    $email_body = mail_template("New Client Registration — {$data['company_name']}", "
    <p>A new Client Credit Check &amp; Registration form has been submitted.</p>
    <div class='info-box'><strong>Reference</strong> {$ref}</div>
    <div class='info-box'><strong>Company</strong> " . htmlspecialchars($data['company_name']) . "</div>
    <div class='info-box'><strong>Industry</strong> " . htmlspecialchars($data['industry']) . "</div>
    <div class='info-box'><strong>Credit Limit Requested</strong> " . htmlspecialchars($data['credit_limit']) . " QAR</div>
    <div class='info-box'><strong>Credit Period</strong> " . htmlspecialchars($data['credit_period_days']) . " days</div>
    <div class='info-box'><strong>CEO</strong> " . htmlspecialchars($data['ceo_name']) . "</div>
    <div class='info-box'><strong>CS Contact</strong> " . htmlspecialchars($data['cs_name']) . " — " . htmlspecialchars($data['cs_email']) . "</div>
    <div class='info-box'><strong>Trade License</strong> " . ($trade_file ? 'Uploaded' : 'Not provided') . "</div>
    <p>The full form PDF is attached. Please review and approve or reject via G2 Tools.</p>
    <a class='btn' href='https://g2tools.greydoha.com/admin/submissions.php'>Review in G2 Tools</a>");

    $boundary = '----=_G2CR_' . uniqid();
    $headers  = "From: G2 Tools <noreply@g2group.com>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
    $msg  = "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $email_body . "\r\n\r\n";

    // Attach PDF
    $msg .= "--{$boundary}\r\nContent-Type: application/pdf; name=\"{$pdf_filename}\"\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$pdf_filename}\"\r\n\r\n";
    $msg .= chunk_split(base64_encode(file_get_contents($pdf_path))) . "\r\n";

    // Attach trade license if uploaded
    if ($trade_file && file_exists($upload_dir . $trade_file)) {
        $ext2  = strtolower(pathinfo($trade_file, PATHINFO_EXTENSION));
        $mime2 = $ext2 === 'pdf' ? 'application/pdf' : 'image/jpeg';
        $msg  .= "--{$boundary}\r\nContent-Type: {$mime2}; name=\"{$trade_file}\"\r\n";
        $msg  .= "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$trade_file}\"\r\n\r\n";
        $msg  .= chunk_split(base64_encode(file_get_contents($upload_dir . $trade_file))) . "\r\n";
    }
    $msg .= "--{$boundary}--";

    foreach ($recipients as $to) {
        mail($to, $subject, $msg, $headers);
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registration Submitted — Grey</title>
<link rel="stylesheet" href="/form.css">
<style>
  body { background:#f6f7f9; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:40px 16px; }
  .confirm-card { background:#fff; border-radius:16px; border:1.5px solid #e8eaee; box-shadow:0 4px 24px rgba(0,0,0,.07); padding:48px 48px 40px; max-width:500px; width:100%; text-align:center; }
  .check { width:64px; height:64px; background:#f0fdf4; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:28px; }
  h1 { font-size:22px; font-weight:800; color:#1a1a1a; margin-bottom:10px; }
  p { font-size:14px; color:#777; line-height:1.7; }
  .ref { display:inline-block; margin-top:16px; padding:8px 20px; background:#f4f5f7; border-radius:8px; font-size:13px; font-weight:700; color:#444; letter-spacing:1px; }
</style>
</head>
<body>
<div class="confirm-card">
  <div class="check">✓</div>
  <h1>Registration Submitted</h1>
  <p>Thank you. Your client registration has been received and is pending Finance review.</p>
  <div class="ref"><?= htmlspecialchars($ref) ?></div>
  <p style="margin-top:20px;font-size:13px">Please keep your reference number for follow-up.</p>
</div>
</body>
</html>
