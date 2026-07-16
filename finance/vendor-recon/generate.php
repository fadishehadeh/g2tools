<?php
session_start();
require '../../config.php';
require_login();
require_can('finance_vendor_recon');
require '../../amex/lib/fpdf.php';

function s($k) { return isset($_POST[$k]) ? trim(strip_tags($_POST[$k])) : ''; }

$companies = [
    'g2'   => ['name' => 'G2 Group',      'logo' => 'logo.png',  'type' => 'PNG',  'ratio' => 200/102],
    'pn'   => ['name' => 'PIN and Notch', 'logo' => 'PN.gif',    'type' => 'GIF',  'ratio' => 865/206],
    'grey' => ['name' => 'Grey',          'logo' => 'grey.jpeg', 'type' => 'JPEG', 'ratio' => 1600/468],
];
$co  = $companies[s('company')] ?? $companies['grey'];
$pfx = ['g2'=>'G2','pn'=>'PN','grey'=>'Grey'][s('company')] ?? 'Grey';

$vendor_name       = s('vendor_name');
$vendor_no         = s('vendor_no');
$recon_date        = s('recon_date');
$grey_soa_balance  = (float)s('grey_soa_balance');
$vendor_soa_balance= (float)s('vendor_soa_balance');
$total_recon       = (float)s('total_recon');
$net_grey          = (float)s('net_grey');
$variance          = (float)s('variance');
$prepared_by       = s('prepared_by');
$reviewed_by       = s('reviewed_by');
$approved_by       = s('approved_by');

$r_dates  = array_map('strip_tags', $_POST['r_date']       ?? []);
$r_parts  = array_map('strip_tags', $_POST['r_particular'] ?? []);
$r_pos    = array_map('strip_tags', $_POST['r_po']         ?? []);
$r_invs   = array_map('strip_tags', $_POST['r_inv']        ?? []);
$r_amts   = array_map('floatval',   $_POST['r_amt']        ?? []);

$stmt = db()->prepare("SELECT COUNT(*) FROM form_submissions WHERE form_type='vendor_recon'");
$stmt->execute();
$serial = $pfx . '-VR-' . str_pad((int)$stmt->fetchColumn() + 1, 5, '0', STR_PAD_LEFT);

class PDF_VR extends FPDF {
    public string $co_name = '';
    function Header(){}
    function Footer(){
        $this->SetY(-13); $this->SetFont('Arial','I',8);
        $this->SetTextColor(180,180,180);
        $this->Cell(0,6,$this->co_name.'  —  Internal Use Only',0,0,'C');
    }
}

$pdf = new PDF_VR('P','mm','A4');
$pdf->co_name = $co['name'];
$pdf->AddPage(); $pdf->SetMargins(20,20,20); $pdf->SetAutoPageBreak(true,20);
$lm=20; $pageW=170;

// Logo
$logoH   = 12;
$logoW   = round($logoH * $co['ratio'], 1);
$rawPath = __DIR__ . '/../../' . $co['logo'];
$logoType = $co['type'];
if ($logoType === 'GIF') {
    $tmp = STORAGE_PATH . 'logo_tmp_' . session_id() . '.png';
    $img = imagecreatefromgif($rawPath);
    imagepng($img, $tmp); imagedestroy($img);
    $rawPath = $tmp; $logoType = 'PNG';
}
if (file_exists($rawPath)) $pdf->Image($rawPath, $lm, 14, $logoW, $logoH, $logoType);

// Title
$pdf->SetFont('Arial','BU',13); $pdf->SetTextColor(30,30,30);
$pdf->SetXY($lm,34); $pdf->Cell(0,8,'VENDOR PAYABLE RECONCILIATION',0,1,'C');

