<?php
session_start();
require '../config.php';
require_login();
require('lib/fpdf.php');

function s($key) {
    return isset($_POST[$key]) ? trim(strip_tags($_POST[$key])) : '';
}

// ── Company / logo config ─────────────────────────────────────────────────────
$companies = [
    'g2'   => ['name' => 'G2 Group',      'logo' => 'logo.png',   'type' => 'PNG',  'ratio' => 200/102],
    'pn'   => ['name' => 'PIN and Notch', 'logo' => 'PN.gif',     'type' => 'GIF',  'ratio' => 865/206],
    'grey' => ['name' => 'Grey',          'logo' => 'grey.jpeg',  'type' => 'JPEG', 'ratio' => 1600/468],
];
$company_key = s('company');
$co = $companies[$company_key] ?? $companies['g2'];

$card_type         = s('card_type') ?: 'AMEX';
$cardholder_name   = s('cardholder_name');
$card_last4        = s('card_last4');
$merchant          = s('merchant');
// Auto-generate serial: prefix based on company, incrementing from all-time count
$serial_prefixes = ['g2' => 'G2', 'pn' => 'PN', 'grey' => 'Grey'];
$serial_prefix   = $serial_prefixes[$company_key] ?? 'G2';
$serial_stmt     = db()->prepare(
    "SELECT COUNT(*) FROM form_submissions
     WHERE form_type = 'amex'
     AND JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.company')) = ?"
);
$serial_stmt->execute([$co['name']]);
$serial_count  = (int)$serial_stmt->fetchColumn();
$serial_number = $serial_prefix . '-' . str_pad($serial_count + 1, 5, '0', STR_PAD_LEFT);
$po_number         = s('po_number');
$billable          = s('billable');
$client_name       = s('client_name');
$nature_of_expense = s('nature_of_expense');
$currency          = s('currency') ?: 'USD';
$amount            = s('amount');
$authorized_name   = s('authorized_name');
$finance_approval  = s('finance_approval');
$finance_date      = s('finance_date');
$mgmt_approval     = s('mgmt_approval');
$mgmt_date         = s('mgmt_date');

$card_display = '**** ****** *' . $card_last4;

class PDF extends FPDF {
    function Header() {}
    function Footer() {
        $this->SetY(-13);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(180, 180, 180);
        $this->Cell(0, 6, 'G2 Group  —  Internal Use Only', 0, 0, 'C');
    }

    function Ellipse($x, $y, $rx, $ry, $style = '') {
        if ($style === 'F') $op = 'f';
        elseif ($style === 'FD' || $style === 'DF') $op = 'B';
        else $op = 'S';
        $lx = 4/3*(M_SQRT2-1)*$rx;
        $ly = 4/3*(M_SQRT2-1)*$ry;
        $k = $this->k; $h = $this->h;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x+$rx)*$k,($h-$y)*$k,($x+$rx)*$k,($h-($y-$ly))*$k,($x+$lx)*$k,($h-($y-$ry))*$k,$x*$k,($h-($y-$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$lx)*$k,($h-($y-$ry))*$k,($x-$rx)*$k,($h-($y-$ly))*$k,($x-$rx)*$k,($h-$y)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$rx)*$k,($h-($y+$ly))*$k,($x-$lx)*$k,($h-($y+$ry))*$k,$x*$k,($h-($y+$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x+$lx)*$k,($h-($y+$ly))*$k,($x+$rx)*$k,($h-($y+$ly))*$k,($x+$rx)*$k,($h-$y)*$k,$op));
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(22, 22, 22);
$pdf->SetAutoPageBreak(true, 20);

$lm    = 22;
$pageW = 166;
$rowH  = 9;

// ── Company Logo ──────────────────────────────────────────────────────────────
$logoH    = 12;
$logoW    = round($logoH * $co['ratio'], 1);
$rawPath  = __DIR__ . '/../' . $co['logo'];
$logoType = $co['type'];

