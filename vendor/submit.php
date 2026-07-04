<?php
// PUBLIC — no login required
if (session_status() === PHP_SESSION_NONE) session_start();
require '../config.php';
require '../mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /g2forms/vendor/'); exit;
}

function s($k) { return isset($_POST[$k]) ? trim(strip_tags($_POST[$k])) : ''; }

// --- Collect form data ---
$data = [
    'legal_name'         => s('legal_name'),
    'aka_name'           => s('aka_name'),
    'zip_code'           => s('zip_code'),
    'city'               => s('city'),
    'state'              => s('state'),
    'country'            => s('country'),
    'company_license'    => s('company_license'),
    'tax_no'             => s('tax_no'),
    'co_reg_no'          => s('co_reg_no'),
    'icv_score'          => s('icv_score'),
    'annual_spend'       => s('annual_spend'),
    'related_party'      => s('related_party'),
    'related_party_desc' => s('related_party_desc'),
    'source'             => array_map('strip_tags', (array)($_POST['source'] ?? [])),
    'client_ref_name'    => s('client_ref_name'),
    'bank_account_name'  => s('bank_account_name'),
    'bank_company_address'=> s('bank_company_address'),
    'account_number'     => s('account_number'),
    'bank_currency'      => s('bank_currency'),
    'bank_name'          => s('bank_name'),
    'bank_address'       => s('bank_address'),
    'swift_code'         => s('swift_code'),
    'iban'               => s('iban'),
    'rep_name'           => s('rep_name'),
    'rep_date'           => s('rep_date'),
    'rep_designation'    => s('rep_designation'),
    'rep_email'          => s('rep_email'),
];

// --- Handle file uploads ---
$upload_dir = __DIR__ . '/../storage/vendor-uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$doc_fields = [
    'doc_company_reg'   => 'Company Registration',
    'doc_company_prof'  => 'Company Profile',
    'doc_auth_id'       => 'Valid ID of Authorized Signatory',
    'doc_bank_mandate'  => 'Bank Mandate',
    'doc_trade_license' => 'Company Trade License',
    'doc_icv'           => 'ICV Certificate',
];

$uploaded_files = [];
$allowed_ext = ['pdf','jpg','jpeg','png'];
$ts = date('Ymd_His');
$legal_slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($data['legal_name']));

foreach ($doc_fields as $field => $label) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        $uploaded_files[$field] = null;
        continue;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $uploaded_files[$field] = null;
        continue;
    }
    // Validate size (5MB)
    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
        $uploaded_files[$field] = null;
        continue;
    }
    // Validate extension
    $orig_name = $_FILES[$field]['name'];
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        $uploaded_files[$field] = null;
        continue;
    }
    // Validate MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES[$field]['tmp_name']);
    finfo_close($finfo);
    $allowed_mime = ['application/pdf','image/jpeg','image/png'];
    if (!in_array($mime, $allowed_mime, true)) {
        $uploaded_files[$field] = null;
        continue;
    }
    $safe_name = $legal_slug . '_' . $field . '_' . $ts . '.' . $ext;
    $dest = $upload_dir . $safe_name;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        $uploaded_files[$field] = $safe_name;
    } else {
        $uploaded_files[$field] = null;
    }
}
$data['uploaded_files'] = $uploaded_files;

// --- Generate PDF ---
require_once __DIR__ . '/../amex/lib/fpdf.php';

class PDF_VendorReg extends FPDF {
    function Header(){}
    function Footer(){
        $this->SetY(-13); $this->SetFont('Arial','I',8);
        $this->SetTextColor(180,180,180);
        $this->Cell(0,6,'G2 Group  —  Vendor Registration  —  Confidential',0,0,'C');
    }
}

$pdf = new PDF_VendorReg('P','mm','A4');
$pdf->AddPage(); $pdf->SetMargins(22,22,22); $pdf->SetAutoPageBreak(true,20);
$lm=22; $pageW=166;

// Logo
$logo = __DIR__.'/../logo.png';
if(file_exists($logo)) $pdf->Image($logo,$lm,14,30,15,'PNG');

// Red rule
$pdf->SetDrawColor(255,61,51); $pdf->SetLineWidth(0.7);
$pdf->Line($lm,34,$lm+$pageW,34); $pdf->SetLineWidth(0.2); $pdf->SetDrawColor(180,180,180);

// Title
$pdf->SetFont('Arial','B',15); $pdf->SetTextColor(30,30,30);
$pdf->SetXY($lm,38); $pdf->Cell(0,9,'VENDOR REGISTRATION FORM',0,1,'C');

// Subtitle / serial
$pdf->SetFont('Arial','I',9); $pdf->SetTextColor(150,150,150);
$sub_serial = 'VR-' . str_pad($sub_id ?? 1, 5, '0', STR_PAD_LEFT);
$pdf->SetX($lm); $pdf->Cell(0,6,'Reference: '.$sub_serial.'   |   Date: '.date('d M Y'),0,1,'C');
$pdf->Ln(4);