// Vendor header
$pdf->SetFont('Arial','B',10); $pdf->SetXY($lm,$pdf->GetY()+2);
$pdf->Cell(28,8,'Vendor Name',0,0);
$pdf->SetFont('Arial','I',7); $pdf->SetXY($lm,$pdf->GetY()+8);
$pdf->Cell(28,5,'(pls include AUX CODE)',0,0);
$pdf->SetFont('Arial','',10); $pdf->SetTextColor(20,20,20);
$pdf->SetXY($lm+28,44); $pdf->Cell(80,8,$vendor_name,0,0);
$pdf->SetFont('Arial','',9); $pdf->SetTextColor(80,80,80);
$pdf->SetXY($lm+112,44); $pdf->Cell(22,8,'Vendor No#',0,0);
$pdf->SetFont('Arial','',10); $pdf->SetTextColor(20,20,20);
$pdf->Cell(18,8,$vendor_no,0,0);
$pdf->SetFont('Arial','',9); $pdf->SetTextColor(80,80,80);
$pdf->Cell(10,8,'Date',0,0);
$pdf->SetFont('Arial','',10); $pdf->SetTextColor(20,20,20);
$pdf->Cell(0,8,$recon_date,0,1);

$pdf->SetY(56);
$pdf->SetFont('Arial','',9); $pdf->SetTextColor(80,80,80);
$pdf->SetX($lm); $pdf->Cell(60,7,'Balance as per Grey SOA',0,0);
$pdf->SetFont('Arial','B',10); $pdf->SetTextColor(20,20,20);
$pdf->Cell(0,7,number_format($grey_soa_balance,2),0,1,'R');

$pdf->SetFont('Arial','',8); $pdf->SetTextColor(80,80,80);
$pdf->SetX($lm+40); $pdf->Cell(0,6,'Add/Less : Reconciling Items',0,1);

// Table header
$colW=[28,56,28,28,30];
$pdf->SetFillColor(240,242,245); $pdf->SetFont('Arial','B',8); $pdf->SetTextColor(60,60,60);
$pdf->SetX($lm);
foreach([['Date',$colW[0]],['Particulars',$colW[1]],['Grey PO#',$colW[2]],['Vendor Inv#',$colW[3]],['Amount',$colW[4]]] as [$h,$w]) {
    $align = ($h==='Amount')?'R':'C';
    $pdf->Cell($w,7,$h,'B',0,$align,true);
}
$pdf->Ln();

// Rows
$pdf->SetFont('Arial','',8); $pdf->SetTextColor(30,30,30);
$fill=false;
foreach($r_dates as $i=>$d){
    if(trim($d)==='' && trim($r_parts[$i])==='' && ($r_amts[$i]??0)==0) continue;
    $pdf->SetFillColor($fill?250:255,$fill?250:255,$fill?250:255);
    $pdf->SetX($lm);
    $pdf->Cell($colW[0],6.5,$d,'B',0,'L',$fill);
    $pdf->Cell($colW[1],6.5,$r_parts[$i]??'','B',0,'L',$fill);
    $pdf->Cell($colW[2],6.5,$r_pos[$i]??'','B',0,'C',$fill);
    $pdf->Cell($colW[3],6.5,$r_invs[$i]??'','B',0,'C',$fill);
    $pdf->Cell($colW[4],6.5,number_format($r_amts[$i]??0,2),'B',1,'R',$fill);
    $fill=!$fill;
}

// Totals
$pdf->SetFont('Arial','',8); $pdf->SetTextColor(80,80,80);
$pdf->SetX($lm); $pdf->Cell(array_sum($colW)-30,7,'TOTAL RECONCILING ITEMS',0,0,'C');
$pdf->SetFont('Arial','B',9); $pdf->SetTextColor(20,20,20);
$pdf->Cell(30,7,number_format($total_recon,2),0,1,'R');
$pdf->Ln(2);

