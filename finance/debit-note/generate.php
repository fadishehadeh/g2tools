<?php
session_start();
require '../../config.php';
require_login();
require '../../amex/lib/fpdf.php';

function s($k) { return isset($_POST[$k]) ? trim(strip_tags($_POST[$k])) : ''; }

$companies = [
    'g2'   => ['name' => 'G2 Group',      'logo' => 'logo.png',  'type' => 'PNG',  'ratio' => 200/102],
    'pn'   => ['name' => 'PIN and Notch', 'logo' => 'PN.gif',    'type' => 'GIF',  'ratio' => 865/206],
    'grey' => ['name' => 'Grey',          'logo' => 'grey.jpeg', 'type' => 'JPEG', 'ratio' => 1600/468],
];
$co  = $companies[s('company')] ?? $companies['grey'];
$pfx = ['g2'=>'G2','pn'=>'PN','grey'=>'Grey'][s('company')] ?? 'Grey';

$to_name      = s('to_name');
$attention    = s('attention');
$dn_date      = s('dn_date');
$currency     = s('currency') ?: 'QAR';
$prepared_by  = s('prepared_by');
$approved_by  = s('approved_by');
$total        = (float)s('total');
$attach_inv   = !empty($_POST['attach_invoice']);
$attach_con   = !empty($_POST['attach_contract']);
$attach_email = !empty($_POST['attach_email']);

$descs = array_map('strip_tags', $_POST['desc'] ?? []);
$amts  = array_map('floatval',   $_POST['amt']  ?? []);

// Auto serial
$stmt = db()->prepare("SELECT COUNT(*) FROM form_submissions WHERE form_type='debit_note'");
$stmt->execute();
$serial = $pfx . '-DN-' . str_pad((int)$stmt->fetchColumn() + 1, 5, '0', STR_PAD_LEFT);

// Amount in words
function num_words(float $n, string $cur): string {
    $a=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $b=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $w = function($n) use ($a,$b,&$w){
        if($n<20)return $a[$n];
        if($n<100)return $b[intdiv($n,10)].($n%10?' '.$a[$n%10]:'');
        return $a[intdiv($n,100)].' Hundred'.($n%100?' '.$w($n%100):'');
    };
    $int=(int)$n; $dec=round(($n-$int)*100);
    $s=$w($int).' '.$cur; if($dec>0) $s.=' and '.$w($dec).' Fils';
    return $s;
}

class PDF_DN extends FPDF {
    public string $co_name = '';
    function Header(){}
    function Footer(){
        $this->SetY(-13); $this->SetFont('Arial','I',8);
        $this->SetTextColor(180,180,180);
        $this->Cell(0,6,$this->co_name.'  —  Internal Use Only',0,0,'C');
    }
}

$pdf = new PDF_DN('P','mm','A4');
$pdf->co_name = $co['name'];
$pdf->AddPage(); $pdf->SetMargins(22,22,22); $pdf->SetAutoPageBreak(true,20);
$lm=22; $pageW=166;

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
if (file_exists($rawPath)) $pdf->Image($rawPath, $lm, 16, $logoW, $logoH, $logoType);

// Right-side metadata block
$hdr=[['DN No:',$serial],['To',$to_name],['Attention',$attention],['Date',$dn_date],['Currency',$currency]];
foreach($hdr as [$lbl,$val]){
    $pdf->SetFont('Arial','B',9); $pdf->SetTextColor(80,80,80);
    $pdf->SetX(110); $pdf->Cell(28,7,$lbl,0,0);
    $pdf->SetFont('Arial','',9); $pdf->SetTextColor(20,20,20);
    $pdf->Cell(0,7,$val,0,1);
}

// Red rule
$pdf->SetDrawColor(255,61,51); $pdf->SetLineWidth(0.6);
$pdf->Line($lm,52,$lm+$pageW,52); $pdf->SetLineWidth(0.2);
$pdf->SetDrawColor(180,180,180);

// Form title
$pdf->SetFont('Arial','B',16); $pdf->SetTextColor(30,30,30);
$pdf->SetXY($lm, 55);
$pdf->Cell(0, 10, 'DEBIT NOTE', 0, 1, 'C');

$pdf->SetY(68);

// Italics notice
$pdf->SetFont('Arial','BI',10); $pdf->SetTextColor(0,0,200);
$pdf->SetX($lm);
$pdf->Cell(0,7,'Kindly Note That We Have Debited Your Account With The Following:',0,1);
$pdf->Ln(4);

