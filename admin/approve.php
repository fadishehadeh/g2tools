<?php
session_start();
require '../config.php';
require_login();
if (!is_finance_admin()) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }

header('Content-Type: application/json');

$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$notes  = trim(strip_tags($_POST['notes'] ?? ''));

if (!$id || !in_array($action, ['approved','rejected'])) {
    echo json_encode(['error' => 'Invalid request']); exit;
}

$row = db()->prepare("SELECT fs.*, u.name submitter_name, u.email submitter_email
    FROM form_submissions fs
    JOIN users u ON u.id = fs.user_id
    WHERE fs.id=?");
$row->execute([$id]);
$sub = $row->fetch();

if (!$sub) { echo json_encode(['error' => 'Submission not found']); exit; }
if ($sub['approval_status'] !== 'pending') { echo json_encode(['error' => 'Already actioned']); exit; }

$approver    = current_user();
$approver_id = $approver['id'];
$approver_nm = $approver['name'];

// ── 1. Update DB ──────────────────────────────────────────────────────────────
db()->prepare("UPDATE form_submissions SET approval_status=?, approved_by=?, approved_at=NOW(), approval_notes=? WHERE id=?")
   ->execute([$action, $approver_id, $notes ?: null, $id]);

// ── 2. Stamp + flatten PDF ────────────────────────────────────────────────────
$pdf_file = $sub['pdf_filename'] ?? '';
$pdf_path = STORAGE_PATH . $pdf_file;
$stamped  = false;

if ($pdf_file && file_exists($pdf_path)) {
    require_once '../amex/lib/fpdf.php';

    // We re-open the existing PDF as an image — FPDF can't parse existing PDFs,
    // so we draw an overlay page and merge via Ghostscript.
    // Strategy: write a single-page "stamp overlay" PDF, then use gs to stamp it
    // onto every page of the original, then flatten.

    $stamp_tmp = STORAGE_PATH . 'stamp_overlay_' . $id . '.pdf';
    $merged_tmp = STORAGE_PATH . 'merged_' . $id . '.pdf';

    // Build the overlay PDF (A4, transparent background, stamp only)
    class FPDF_Overlay extends FPDF {
        function Header(){}
        function Footer(){}
        function Ellipse(float $x,float $y,float $rx,float $ry,string $style='S'): void {
            $lx=(4/3)*(sqrt(2)-1)*$rx; $ly=(4/3)*(sqrt(2)-1)*$ry;
            $k=$this->k; $h=$this->h;
            $x2=$x*$k; $y2=($h-$y)*$k; $rx2=$rx*$k; $ry2=$ry*$k; $lx2=$lx*$k; $ly2=$ly*$k;
            $op=$style==='F'?'f':($style==='FD'||$style==='DF'?'b':'S');
            $this->_out(sprintf(
                '%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %s',
                $x2+$rx2,$y2, $x2+$rx2,$y2+$ly2,$x2+$lx2,$y2+$ry2,$x2,$y2+$ry2,
                $x2-$lx2,$y2+$ry2,$x2-$rx2,$y2+$ly2,$x2-$rx2,$y2,
                $x2-$rx2,$y2-$ly2,$x2-$lx2,$y2-$ry2,$x2,$y2-$ry2,
                $x2+$lx2,$y2-$ry2,$x2+$rx2,$y2-$ly2,$x2+$rx2,$y2,$op
            ));
        }
    }

    $approved = ($action === 'approved');
    [$r,$g,$b] = $approved ? [22,163,74] : [220,38,38];

    $ov = new FPDF_Overlay('P','mm','A4');
    $ov->AddPage();
    $ov->SetDrawColor($r,$g,$b);
    $ov->SetTextColor($r,$g,$b);

    $cx=160; $cy=218; $rad=24;

    $ov->SetLineWidth(1.4); $ov->Ellipse($cx,$cy,$rad,$rad);
    $ov->SetLineWidth(0.5); $ov->Ellipse($cx,$cy,$rad-3.5,$rad-3.5);

    $ov->SetFont('Arial','B',14);
    $ov->SetXY($cx-$rad,$cy-6); $ov->Cell($rad*2,10,$approved?'APPROVED':'REJECTED',0,0,'C');

    $ov->SetFont('Arial','',7.5);
    $ov->SetXY($cx-$rad,$cy+5);  $ov->Cell($rad*2,5,$approver_nm,0,1,'C');
    $ov->SetXY($cx-$rad,$cy+9);  $ov->Cell($rad*2,5,date('d M Y · H:i'),0,1,'C');

    $ov->SetFont('Arial','B',6.5);
    $ov->SetXY($cx-$rad,$cy+13); $ov->Cell($rad*2,5,'REF #'.$id,0,0,'C');

    $ov->Output('F', $stamp_tmp);

    // Use Ghostscript to stamp overlay on top of original and flatten
    $gs_cmd = sprintf(
        'gs -dBATCH -dNOPAUSE -dNOSAFER -sDEVICE=pdfwrite'
        . ' -dFIXEDMEDIA -dPDFFitPage'
        . ' -sOutputFile=%s %s %s 2>&1',
        escapeshellarg($merged_tmp),
        escapeshellarg($pdf_path),
        escapeshellarg($stamp_tmp)
    );
    exec($gs_cmd, $gs_out, $gs_code);

    if ($gs_code === 0 && file_exists($merged_tmp) && filesize($merged_tmp) > 0) {
        rename($merged_tmp, $pdf_path); // replace original
        @unlink($stamp_tmp);
        $stamped = true;
    } else {
        // gs failed — clean up temps but keep original PDF intact
        @unlink($stamp_tmp);
        @unlink($merged_tmp);
    }
}

// ── 3. Email submitter ────────────────────────────────────────────────────────
require_once '../mailer.php';

$type_labels = [
    'amex'        => 'Credit Card Authorization',
    'debit_note'  => 'Debit Note',
    'credit_note' => 'Credit Note',
    'vendor_recon'=> 'Vendor Payable Reconciliation',
    'vendor_reg'  => 'Vendor Registration',
    'client_reg'  => 'Client Registration',
    'petty_cash'  => 'Petty Cash Entry',
];
$label  = $type_labels[$sub['form_type']] ?? ucwords(str_replace('_',' ',$sub['form_type']));
$colour = $approved ? '#15803d' : '#b91c1c';
$word   = $approved ? 'Approved ✓' : 'Rejected ✗';
$subj   = "[$word] Your $label — G2 Tools";

$body_html = "
    <p>Your <strong>" . htmlspecialchars($label) . "</strong> submission has been
    <strong style='color:{$colour}'>" . ($approved ? 'approved' : 'rejected') . "</strong>
    by <strong>" . htmlspecialchars($approver_nm) . "</strong>.</p>
    <div class='info-box'><strong>Reference</strong> #$id</div>
    <div class='info-box'><strong>Decision</strong> <span style='color:{$colour}'>" . ucfirst($action) . "</span></div>
    <div class='info-box'><strong>By</strong> " . htmlspecialchars($approver_nm) . "</div>
    <div class='info-box'><strong>Date</strong> " . date('d M Y, H:i') . "</div>
";
if ($notes) {
    $body_html .= "<div class='info-box'><strong>Note</strong> " . htmlspecialchars($notes) . "</div>";
}
$body_html .= "<a class='btn' href='https://g2tools.greydoha.com/admin/submission-view.php?id=$id'>View Submission</a>";

$html = mail_template($subj, $body_html);

$to = ['email' => $sub['submitter_email'], 'name' => $sub['submitter_name']];

if ($stamped && file_exists($pdf_path)) {
    send_mail_with_attachment($to, $subj, $html, $pdf_path, $pdf_file);
} else {
    send_mail($to, $subj, $html);
}

echo json_encode(['ok' => true, 'status' => $action]);
