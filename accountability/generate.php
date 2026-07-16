<?php
session_start();
require '../config.php';
require_login();
require_can('finance_accountability');
require('lib/fpdf.php');

function s($key) {
    return isset($_POST[$key]) ? trim(strip_tags($_POST[$key])) : '';
}

$companies = [
    'g2'   => ['name' => 'G2 Group',      'logo' => 'logo.png',  'type' => 'PNG',  'ratio' => 200/102],
    'pn'   => ['name' => 'PIN and Notch', 'logo' => 'PN.gif',    'type' => 'GIF',  'ratio' => 865/206],
    'grey' => ['name' => 'Grey',          'logo' => 'grey.jpeg', 'type' => 'JPEG', 'ratio' => 1600/468],
];
$co = $companies[s('company')] ?? $companies['g2'];

$request_by     = s('request_by');
$department     = s('department');
$position       = s('position');
$item_name      = s('item_name');
$serial_number  = s('serial_number');
$estimated_life = s('estimated_life');
$received_by    = s('received_by');
$received_date  = s('received_date');

class PDF_ACC extends FPDF {
    public string $co_name = '';
    function Header() {}
    function Footer() {
        $this->SetY(-13);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(180, 180, 180);
        $this->Cell(0, 6, $this->co_name . '  —  Internal Use Only', 0, 0, 'C');
    }
}

$pdf = new PDF_ACC('P', 'mm', 'A4');
$pdf->co_name = $co['name'];
$pdf->AddPage();
$pdf->SetMargins(22, 22, 22);
$pdf->SetAutoPageBreak(true, 20);

$lm    = 22;
$pageW = 166;

// Logo
$logoH   = 15;
$logoW   = round($logoH * $co['ratio'], 1);
$rawPath = __DIR__ . '/../' . $co['logo'];
$logoType = $co['type'];
if ($logoType === 'GIF') {
    $tmp = STORAGE_PATH . 'logo_tmp_' . session_id() . '.png';
    $img = imagecreatefromgif($rawPath);
    imagepng($img, $tmp); imagedestroy($img);
    $rawPath = $tmp; $logoType = 'PNG';
}
if (file_exists($rawPath)) $pdf->Image($rawPath, $lm, 16, $logoW, $logoH, $logoType);

// Horizontal rule under logo area
$pdf->SetDrawColor(220, 220, 220);
$pdf->Line($lm, 35, $lm + $pageW, 35);

// ── Title ─────────────────────────────────────────────────────────────────────
$pdf->SetY(40);
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(0, 8, 'ACCOUNTABILITY FOR COMPANY PROPERTY', 0, 1, 'C');
$pdf->Ln(8);

// ── Field rows ────────────────────────────────────────────────────────────────
$labelW = 52;
$colonW = 6;
$valueW = $pageW - $labelW - $colonW;
$rowH   = 10;

$field = function($label, $value, $bold = false) use ($pdf, $lm, $labelW, $colonW, $valueW, $rowH) {
    $pdf->SetXY($lm, $pdf->GetY());
    $pdf->SetFont('Arial', $bold ? 'B' : '', 11);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->Cell($labelW, $rowH, $label, 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell($colonW, $rowH, ':', 0, 0);
    $pdf->SetFont('Arial', $bold ? 'B' : '', 11);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->Cell($valueW, $rowH, $value, 0, 1);
    $pdf->Ln(1);
};

$field('Request By',           $request_by);
$field('Department',           $department);
$field('Position',             $position);
$field('Item Name',            $item_name);
$field('Name / Serial Number', $serial_number, true);
$field('Estimated Life',       $estimated_life);

$pdf->Ln(10);

// ── Acknowledgement paragraph ─────────────────────────────────────────────────
$pdf->SetDrawColor(230, 230, 230);
$pdf->SetFillColor(250, 250, 250);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(30, 30, 30);
$pdf->SetX($lm);
$pdf->Cell(0, 6, 'ACKNOWLEDGEMENT OF RECEIPT OF COMPANY PROPERTY :', 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(50, 50, 50);
$pdf->SetX($lm);
$ackText = 'By signing this form, I agree to the following: (1) I am responsible for the equipment or property issued to me. (2) I will use it/them in the manner intended (3) I will be responsible for any damage done (excluding normal wear and tear) (4) Upon separation from the Company, I will return the item(s) issued to me in proper working order (excluding normal wear & tear) (5) I will replace any items issued to me that are damaged or lost due to my negligence at my expense.';
$pdf->MultiCell($pageW, 6, $ackText, 0, 'L');

$pdf->Ln(12);

// ── Signature section ─────────────────────────────────────────────────────────
$pdf->SetXY($lm, $pdf->GetY());
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(50, 50, 50);
$pdf->Cell($labelW, $rowH, 'Received By', 0, 0);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell($colonW, $rowH, ':', 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(20, 20, 20);
$pdf->Cell($valueW, $rowH, $received_by, 0, 1);

// Signature line (empty — to be signed by hand)
$sigY = $pdf->GetY() + 3;
$pdf->SetXY($lm, $pdf->GetY());
$pdf->Cell($labelW + $colonW, $rowH, '', 0, 0);
$pdf->SetDrawColor(80, 80, 80);
$pdf->Line($lm + $labelW + $colonW, $sigY + 6, $lm + $labelW + $colonW + 70, $sigY + 6);
$pdf->Ln($rowH + 3);

// Date
$pdf->SetXY($lm, $pdf->GetY());
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(50, 50, 50);
$pdf->Cell($labelW, $rowH, 'Date', 0, 0);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell($colonW, $rowH, ':', 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(20, 20, 20);
$pdf->Cell($valueW, $rowH, $received_date, 0, 1);

// ── Save to file + DB, then redirect to download ──────────────────────────────
$uid      = current_user()['id'];
$ts       = date('Ymd_His');
$filename = 'acc_' . $uid . '_' . $ts . '.pdf';
$filepath = STORAGE_PATH . $filename;

$pdf->Output('F', $filepath);

$form_data = json_encode([
    'request_by'     => $request_by,
    'department'     => $department,
    'position'       => $position,
    'item_name'      => $item_name,
    'serial_number'  => $serial_number,
    'estimated_life' => $estimated_life,
    'received_by'    => $received_by,
    'received_date'  => $received_date,
]);

$stmt = db()->prepare("INSERT INTO form_submissions (user_id, form_type, form_data, pdf_filename) VALUES (?,?,?,?)");
$stmt->execute([$uid, 'accountability', $form_data, $filename]);
$sub_id = db()->lastInsertId();

header('Location: /download.php?id=' . $sub_id);
exit;