if ($logoType === 'GIF') {
    // FPDF doesn't support GIF natively — convert to PNG via GD
    $tmpLogo  = STORAGE_PATH . 'logo_tmp_' . session_id() . '.png';
    $gifImg   = imagecreatefromgif($rawPath);
    imagepng($gifImg, $tmpLogo);
    imagedestroy($gifImg);
    $logoPath = $tmpLogo;
    $logoType = 'PNG';
} else {
    $logoPath = $rawPath;
}

if (file_exists($logoPath)) {
    $pdf->Image($logoPath, $lm, 16, $logoW, $logoH, $logoType);
}

// Red underline
$pdf->SetDrawColor(255, 61, 51);
$pdf->SetLineWidth(0.8);
$pdf->Line($lm, 35, $lm + $pageW, 35);
$pdf->SetLineWidth(0.2);
$pdf->SetDrawColor(180, 180, 180);

// ── Title ─────────────────────────────────────────────────────────────────────
$pdf->SetY(40);
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(0, 8, 'CREDIT CARD AUTHORIZATION FORM', 0, 1, 'C');
$pdf->Ln(6);

// ── Section label helper ──────────────────────────────────────────────────────
$section = function($text) use ($pdf, $lm, $pageW) {
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(255, 61, 51);
    $pdf->SetX($lm);
    $pdf->Cell(0, 5, $text, 0, 1);
    $pdf->SetDrawColor(255, 61, 51);
    $pdf->SetLineWidth(0.4);
    $pdf->Line($lm, $pdf->GetY(), $lm + $pageW, $pdf->GetY());
    $pdf->SetLineWidth(0.2);
    $pdf->SetDrawColor(180, 180, 180);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(4);
};

// Field row helper (label : value)
$labelW = 52; $colonW = 6; $valueW = $pageW - $labelW - $colonW;
$field = function($label, $value, $bold = false) use ($pdf, $lm, $labelW, $colonW, $valueW, $rowH) {
    $pdf->SetXY($lm, $pdf->GetY());
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell($labelW, $rowH, $label, 0, 0);
    $pdf->Cell($colonW, $rowH, ':', 0, 0);
    $pdf->SetFont('Arial', $bold ? 'B' : '', 11);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->Cell($valueW, $rowH, $value, 0, 1);
    $pdf->Ln(1);
};

// ── Credit Card Information ───────────────────────────────────────────────────
$section('CREDIT CARD INFORMATION');
$field('Card Type',            $card_type);
$field('Cardholder Name',      $cardholder_name);
$field('Card Number',          $card_display);
$field('Name of Merchant',     $merchant);
if ($serial_number) $field('Serial / Ref No.',   $serial_number);

// ── Purchase Order ────────────────────────────────────────────────────────────
$section('PURCHASE ORDER');
$field('PO Number', $po_number, true);

// ── Billable ──────────────────────────────────────────────────────────────────
$section('BILLABLE');

$rx = 5; $ry = 3.5;
$billH = 48;
$y = $pdf->GetY();

$pdf->SetDrawColor(200, 215, 220);
$pdf->Rect($lm, $y, $pageW, $billH);
$pdf->Line($lm, $y + $billH/2, $lm + $pageW, $y + $billH/2);

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(30, 30, 30);
$pdf->SetXY($lm + 2, $y + $billH/2 - 5);
$pdf->Cell(28, 9, 'Billable', 0, 0);

$contentX = $lm + 32;

// YES
$yesY = $y + 10; $cy = $yesY + 2.5;
if ($billable === 'YES') {
    $pdf->SetFillColor(200, 0, 0); $pdf->SetDrawColor(140, 0, 0);
} else {
    $pdf->SetFillColor(255, 255, 255); $pdf->SetDrawColor(150, 150, 150);
}
$pdf->Ellipse($contentX + 5, $cy, $rx, $ry, 'FD');
$pdf->SetDrawColor(200, 215, 220);