// Section helper
$section = function(string $title) use ($pdf,$lm,$pageW) {
    $pdf->SetFont('Arial','B',9); $pdf->SetTextColor(255,61,51);
    $pdf->SetFillColor(255,248,248);
    $pdf->SetX($lm); $pdf->Cell($pageW,7,'  '.$title,0,1,'L',true);
    $pdf->SetDrawColor(255,180,180); $pdf->SetLineWidth(0.3);
    $pdf->Line($lm,$pdf->GetY(),$lm+$pageW,$pdf->GetY());
    $pdf->SetLineWidth(0.2); $pdf->SetDrawColor(200,200,200);
    $pdf->Ln(3);
};
$row = function(string $label, string $value) use ($pdf,$lm,$pageW) {
    if(trim($value)==='') return;
    $pdf->SetFont('Arial','',9); $pdf->SetTextColor(100,100,100);
    $pdf->SetX($lm); $pdf->Cell(52,6,$label,0,0);
    $pdf->SetFont('Arial','',9); $pdf->SetTextColor(20,20,20);
    $pdf->MultiCell($pageW-52,6,$value,0,'L');
    $pdf->Ln(1);
};

$addr = trim(implode(', ', array_filter([$data['zip_code'],$data['city'],$data['state'],$data['country']])));

$section('COMPANY DETAILS');
$row('Legal Name',        $data['legal_name']);
$row('AKA / DBA',         $data['aka_name']);
$row('Address',           $addr);
$row('Company License',   $data['company_license']);
$row('Tax Number',        $data['tax_no']);
$row('CO Registration No',$data['co_reg_no']);
$row('ICV Score',         $data['icv_score']);
$row('Expected Annual Spend', $data['annual_spend']);
$row('Related Party',     $data['related_party'] . ($data['related_party']==='Yes' && $data['related_party_desc'] ? ' — '.$data['related_party_desc'] : ''));
$row('How did you hear',  implode(', ', (array)$data['source']) . ($data['client_ref_name'] ? ' (Ref: '.$data['client_ref_name'].')' : ''));
$pdf->Ln(3);

$section('BANK DETAILS');
$row('Account Name',    $data['bank_account_name']);
$row('Company Address', $data['bank_company_address']);
$row('Account Number',  $data['account_number']);
$row('Currency',        $data['bank_currency']);
$row('Bank Name',       $data['bank_name']);
$row('Bank Address',    $data['bank_address']);
$row('SWIFT Code',      $data['swift_code']);
$row('IBAN',            $data['iban']);
$pdf->Ln(3);

$section('AUTHORIZED REPRESENTATIVE');
$row('Full Name',    $data['rep_name']);
$row('Designation', $data['rep_designation']);
$row('Date',        $data['rep_date']);
$pdf->Ln(3);

$section('DOCUMENTS SUBMITTED');
foreach($doc_fields as $field=>$label){
    $f = $uploaded_files[$field] ?? null;
    $row($label, $f ? 'Uploaded' : 'Not provided');
}
$pdf->Ln(6);

// Signature block
$pdf->SetFont('Arial','',9); $pdf->SetTextColor(70,70,70);
$pdf->SetX($lm);
$pdf->MultiCell($pageW,5.5,'I, the undersigned Authorized Representative, hereby certify that all information provided in this Vendor Registration Form is true, accurate, and complete to the best of my knowledge.',0,'L');
$pdf->Ln(10);
$pdf->SetDrawColor(80,80,80); $pdf->SetLineWidth(0.4);
$pdf->Line($lm,$pdf->GetY(),$lm+70,$pdf->GetY());
$pdf->Ln(3);
$pdf->SetFont('Arial','',8); $pdf->SetTextColor(130,130,130);
$pdf->SetX($lm); $pdf->Cell(70,5,'Authorized Signature',0,0,'C');
$pdf->SetX($lm+90); $pdf->Cell(76,5,'Date: '.date('d M Y'),0,1);

// Save PDF
$ts = date('Ymd_His');
$legal_slug_pdf = preg_replace('/[^a-z0-9]+/', '_', strtolower($data['legal_name']));
$pdf_filename = 'vendor_reg_' . $legal_slug_pdf . '_' . $ts . '.pdf';
$pdf_path = STORAGE_PATH . $pdf_filename;
if (!is_dir(STORAGE_PATH)) mkdir(STORAGE_PATH, 0755, true);
$pdf->Output('F', $pdf_path);

// --- Save to DB (user_id = NULL for public submissions) ---
$stmt = db()->prepare("INSERT INTO form_submissions (user_id, form_type, form_data, pdf_filename) VALUES (NULL, 'vendor_reg', ?, ?)");
$stmt->execute([json_encode($data), $pdf_filename]);
$sub_id = db()->lastInsertId();