// Line items
$pdf->SetFont('Arial','',10); $pdf->SetTextColor(40,40,40);
foreach($descs as $i=>$desc){
    if(trim($desc)==='') continue;
    $amt=$amts[$i]??0;
    $pdf->SetX($lm); $pdf->Cell($pageW-50,7,$desc,0,0);
    $pdf->Cell(50,7,number_format($amt,2),0,1,'R');
    $pdf->Ln(2);
}

$pdf->Ln(4);
$pdf->SetDrawColor(200,200,200); $pdf->SetLineWidth(0.3);
$pdf->Line($lm+$pageW-80,$pdf->GetY(),$lm+$pageW,$pdf->GetY());
$pdf->SetFont('Arial','B',11); $pdf->SetTextColor(20,20,20);
$pdf->SetX($lm);
$pdf->Cell($pageW-50,8,'Total',0,0);
$pdf->Cell(50,8,number_format($total,2),0,1,'R');

// Amount in words
$pdf->SetFont('Arial','I',9); $pdf->SetTextColor(200,0,0);
$pdf->SetX($lm); $pdf->Cell(0,7,num_words($total,$currency).' only.',0,1);
$pdf->Ln(6);

// Attachments
$pdf->SetFont('Arial','BI',10); $pdf->SetTextColor(0,0,200);
$pdf->SetX($lm); $pdf->Cell(30,7,'Attachment/s',0,0);
$pdf->SetFont('Arial','',10); $pdf->SetTextColor(40,40,40);
$attachX=$lm+32; $checkW=6;
foreach([['Invoice Copy',$attach_inv],['Contract',$attach_con],['Email',$attach_email]] as [$lbl,$checked]){
    $y=$pdf->GetY();
    $pdf->SetXY($attachX,$y);
    $pdf->SetDrawColor(100,100,100); $pdf->Rect($attachX,$y+1,$checkW,$checkW);
    if($checked){ $pdf->SetFont('Arial','B',10); $pdf->SetXY($attachX,$y); $pdf->Cell($checkW,$checkW,'X',0,0,'C'); }
    $pdf->SetFont('Arial','',10);
    $pdf->SetXY($attachX+$checkW+2,$y); $pdf->Cell(0,7,$lbl,0,1);
}
$pdf->Ln(10);

// Signatories
$pdf->SetFont('Arial','',11); $pdf->SetTextColor(50,50,50);
$pdf->SetX($lm); $pdf->Cell(83,7,'Prepared By',0,0); $pdf->Cell(0,7,'Approved by',0,1);
$pdf->SetFont('Arial','B',11); $pdf->SetTextColor(20,20,20);
$pdf->SetX($lm); $pdf->Cell(83,7,$prepared_by,0,0); $pdf->Cell(0,7,$approved_by,0,1);
$pdf->Ln(4);
$pdf->SetDrawColor(80,80,80); $pdf->SetLineWidth(0.4);
$pdf->Line($lm,$pdf->GetY(),$lm+60,$pdf->GetY());
$pdf->Line($lm+90,$pdf->GetY(),$lm+150,$pdf->GetY());
$pdf->Ln(3);
$pdf->SetFont('Arial','I',7); $pdf->SetTextColor(150,150,150);
$pdf->SetX($lm); $pdf->Cell(0,5,'ccc/dn',0,1);

// Save
$uid=$_SESSION['g2_user']['id']; $ts=date('Ymd_His');
$filename='dn_'.$uid.'_'.$ts.'.pdf';
$filepath=STORAGE_PATH.$filename;
$dl_name=$serial.'.pdf';
$pdf->Output('F',$filepath);

$form_data=json_encode([
    'company'=>s('company'),'co_name'=>$co['name'],
    'serial'=>$serial,'to_name'=>$to_name,'attention'=>$attention,
    'dn_date'=>$dn_date,'currency'=>$currency,'total'=>$total,
    'descs'=>$descs,'amts'=>$amts,
    'attach_invoice'=>$attach_inv,'attach_contract'=>$attach_con,'attach_email'=>$attach_email,
    'prepared_by'=>$prepared_by,'approved_by'=>$approved_by,'dl_name'=>$dl_name,
]);
$stmt=db()->prepare("INSERT INTO form_submissions (user_id,form_type,form_data,pdf_filename) VALUES (?,?,?,?)");
$stmt->execute([$uid,'debit_note',$form_data,$filename]);
$sub_id=db()->lastInsertId();

header('Location: /g2forms/finance/confirm.php?id='.$sub_id);
exit;