$pdf->SetFont('Arial', 'B', 10); $pdf->SetTextColor(0, 0, 0);
$pdf->SetXY($contentX + 12, $yesY - 1);
$pdf->Cell(24, 6, 'YES (Vendor)', 0, 0);
$pdf->SetFont('Arial', 'I', 8); $pdf->SetTextColor(180, 0, 0);
$pdf->Cell(0, 6, '(a copy of approval and PO/BO to be attached)', 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'B', 8); $pdf->SetTextColor(80, 80, 80);
$pdf->SetXY($contentX + 12, $yesY + 7);
$pdf->Cell(26, 5, 'NAME OF CLIENT:', 0, 0);
$pdf->SetFont('Arial', 'B', 10); $pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 5, $client_name, 0, 1);

// NO
$noY = $y + $billH/2 + 9; $cy2 = $noY + 2.5;
if ($billable === 'NO') {
    $pdf->SetFillColor(200, 0, 0); $pdf->SetDrawColor(140, 0, 0);
} else {
    $pdf->SetFillColor(255, 255, 255); $pdf->SetDrawColor(150, 150, 150);
}
$pdf->Ellipse($contentX + 5, $cy2, $rx, $ry, 'FD');
$pdf->SetDrawColor(200, 215, 220);

$pdf->SetFont('Arial', 'B', 10); $pdf->SetTextColor(0, 0, 0);
$pdf->SetXY($contentX + 12, $noY - 1);
$pdf->Cell(0, 6, 'NO', 0, 1);
$pdf->SetFont('Arial', 'B', 8); $pdf->SetTextColor(80, 80, 80);
$pdf->SetXY($contentX + 12, $noY + 7);
$pdf->Cell(36, 5, 'NATURE OF EXPENSE:', 0, 0);
$pdf->SetFont('Arial', '', 10); $pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 5, $nature_of_expense, 0, 1);

$pdf->SetY($y + $billH);

// ── Amount ────────────────────────────────────────────────────────────────────
$section('AMOUNT');
$pdf->SetXY($lm, $pdf->GetY());
$pdf->SetFont('Arial', '', 11); $pdf->SetTextColor(80, 80, 80);
$pdf->Cell($labelW, $rowH, 'Amount', 0, 0);
$pdf->Cell($colonW, $rowH, ':', 0, 0);
$pdf->SetFont('Arial', 'B', 12); $pdf->SetTextColor(255, 61, 51);
$pdf->Cell(16, $rowH, $currency, 0, 0);
$pdf->SetFont('Arial', 'B', 11); $pdf->SetTextColor(20, 20, 20);
$pdf->Cell(0, $rowH, '  ' . $amount, 0, 1);
$pdf->Ln(2);

// ── Authorization ─────────────────────────────────────────────────────────────
$section('AUTHORIZATION');

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(50, 50, 50);
$pdf->SetX($lm);
$pdf->Cell(32, 7, 'This is to authorize', 0, 0);
$nameX = $pdf->GetX();
$nameW = 62;
$lineY = $pdf->GetY() + 6.5;
$pdf->SetDrawColor(60, 60, 60);
$pdf->Line($nameX, $lineY, $nameX + $nameW, $lineY);
$pdf->SetFont('Arial', 'B', 10); $pdf->SetTextColor(0, 0, 0);
$pdf->SetXY($nameX + 2, $pdf->GetY() + 1);
$pdf->Cell($nameW, 6, $authorized_name, 0, 0);
$pdf->SetFont('Arial', '', 10); $pdf->SetTextColor(50, 50, 50);
$pdf->SetXY($nameX + $nameW, $pdf->GetY() - 1);
$pdf->Cell(0, 6, ' to use company credit card to pay above expense.', 0, 1);

$pdf->Ln(10);