// --- Build email body ---
$addr = trim(implode(', ', array_filter([
    $data['zip_code'], $data['city'], $data['state'], $data['country']
])));

$body = "A new Vendor Registration has been submitted via G2 Tools.\n\n";
$body .= "=== VENDOR DETAILS ===\n";
$body .= "Legal Name:       " . $data['legal_name'] . "\n";
if ($data['aka_name'])        $body .= "AKA/DBA:          " . $data['aka_name'] . "\n";
$body .= "Address:          " . $addr . "\n";
if ($data['company_license']) $body .= "Company License:  " . $data['company_license'] . "\n";
if ($data['tax_no'])          $body .= "Tax No:           " . $data['tax_no'] . "\n";
if ($data['co_reg_no'])       $body .= "CO Reg No:        " . $data['co_reg_no'] . "\n";
if ($data['icv_score'])       $body .= "ICV Score:        " . $data['icv_score'] . "\n";
$body .= "Annual Spend:     " . $data['annual_spend'] . "\n";
$body .= "Related Party:    " . $data['related_party'] . "\n";
if ($data['related_party'] === 'Yes' && $data['related_party_desc'])
    $body .= "Related Party Description:\n" . $data['related_party_desc'] . "\n";
if ($data['source'])          $body .= "Source:           " . implode(', ', $data['source']) . "\n";

$body .= "\n=== BANK DETAILS ===\n";
$body .= "Account Name:     " . $data['bank_account_name'] . "\n";
$body .= "Account Number:   " . $data['account_number'] . "\n";
$body .= "Currency:         " . $data['bank_currency'] . "\n";
$body .= "Bank Name:        " . $data['bank_name'] . "\n";
if ($data['bank_address'])    $body .= "Bank Address:     " . $data['bank_address'] . "\n";
if ($data['swift_code'])      $body .= "SWIFT:            " . $data['swift_code'] . "\n";
if ($data['iban'])            $body .= "IBAN:             " . $data['iban'] . "\n";

$body .= "\n=== AUTHORIZED REPRESENTATIVE ===\n";
$body .= "Name:             " . $data['rep_name'] . "\n";
$body .= "Date:             " . $data['rep_date'] . "\n";
$body .= "Designation:      " . $data['rep_designation'] . "\n";

$body .= "\n=== ATTACHMENTS UPLOADED ===\n";
$any_file = false;
foreach ($doc_fields as $field => $label) {
    $f = $uploaded_files[$field];
    $body .= $label . ": " . ($f ? "Uploaded ({$f})" : "Not provided") . "\n";
    if ($f) $any_file = true;
}
$body .= "\n---\nSubmission ID: {$sub_id}\nSubmitted: " . date('d M Y H:i') . "\n";

// --- Send email to finance ---
$recipients = get_finance_emails();
$email_sent = false;

if (!empty($recipients)) {
    $subject  = 'New Vendor Registration — ' . $data['legal_name'];
    $boundary = '----=_G2VR_' . uniqid();

    $headers  = "From: G2 Tools <noreply@g2group.com>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $msg  = "--{$boundary}\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $msg .= $body . "\r\n\r\n";

    // Attach uploaded files
    foreach ($doc_fields as $field => $label) {
        $f = $uploaded_files[$field] ?? null;
        if (!$f) continue;
        $fpath = $upload_dir . $f;
        if (!file_exists($fpath)) continue;
        $mime_map = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
        $ext2 = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $mime = $mime_map[$ext2] ?? 'application/octet-stream';
        $encoded = base64_encode(file_get_contents($fpath));
        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: {$mime}; name=\"{$f}\"\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n";
        $msg .= "Content-Disposition: attachment; filename=\"{$f}\"\r\n\r\n";
        $msg .= chunk_split($encoded) . "\r\n";
    }
    $msg .= "--{$boundary}--";

    $email_sent = mail(implode(', ', $recipients), $subject, $msg, $headers);
}

// Send confirmation to vendor
if (!empty($data['rep_email'])) {
    $body = mail_template('Vendor Registration Received', "
        <p>Dear <strong>".htmlspecialchars($data['rep_name'])."</strong>,</p>
        <p>Thank you for submitting your vendor registration for <strong>".htmlspecialchars($data['legal_name'])."</strong>. We have received your application and our team will review it shortly.</p>
        <div class='info-box'><strong>Company</strong> ".htmlspecialchars($data['legal_name'])."</div>
        <div class='info-box'><strong>Submitted On</strong> ".date('d M Y')."</div>
        <p>If you have any questions, please contact us at <a href='mailto:hrdoha@greydoha.com'>hrdoha@greydoha.com</a>.</p>");
    send_mail(['email'=>$data['rep_email'],'name'=>$data['rep_name']], 'Vendor Registration Received — G2 Group', $body);
}

// Redirect to thank-you
header('Location: /g2forms/vendor/thankyou.php');
exit;
