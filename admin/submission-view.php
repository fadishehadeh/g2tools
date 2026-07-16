<?php
session_start();
require '../config.php';
require_login();

$id  = (int)($_GET['id'] ?? 0);
$db  = db();
$uid = current_user()['id'];

$row = $db->prepare("
    SELECT fs.*, u.name AS submitted_by, u.email AS submitted_email
    FROM form_submissions fs
    LEFT JOIN users u ON u.id = fs.user_id
    WHERE fs.id = ?
");
$row->execute([$id]);
$sub = $row->fetch();

if (!$sub) { http_response_code(404); echo 'Submission not found.'; exit; }

// Non-admins can only view their own submissions
if (!is_admin() && $sub['user_id'] != $uid) {
    http_response_code(403); echo 'Access denied.'; exit;
}

$fd = json_decode($sub['form_data'], true) ?? [];

// ── Handle approve/reject POST ────────────────────────────────────────────────
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_finance_admin()) {
    $act   = $_POST['act'] ?? '';
    $notes = trim(strip_tags($_POST['notes'] ?? ''));
    if (in_array($act, ['approved','rejected']) && $sub['approval_status'] === 'pending') {
        $db->prepare("UPDATE form_submissions SET approval_status=?, approved_by=?, approved_at=NOW(), approval_notes=? WHERE id=?")
           ->execute([$act, $uid, $notes ?: null, $id]);
        // Notify submitter
        $notify = $fd['rep_email'] ?? $fd['cs_email'] ?? $fd['fin_email'] ?? $sub['submitted_email'] ?? null;
        if ($notify && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
            require_once '../mailer.php';
            $status_label = $act === 'approved' ? 'Approved ✓' : 'Rejected ✗';
            $colour = $act === 'approved' ? '#15803d' : '#b91c1c';
            $subj = "[$status_label] Your submission — G2 Tools";
            $msg  = "Your submission (#{$id}) has been <strong style='color:{$colour}'>" . ucfirst($act) . "</strong>.";
            if ($notes) $msg .= "<br><br><strong>Note:</strong> " . htmlspecialchars($notes);
            $body = mail_template($subj, "<p>$msg</p>");
            send_mail(['email'=>$notify,'name'=>$fd['rep_name']??$fd['company_name']??''], $subj, $body);
        }
        header('Location: /admin/submission-view.php?id=' . $id . '&done=' . $act); exit;
    }
}

if (isset($_GET['done'])) {
    $flash = $_GET['done'] === 'approved'
        ? ['Submission approved.', '#f0fdf4', '#15803d']
        : ['Submission rejected.', '#fff5f5', '#b91c1c'];
    // Reload fresh status
    $row->execute([$id]);
    $sub = $row->fetch();
}