// Approval lines
$sig1x = $lm + 44; $lineW = 56; $datLbl = 133; $datX = $datLbl + 11;

// Finance
$rowY = $pdf->GetY(); $sigY = $rowY + 6;
$pdf->SetFont('Arial', '', 11); $pdf->SetTextColor(50, 50, 50);
$pdf->SetXY($lm, $rowY); $pdf->Cell(44, 7, 'Finance Approval', 0, 0);
$pdf->SetDrawColor(80, 80, 80);
$pdf->Line($sig1x, $sigY, $sig1x + $lineW, $sigY);
$pdf->SetFont('Arial', '', 10); $pdf->SetXY($sig1x + 2, $rowY + 1);
$pdf->Cell($lineW - 4, 6, $finance_approval, 0, 0);
$pdf->SetFont('Arial', '', 11); $pdf->SetXY($datLbl, $rowY);
$pdf->Cell(11, 7, 'Date', 0, 0);
$pdf->Line($datX, $sigY, $datX + 34, $sigY);
$pdf->SetFont('Arial', '', 10); $pdf->SetXY($datX + 1, $rowY + 1);
$pdf->Cell(32, 6, $finance_date, 0, 1);

$pdf->Ln(10);

// Management
$rowY2 = $pdf->GetY(); $sigY2 = $rowY2 + 6; $sig2x = $lm + 52;
$pdf->SetFont('Arial', '', 11); $pdf->SetTextColor(50, 50, 50);
$pdf->SetXY($lm, $rowY2); $pdf->Cell(52, 7, 'Management Approval', 0, 0);
$pdf->Line($sig2x, $sigY2, $sig2x + $lineW - 8, $sigY2);
$pdf->SetFont('Arial', '', 10); $pdf->SetXY($sig2x + 2, $rowY2 + 1);
$pdf->Cell($lineW - 12, 6, $mgmt_approval, 0, 0);
$pdf->SetFont('Arial', '', 11); $pdf->SetXY($datLbl, $rowY2);
$pdf->Cell(11, 7, 'Date', 0, 0);
$pdf->Line($datX, $sigY2, $datX + 34, $sigY2);
$pdf->SetFont('Arial', '', 10); $pdf->SetXY($datX + 1, $rowY2 + 1);
$pdf->Cell(32, 6, $mgmt_date, 0, 1);

// ── Save to file + DB, then send to browser ───────────────────────────────────
$uid         = current_user()['id'];
$ts          = date('Ymd_His');
$dl_name     = 'AMEX_Credit Card Authorization - ' . $serial_number . '.pdf';
$filename    = 'amex_' . $uid . '_' . $ts . '.pdf';
$filepath    = STORAGE_PATH . $filename;

$pdf->Output('F', $filepath);

$form_data = json_encode([
    'company' => $co['name'],
    'card_type' => $card_type, 'cardholder_name' => $cardholder_name,
    'card_last4' => $card_last4, 'merchant' => $merchant,
    'serial_number' => $serial_number,
    'po_number' => $po_number,
    'billable' => $billable, 'client_name' => $client_name,
    'nature_of_expense' => $nature_of_expense, 'currency' => $currency,
    'amount' => $amount, 'authorized_name' => $authorized_name,
    'finance_approval' => $finance_approval, 'finance_date' => $finance_date,
    'mgmt_approval' => $mgmt_approval, 'mgmt_date' => $mgmt_date,
    'dl_name' => $dl_name,
]);

$stmt = db()->prepare("INSERT INTO form_submissions (user_id, form_type, form_data, pdf_filename) VALUES (?,?,?,?)");
$stmt->execute([$uid, 'amex', $form_data, $filename]);
$sub_id = db()->lastInsertId();

// Clean up temp GIF conversion if used
if (isset($tmpLogo) && file_exists($tmpLogo)) @unlink($tmpLogo);

header('Location: /amex/confirm.php?id=' . $sub_id);
exit;
