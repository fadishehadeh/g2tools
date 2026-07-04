<?php
// PUBLIC — no login required
if (session_status() === PHP_SESSION_NONE) session_start();
require '../config.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Registration — Grey Doha</title>
<link rel="stylesheet" href="/g2forms/form.css">
<style>
  body { background: #f6f7f9; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding: 40px 16px 80px; }
  .form-card { max-width: 700px; width: 100%; }
  .upload-area { border: 2px dashed #e8eaee; border-radius: 8px; padding: 14px 16px;
                 font-size: 13px; color: #aaa; cursor: pointer; transition: border-color .18s; }
  .upload-area:hover { border-color: #FF3D33; color: #FF3D33; }
  .upload-area input[type=file] { display: none; }
  .file-list { margin-top: 8px; font-size: 12px; color: #555; }
  .check-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .check-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #555;
                padding: 9px 12px; border: 1.5px solid #e8eaee; border-radius: 8px; cursor: pointer; }
  .check-item input[type=checkbox] { width:16px; height:16px; accent-color:#FF3D33; cursor:pointer; flex-shrink:0; }
  .radio-group { display:flex; flex-direction:column; gap:8px; }
  .radio-item { display:flex; align-items:center; gap:10px; padding:10px 14px;
                border:1.5px solid #e8eaee; border-radius:8px; cursor:pointer; transition:border-color .15s; font-size:13px; color:#555; }
  .radio-item input[type=radio] { accent-color:#FF3D33; width:16px; height:16px; cursor:pointer; }
  .radio-item:has(input:checked) { border-color:#FF3D33; background:#fff8f8; }
  .notice { background:#fffbeb; border:1.5px solid #fde68a; border-left:4px solid #f59e0b;
            border-radius:8px; padding:12px 16px; font-size:12px; color:#92400e; line-height:1.6; }
  .related-section { display:none; }
</style>
</head>
<body>
<div class="form-card">
  <div class="form-header">
    <div class="fh-logo"><img src="/g2forms/grey.jpeg" style="height:38px;display:block;object-fit:contain;max-width:140px"></div>
    <div class="fh-divider"></div>
    <div class="fh-text">
      <h1>Vendor's Registration</h1>
      <p>Complete all sections and submit. Finance will review your registration.</p>
    </div>
  </div>
  <div class="form-accent-bar"></div>

  <form method="POST" action="submit.php" enctype="multipart/form-data">
  <div class="form-body">

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Section 1 — Vendor Details</h2></div>
      <div class="field">
        <label class="field-label">Legal Name <span style="color:#FF3D33">*</span></label>
        <input type="text" name="legal_name" placeholder="Full legal company name" required>
      </div>
      <div class="field">
        <label class="field-label">AKA / DBA Names <span style="font-size:11px;color:#aaa">(if different)</span></label>
        <input type="text" name="aka_name" placeholder="Trading name if different from legal name">
      </div>
      <div class="field">
        <label class="field-label">Address <span style="color:#FF3D33">*</span></label>
        <div class="grid2" style="margin-bottom:8px">
          <input type="text" name="zip_code" placeholder="Zip / Postal Code">
          <input type="text" name="city" placeholder="City" required>
        </div>
        <div class="grid2">
          <input type="text" name="state" placeholder="State / Province">
          <input type="text" name="country" placeholder="Country" required>
        </div>
      </div>
      <div class="grid2">
        <div class="field"><label class="field-label">Company License No.</label><input type="text" name="company_license" placeholder="License number"></div>
        <div class="field"><label class="field-label">Tax No.</label><input type="text" name="tax_no" placeholder="Tax number"></div>
      </div>
      <div class="grid2">
        <div class="field"><label class="field-label">CO. Reg. No.</label><input type="text" name="co_reg_no" placeholder="Company registration number"></div>
        <div class="field"><label class="field-label">ICV Score</label><input type="text" name="icv_score" placeholder="ICV score if applicable"></div>
      </div>
      <div class="field">
        <label class="field-label">Estimated Annual Spend <span style="color:#FF3D33">*</span></label>
        <div class="radio-group">
          <label class="radio-item"><input type="radio" name="annual_spend" value="UP TO US$25K" required> Up to US$25K</label>
          <label class="radio-item"><input type="radio" name="annual_spend" value="> US$25K AND UP TO US$100K"> &gt; US$25K and up to US$100K</label>
          <label class="radio-item"><input type="radio" name="annual_spend" value="> US$100K"> &gt; US$100K</label>
        </div>
      </div>
      <div class="field">
        <label class="field-label">Does a related party relationship exist with Grey? <span style="color:#FF3D33">*</span></label>
        <div class="radio-group" style="flex-direction:row;gap:12px">
          <label class="radio-item" style="flex:1"><input type="radio" name="related_party" value="Yes" onchange="toggleRelated(this)" required> Yes</label>
          <label class="radio-item" style="flex:1"><input type="radio" name="related_party" value="No" onchange="toggleRelated(this)"> No</label>
        </div>
      </div>
      <div class="field">
        <label class="field-label">How did you learn about Grey?</label>
        <div class="check-grid">
          <label class="check-item"><input type="checkbox" name="source[]" value="Awarded tender"> Awarded Tender</label>
          <label class="check-item"><input type="checkbox" name="source[]" value="Website"> Website</label>
          <label class="check-item"><input type="checkbox" name="source[]" value="Co-worker Reference"> Co-worker Reference</label>
          <label class="check-item"><input type="checkbox" name="source[]" value="Other"> Other</label>
          <label class="check-item" style="grid-column:span 2"><input type="checkbox" name="source[]" value="Client Reference"> Client Reference &nbsp;<input type="text" name="client_ref_name" placeholder="Client name" style="flex:1;padding:4px 8px;border:1px solid #e8eaee;border-radius:6px;font-size:12px"></label>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Section 2 — Bank Details</h2></div>
      <p style="font-size:12px;color:#aaa;margin-bottom:14px;font-style:italic">Please also attach Bank Mandate document below.</p>
      <div class="field"><label class="field-label">Account Name <span style="color:#FF3D33">*</span></label><input type="text" name="bank_account_name" placeholder="Name on bank account" required></div>
      <div class="field"><label class="field-label">Company Address</label><input type="text" name="bank_company_address" placeholder="Address as on bank records"></div>
      <div class="grid2">
        <div class="field"><label class="field-label">Account Number <span style="color:#FF3D33">*</span></label><input type="text" name="account_number" placeholder="Account number" required></div>
        <div class="field"><label class="field-label">Currency</label><input type="text" name="bank_currency" placeholder="e.g. QAR, USD"></div>
      </div>
      <div class="field"><label class="field-label">Bank Name <span style="color:#FF3D33">*</span></label><input type="text" name="bank_name" placeholder="Full bank name" required></div>
      <div class="field"><label class="field-label">Bank Address</label><input type="text" name="bank_address" placeholder="Bank branch address"></div>
      <div class="grid2">
        <div class="field"><label class="field-label">SWIFT Code</label><input type="text" name="swift_code" placeholder="SWIFT / BIC code"></div>
        <div class="field"><label class="field-label">IBAN</label><input type="text" name="iban" placeholder="IBAN number"></div>
      </div>
    </div>

    <div class="section related-section" id="relatedSection">
      <div class="section-header"><div class="s-dot"></div><h2>Section 3 — Related Party Disclosure</h2></div>
      <div class="ack-box">
        <strong>Related Party Disclosure</strong>
        I hereby confirm that I have a related party relationship with Grey &amp; any of its affiliated companies. A related party is defined as any person or entity bearing a relationship or having a financial or other benefit derived from that relationship such as: members of a family; grantor or fiduciary of any trust; two corporations which are members of the same controlled group or individuals; corporations and partnerships with more than 50% direct or indirect ownership of the stock, capital or profits in these entities.
      </div>
      <div class="field"><label class="field-label">Related Party Name / Description</label><textarea name="related_party_desc" placeholder="Describe the nature of the related party relationship…"></textarea></div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Section 4 — Documents &amp; Attachments</h2></div>
      <p style="font-size:12px;color:#aaa;margin-bottom:14px">Upload the following documents. Accepted formats: PDF, JPG, PNG (max 5MB each).</p>
      <div style="display:flex;flex-direction:column;gap:12px">
        <?php
        $docs = [
          'doc_company_reg'  => 'Company Registration',
          'doc_company_prof' => 'Company Profile',
          'doc_auth_id'      => 'Valid ID of Authorized Signatory',
          'doc_bank_mandate' => 'Bank Mandate',
          'doc_trade_license'=> 'Company Trade License',
          'doc_icv'          => 'ICV Certificate',
        ];
        foreach($docs as $name => $label): ?>
        <div style="display:flex;align-items:center;gap:12px">
          <label style="flex:0 0 260px;font-size:13px;font-weight:600;color:#555"><?= $label ?></label>
          <label class="upload-area" style="flex:1">
            <input type="file" name="<?= $name ?>" accept=".pdf,.jpg,.jpeg,.png" onchange="showFile(this,'lbl_<?= $name ?>')">
            <span id="lbl_<?= $name ?>">Click to upload or drag &amp; drop</span>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Authorized Representative</h2></div>
      <div class="grid2">
        <div class="field"><label class="field-label">Printed Name <span style="color:#FF3D33">*</span></label><input type="text" name="rep_name" placeholder="Full name" required></div>
        <div class="field"><label class="field-label">Date <span style="color:#FF3D33">*</span></label><input type="text" name="rep_date" value="<?= date('j-M-Y') ?>" required></div>
      </div>
      <div class="field" style="max-width:300px"><label class="field-label">Designation</label><input type="text" name="rep_designation" placeholder="e.g. Finance Manager"></div>
      <div class="field" style="max-width:300px"><label class="field-label">Email Address <span style="color:#FF3D33">*</span></label><input type="email" name="rep_email" placeholder="your@email.com" required></div>
      <div class="notice" style="margin-top:16px">
        <strong style="display:block;font-weight:700;color:#b45309;margin-bottom:4px">Qatar Tax Notice</strong>
        As per Qatar tax Law, all FOREIGN VENDORS providing services are entitled to 5% withholding tax.
      </div>
    </div>

  </div>
  <div class="form-footer">
    <button type="submit" class="submit-btn">Submit Registration</button>
    <p class="submit-note">Your registration will be sent to the finance team for review.</p>
  </div>
  </form>
</div>

<script>
function toggleRelated(radio) {
  document.getElementById('relatedSection').style.display = (radio.value === 'Yes') ? 'block' : 'none';
}
function showFile(input, lblId) {
  const lbl = document.getElementById(lblId);
  lbl.textContent = input.files.length ? input.files[0].name : 'Click to upload or drag & drop';
}
</script>
</body>
</html>