// ── Field label maps per form type ───────────────────────────────────────────
$field_groups = [];
switch ($sub['form_type']) {
    case 'amex':
        $field_groups = [
            'Request Details' => [
                'serial_number'=>'Serial Number','co_name'=>'Company','dept'=>'Department',
                'merchant'=>'Merchant / Payee','amount'=>'Amount','currency'=>'Currency',
                'purpose'=>'Purpose','date'=>'Date','card_last4'=>'Card (last 4)',
            ],
            'Submitted By' => ['name'=>'Name','position'=>'Position'],
        ];
        break;
    case 'accountability':
        $field_groups = [
            'Item Details' => [
                'item_name'=>'Item','quantity'=>'Quantity','unit_price'=>'Unit Price',
                'total'=>'Total','currency'=>'Currency','received_by'=>'Received By',
                'dept'=>'Department','date'=>'Date','notes'=>'Notes',
            ],
        ];
        break;
    case 'debit_note':
    case 'credit_note':
        $label = $sub['form_type'] === 'debit_note' ? 'Debit Note' : 'Credit Note';
        $field_groups = [
            "$label Details" => [
                'co_name'=>'Company','to_name'=>'To','to_address'=>'Address',
                'ref_number'=>'Reference No.','date'=>'Date','due_date'=>'Due Date',
                'amount'=>'Amount','currency'=>'Currency','description'=>'Description','notes'=>'Notes',
            ],
        ];
        break;
    case 'vendor_recon':
        $field_groups = [
            'Reconciliation Details' => [
                'co_name'=>'Company','vendor_name'=>'Vendor','vendor_account'=>'Account No.',
                'period_from'=>'Period From','period_to'=>'Period To',
                'our_balance'=>'Our Balance','vendor_balance'=>'Vendor Balance',
                'difference'=>'Difference','currency'=>'Currency','notes'=>'Notes',
            ],
        ];
        break;
    case 'vendor_reg':
        $field_groups = [
            'Company Information' => [
                'legal_name'=>'Legal Name','trade_name'=>'Trade Name','address'=>'Address',
                'city'=>'City','country'=>'Country','po_box'=>'P.O. Box',
                'website'=>'Website','industry'=>'Industry','year_established'=>'Year Est.',
            ],
            'Contact' => [
                'contact_name'=>'Contact Name','contact_position'=>'Position',
                'contact_email'=>'Email','contact_phone'=>'Phone',
            ],
            'Financial' => [
                'bank_name'=>'Bank','iban'=>'IBAN','swift'=>'SWIFT',
                'vat_number'=>'VAT No.','payment_terms'=>'Payment Terms',
            ],
        ];
        break;
    case 'client_reg':
        $field_groups = [
            'Client Information' => [
                'company_name'=>'Company','company_address'=>'Address','billing_address'=>'Billing Address',
                'website'=>'Website','industry'=>'Industry','year_trading'=>'Year of Trading',
                'trade_license_no'=>'Trade License No.','vat_number'=>'VAT No.',
                'brand_product'=>'Brand / Product','ceo_name'=>'CEO','cfo_name'=>'CFO',
            ],
            'Client Servicing Contact' => [
                'cs_name'=>'Name','cs_position'=>'Position','cs_email'=>'Email','cs_phone'=>'Phone',
            ],
            'Finance Contact' => [
                'fin_name'=>'Name','fin_position'=>'Position','fin_email'=>'Email','fin_phone'=>'Phone',
            ],
            'Financial & Credit' => [
                'revenue'=>'Revenue (Last FY)','net_profit'=>'Net Profit Before Tax',
                'audited_financials'=>'Audited Financials','credit_check_results'=>'Credit Check Results',
                'credit_limit'=>'Requested Credit Limit','credit_period_days'=>'Credit Period (days)',
                'related_party_checks'=>'Related-Party Checks',
            ],
            'Authorized Representative' => [
                'rep_name'=>'Name','rep_designation'=>'Designation','rep_date'=>'Date',
            ],
        ];
        break;
    default:
        // Fallback: show all keys
        $field_groups = ['Details' => array_combine(array_keys($fd), array_keys($fd))];
}

$type_labels = [
    'amex'=>'Credit Card Auth','accountability'=>'Accountability','debit_note'=>'Debit Note',
    'credit_note'=>'Credit Note','vendor_recon'=>'Vendor Recon','vendor_reg'=>'Vendor Registration',
    'client_reg'=>'Client Registration',
];
$form_label = $type_labels[$sub['form_type']] ?? ucwords(str_replace('_',' ',$sub['form_type']));
$needs_approval = in_array($sub['form_type'], ['amex','debit_note','credit_note','vendor_recon','vendor_reg','client_reg']);
$status = $sub['approval_status'] ?? 'pending';

