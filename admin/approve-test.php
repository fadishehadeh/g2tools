<?php
/**
 * One-off test — stamps the latest pending submission and emails fshehadeh@gmail.com.
 * Does NOT change approval_status in the DB.
 * Delete after testing.
 */
require '../config.php';
require '../amex/lib/fpdf.php';
require '../mailer.php';

$sub = db()->query("SELECT fs.*, u.name submitter_name, u.email submitter_email
    FROM form_submissions fs
    JOIN users u ON u.id = fs.user_id
    WHERE fs.approval_status = 'pending' AND fs.pdf_filename IS NOT NULL AND fs.pdf_filename != ''
    ORDER BY fs.id DESC LIMIT 1")->fetch();

if (!$sub) { die("No pending submissions with a PDF found.\n"); }

echo "Using submission #{$sub['id']} — {$sub['form_type']}\n";

$pdf_path  = STORAGE_PATH . $sub['pdf_filename'];
$stamp_tmp  = STORAGE_PATH . 'test_stamp_overlay.pdf';
$merged_tmp = STORAGE_PATH . 'test_merged.pdf';

if (!file_exists($pdf_path)) { die("PDF not found: $pdf_path\n"); }

// Draw stamp overlay
class FPDF_OvTest extends FPDF {
    function Header(){}
    function Footer(){}
    function Ellipse(float $x,float $y,float $rx,float $ry): void {
        $lx=(4/3)*(sqrt(2)-1)*$rx; $ly=(4/3)*(sqrt(2)-1)*$ry;
        $k=$this->k; $h=$this->h;
        $x2=$x*$k; $y2=($h-$y)*$k; $rx2=$rx*$k; $ry2=$ry*$k; $lx2=$lx*$k; $ly2=$ly*$k;
        $this->_out(sprintf(
            '%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c S',
            $x2+$rx2,$y2,$x2+$rx2,$y2+$ly2,$x2+$lx2,$y2+$ry2,$x2,$y2+$ry2,
            $x2-$lx2,$y2+$ry2,$x2-$rx2,$y2+$ly2,$x2-$rx2,$y2,
            $x2-$rx2,$y2-$ly2,$x2-$lx2,$y2-$ry2,$x2,$y2-$ry2,
            $x2+$lx2,$y2-$ry2,$x2+$rx2,$y2-$ly2,$x2+$rx2,$y2
        ));
    }
}

$ov = new FPDF_OvTest('P','mm','A4');
$ov->AddPage();
$ov->SetDrawColor(22,163,74); $ov->SetTextColor(22,163,74);
$cx=160; $cy=218; $rad=24;
$ov->SetLineWidth(1.4); $ov->Ellipse($cx,$cy,$rad,$rad);
$ov->SetLineWidth(0.5); $ov->Ellipse($cx,$cy,$rad-3.5,$rad-3.5);
$ov->SetFont('Arial','B',14);
$ov->SetXY($cx-$rad,$cy-6); $ov->Cell($rad*2,10,'APPROVED',0,0,'C');
$ov->SetFont('Arial','',7.5);
$ov->SetXY($cx-$rad,$cy+5); $ov->Cell($rad*2,5,'Fadi Shehadeh',0,1,'C');
$ov->SetXY($cx-$rad,$cy+9); $ov->Cell($rad*2,5,date('d M Y · H:i'),0,1,'C');
$ov->SetFont('Arial','B',6.5);
$ov->SetXY($cx-$rad,$cy+13); $ov->Cell($rad*2,5,'REF #'.$sub['id'],0,0,'C');
$ov->Output('F', $stamp_tmp);

// Ghostscript flatten
$gs_cmd = sprintf(
    'gs -dBATCH -dNOPAUSE -dNOSAFER -sDEVICE=pdfwrite -dFIXEDMEDIA -dPDFFitPage -sOutputFile=%s %s %s 2>&1',
    escapeshellarg($merged_tmp),
    escapeshellarg($pdf_path),
    escapeshellarg($stamp_tmp)
);
exec($gs_cmd, $gs_out, $gs_code);
@unlink($stamp_tmp);

echo "GS exit code: $gs_code\n";
if ($gs_code !== 0) { echo implode("\n", $gs_out); die("\nGhostscript failed.\n"); }

$type_labels = ['amex'=>'Credit Card Authorization','debit_note'=>'Debit Note','credit_note'=>'Credit Note','vendor_recon'=>'Vendor Payable Reconciliation','vendor_reg'=>'Vendor Registration','client_reg'=>'Client Registration'];
$label = $type_labels[$sub['form_type']] ?? ucwords(str_replace('_',' ',$sub['form_type']));

$subj = "[Approved ✓] Your $label — G2 Tools (TEST)";
$body_html = "
    <p>This is a <strong>test email</strong> to preview the approval notification.</p>
    <p>Your <strong>$label</strong> submission has been <strong style='color:#15803d'>approved</strong> by <strong>Fadi Shehadeh</strong>.</p>
    <div class='info-box'><strong>Reference</strong> #{$sub['id']}</div>
    <div class='info-box'><strong>Decision</strong> <span style='color:#15803d'>Approved</span></div>
    <div class='info-box'><strong>By</strong> Fadi Shehadeh</div>
    <div class='info-box'><strong>Date</strong> " . date('d M Y, H:i') . "</div>
    <a class='btn' href='https://g2tools.greydoha.com/admin/submission-view.php?id={$sub['id']}'>View Submission</a>
";
$html = mail_template($subj, $body_html);

$result = send_mail_with_attachment(
    ['email'=>'fshehadeh@gmail.com','name'=>'Fadi Shehadeh'],
    $subj, $html,
    $merged_tmp,
    'approved-' . $sub['form_type'] . '-' . $sub['id'] . '.pdf'
);

@unlink($merged_tmp);

echo $result ? "Email sent successfully.\n" : "Email failed.\n";