$rows=[
    ['Net Balance as per Grey SOA',$net_grey,true],
    ['Net Balance as per Vendor SOA',$vendor_soa_balance,false],
    ['VARIANCE',$variance,true],
];
foreach($rows as [$lbl,$val,$bold]){
    $pdf->SetFont('Arial',$bold?'B':'',9); $pdf->SetTextColor($bold?20:80,$bold?20:80,$bold?20:80);
    $pdf->SetX($lm); $pdf->Cell(array_sum($colW)-30,7,$lbl,0,0);
    $pdf->SetFont('Arial','B',10); $pdf->SetTextColor($bold?255:20,$bold?61:20,$bold?51:20);
    $pdf->Cell(30,7,number_format($val,2),0,1,'R');
}
$pdf->Ln(16);

// Signatories
$sigW=56;
foreach([['Prepared by:',$prepared_by],['Reviewed by:',$reviewed_by],['Approved by:',$approved_by]] as [$lbl,$name]){
    $x=$pdf->GetX(); $y=$pdf->GetY();
    $pdf->SetFont('Arial','',9); $pdf->SetTextColor(80,80,80);
    $pdf->Cell($sigW,6,$lbl,0,0);
}
$pdf->Ln(6);
foreach([['Prepared by:',$prepared_by],['Reviewed by:',$reviewed_by],['Approved by:',$approved_by]] as [$lbl,$name]){
    $pdf->SetFont('Arial','B',9); $pdf->SetTextColor(20,20,20);
    $pdf->Cell($sigW,6,$name,0,0);
}
$pdf->Ln(6);
$pdf->SetDrawColor(80,80,80); $pdf->SetLineWidth(0.4);
$x=$lm;
foreach([0,1,2] as $i){
    $pdf->Line($x,$pdf->GetY(),$x+48,$pdf->GetY()); $x+=56;
}

// Save
$uid=$_SESSION['g2_user']['id']; $ts=date('Ymd_His');
$filename='vr_'.$uid.'_'.$ts.'.pdf';
$filepath=STORAGE_PATH.$filename;
$dl_name=$serial.'.pdf';
$pdf->Output('F',$filepath);

$form_data=json_encode([
    'company'=>s('company'),'co_name'=>$co['name'],
    'serial'=>$serial,'vendor_name'=>$vendor_name,'vendor_no'=>$vendor_no,
    'recon_date'=>$recon_date,'grey_soa_balance'=>$grey_soa_balance,
    'vendor_soa_balance'=>$vendor_soa_balance,'total_recon'=>$total_recon,
    'net_grey'=>$net_grey,'variance'=>$variance,
    'prepared_by'=>$prepared_by,'reviewed_by'=>$reviewed_by,'approved_by'=>$approved_by,
    'dl_name'=>$dl_name,
]);
$stmt=db()->prepare("INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename) VALUES (?,?,?,?)");
$stmt->execute([$uid,'vendor_recon',$form_data,$filename]);
$sub_id=db()->lastInsertId();

require_once '../../mailer.php';
$fin_emails = get_finance_emails();
if ($fin_emails && file_exists($filepath)) {
    $subj = "New Vendor Recon — {$vendor_name} [#{$sub_id}]";
    $html = mail_template("New Vendor Payable Reconciliation", "
        <p>A new Vendor Payable Reconciliation has been submitted and requires your approval.</p>
        <div class='info-box'><strong>Reference</strong> #$sub_id</div>
        <div class='info-box'><strong>Vendor</strong> " . htmlspecialchars($vendor_name) . "</div>
        <div class='info-box'><strong>Submitted By</strong> " . htmlspecialchars(current_user()['name'] ?? '') . "</div>
        <a class='btn' href='https://g2tools.greydoha.com/admin/submission-view.php?id=$sub_id'>Review &amp; Approve</a>
    ");
    foreach ($fin_emails as $to_email) {
        send_mail_with_attachment($to_email, $subj, $html, $filepath, $filename);
    }
}

header('Location: /finance/confirm.php?id='.$sub_id);
exit;