$back = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($form_label) ?> #<?= $id ?> — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f4f5f7;color:#1a1a1a}
.page{padding:32px 36px;max-width:900px}
.back-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#888;text-decoration:none;margin-bottom:20px;font-weight:600}
.back-link:hover{color:#333}
.sub-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:28px;flex-wrap:wrap}
.sub-title{font-size:22px;font-weight:900;color:#1a1a1a}
.sub-meta{font-size:13px;color:#aaa;margin-top:4px}
.status-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 16px;border-radius:20px;font-size:13px;font-weight:700}
.status-pending{background:#fffbeb;color:#d97706;border:1.5px solid #fde68a}
.status-approved{background:#f0fdf4;color:#15803d;border:1.5px solid #bbf7d0}
.status-rejected{background:#fff5f5;color:#b91c1c;border:1.5px solid #fecaca}

.field-group{background:#fff;border:1.5px solid #e8eaee;border-radius:14px;overflow:hidden;margin-bottom:16px}
.group-title{padding:13px 20px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#aaa;background:#fafafa;border-bottom:1px solid #f0f0f0}
.field-row{display:grid;grid-template-columns:180px 1fr;padding:11px 20px;border-bottom:1px solid #f8f8f8;font-size:13px}
.field-row:last-child{border-bottom:none}
.field-label{color:#888;font-weight:500}
.field-value{color:#1a1a1a;font-weight:500;word-break:break-word}
.field-empty{color:#ccc}

.pdf-link{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:#f4f5f7;border-radius:8px;font-size:13px;font-weight:600;color:#555;text-decoration:none;margin-bottom:24px;border:1.5px solid #e8eaee}
.pdf-link:hover{background:#eee}

.approval-box{background:#fff;border:1.5px solid #e8eaee;border-radius:14px;padding:24px;margin-top:8px}
.approval-box h3{font-size:15px;font-weight:800;margin-bottom:16px;color:#1a1a1a}
.approval-info{font-size:13px;color:#888;margin-bottom:8px}
textarea{width:100%;border:1.5px solid #e8eaee;border-radius:8px;padding:10px 13px;font-size:13px;font-family:inherit;resize:vertical;min-height:72px;outline:none;margin-bottom:14px}
textarea:focus{border-color:#FF3D33}
.approval-btns{display:flex;gap:10px}
.btn-approve{padding:10px 24px;background:#15803d;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer}
.btn-approve:hover{background:#166534}
.btn-reject{padding:10px 24px;background:#fff;color:#b91c1c;border:1.5px solid #fca5a5;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer}
.btn-reject:hover{background:#fff5f5}
.flash{padding:12px 18px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:20px}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <span class="topbar-title"><?= htmlspecialchars($form_label) ?> — Submission #<?= $id ?></span>
</div>
<div class="page">

  <a class="back-link" href="<?= htmlspecialchars($back) ?>">← Back</a>

  <?php if ($flash): ?>
  <div class="flash" style="background:<?= $flash[1] ?>;color:<?= $flash[2] ?>;border:1.5px solid <?= $flash[2] ?>33"><?= $flash[0] ?></div>
  <?php endif; ?>

  <div class="sub-header">
    <div>
      <div class="sub-title"><?= htmlspecialchars($form_label) ?> <span style="font-weight:400;color:#aaa">#<?= $id ?></span></div>
      <div class="sub-meta">
        Submitted by <?= htmlspecialchars($sub['submitted_by'] ?? 'Public') ?>
        &nbsp;·&nbsp;
        <?= $sub['created_at'] ? (new DateTime($sub['created_at']))->format('d M Y, H:i') : '—' ?>
      </div>
    </div>
    <?php if ($needs_approval): ?>
    <div>
      <?php if ($status === 'approved'): ?>
        <span class="status-pill status-approved">✓ Approved</span>
      <?php elseif ($status === 'rejected'): ?>
        <span class="status-pill status-rejected">✗ Rejected</span>
      <?php else: ?>
        <span class="status-pill status-pending">⏳ Pending Approval</span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- PDF download -->
  <?php if (!empty($sub['pdf_filename'])): ?>
  <?php $pdf_url = '/storage/pdfs/' . rawurlencode($sub['pdf_filename']); ?>
  <a class="pdf-link" href="<?= $pdf_url ?>" target="_blank">📄 View / Download PDF</a>
  <?php endif; ?>

  <!-- Field groups -->
  <?php foreach ($field_groups as $group_name => $fields): ?>
  <?php
    // Only render group if it has at least one value
    $has_value = false;
    foreach ($fields as $key => $_) { if (!empty($fd[$key])) { $has_value = true; break; } }
    if (!$has_value) continue;
  ?>
  <div class="field-group">
    <div class="group-title"><?= htmlspecialchars($group_name) ?></div>
    <?php foreach ($fields as $key => $label): ?>
    <?php $val = trim($fd[$key] ?? ''); if ($val === '') continue; ?>
    <div class="field-row">
      <div class="field-label"><?= htmlspecialchars($label) ?></div>
      <div class="field-value"><?= nl2br(htmlspecialchars($val)) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <!-- Approval action (finance admin, pending only) -->
  <?php if (is_finance_admin() && $needs_approval && $status === 'pending'): ?>
  <div class="approval-box">
    <h3>Finance Decision</h3>
    <form method="POST">
      <div class="approval-info">Add an optional note — it will be emailed to the submitter.</div>
      <textarea name="notes" placeholder="Note (optional)…"></textarea>
      <div class="approval-btns">
        <button class="btn-approve" name="act" value="approved">✓ Approve</button>
        <button class="btn-reject"  name="act" value="rejected">✗ Reject</button>
      </div>
    </form>
  </div>
  <?php elseif ($needs_approval && $status !== 'pending'): ?>
  <div class="approval-box">
    <h3>Decision</h3>
    <?php
      $approver_id = $sub['approved_by'];
      $approver = null;
      if ($approver_id) {
          $a = $db->prepare("SELECT name FROM users WHERE id=?"); $a->execute([$approver_id]);
          $approver = $a->fetchColumn();
      }
    ?>
    <div class="approval-info">
      <?= $status === 'approved' ? '✓ Approved' : '✗ Rejected' ?>
      <?= $approver ? ' by ' . htmlspecialchars($approver) : '' ?>
      <?= $sub['approved_at'] ? ' on ' . (new DateTime($sub['approved_at']))->format('d M Y, H:i') : '' ?>
    </div>
    <?php if ($sub['approval_notes']): ?>
    <div style="margin-top:10px;padding:10px 14px;background:#f4f5f7;border-radius:8px;font-size:13px">
      <?= nl2br(htmlspecialchars($sub['approval_notes'])) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</div>
</body>
</html>
