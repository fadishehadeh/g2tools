<?php
// PUBLIC — no login required
if (session_status() === PHP_SESSION_NONE) session_start();
require '../config.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Credit Check & Registration — Grey</title>
<link rel="stylesheet" href="/form.css">
<style>
  body { background:#f6f7f9; min-height:100vh; display:flex; flex-direction:column; align-items:center; padding:40px 16px 80px; }
  .form-card { max-width:720px; width:100%; }
  .upload-area { border:2px dashed #e8eaee; border-radius:8px; padding:14px 16px; font-size:13px; color:#aaa; cursor:pointer; transition:border-color .18s; }
  .upload-area:hover { border-color:#FF3D33; color:#FF3D33; }
  .upload-area input[type=file] { display:none; }
  .file-chosen { margin-top:6px; font-size:12px; color:#555; }
  .radio-group { display:flex; flex-direction:column; gap:8px; }
  .radio-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border:1.5px solid #e8eaee; border-radius:8px; cursor:pointer; font-size:13px; color:#555; transition:border-color .15s; }
  .radio-item input[type=radio] { accent-color:#FF3D33; width:16px; height:16px; cursor:pointer; }
  .radio-item:has(input:checked) { border-color:#FF3D33; background:#fff8f8; }
  .notice { background:#fffbeb; border:1.5px solid #fde68a; border-left:4px solid #f59e0b; border-radius:8px; padding:12px 16px; font-size:12px; color:#92400e; line-height:1.6; }
  .field-note { font-size:11px; color:#aaa; margin-top:3px; }
</style>
</head>
<body>
<div class="form-card">
  <div class="form-header">
    <div class="fh-logo"><img src="/grey.jpeg" style="height:38px;display:block;object-fit:contain;max-width:140px"></div>
    <div class="fh-divider"></div>
    <div class="fh-text">
      <h1>New Client Set-up Form</h1>
      <p>Complete all sections and submit. Finance will review your registration.</p>
    </div>
  </div>
  <div class="form-accent-bar"></div>

  <form method="POST" action="submit.php" enctype="multipart/form-data" id="clientForm">
  <div class="form-body">

    <!-- ── Section 1: Client Information ── -->
    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Section 1 — Client Information</h2></div>

      <div class="field">
        <label class="field-label">Company Name <span style="color:#FF3D33">*</span></label>
        <input type="text" name="company_name" required placeholder="e.g. Ooredoo Qatar Q.S.C.">
      </div>

      <div class="field">
        <label class="field-label">Company Address <span style="color:#FF3D33">*</span></label>
        <textarea name="company_address" rows="2" required placeholder="Full registered address"></textarea>
      </div>

      <div class="field">
        <label class="field-label">Billing Address <span style="font-size:11px;color:#aaa;font-weight:400">(if different from above)</span></label>
        <textarea name="billing_address" rows="2" placeholder="Leave blank if same as above"></textarea>
      </div>

      <div class="grid2">
        <div class="field">
          <label class="field-label">Company Website</label>
          <input type="url" name="website" placeholder="https://example.com">
        </div>
        <div class="field">
          <label class="field-label">Industry <span style="color:#FF3D33">*</span></label>
          <input type="text" name="industry" required placeholder="e.g. Telecommunications">
        </div>
      </div>

      <div class="grid2">
        <div class="field">
          <label class="field-label">Year of Trading <span style="color:#FF3D33">*</span></label>
          <input type="text" name="year_trading" required placeholder="e.g. 2008">
        </div>
        <div class="field">
          <label class="field-label">VAT Number</label>
          <input type="text" name="vat_number" placeholder="e.g. 300412834700003">
        </div>
      </div>

      <div class="grid2">
        <div class="field">
          <label class="field-label">Trade License / Registration No. <span style="color:#FF3D33">*</span></label>
          <input type="text" name="trade_license_no" required placeholder="e.g. CR-2019-04182">
        </div>
        <div class="field">
          <label class="field-label">Brand / Product</label>
          <input type="text" name="brand_product" placeholder="e.g. Ooredoo Mobile">
        </div>
      </div>

      <div class="grid2">
        <div class="field">
          <label class="field-label">Company CEO <span style="color:#FF3D33">*</span></label>
          <input type="text" name="ceo_name" required placeholder="Full name">
        </div>
        <div class="field">
          <label class="field-label">Company CFO</label>
          <input type="text" name="cfo_name" placeholder="Full name">
        </div>
      </div>

      <div class="field">
        <label class="field-label">Trade License Upload <span style="color:#FF3D33">*</span></label>
        <div class="upload-area" onclick="this.querySelector('input').click()">
          <input type="file" name="trade_license_file" accept=".pdf,.jpg,.jpeg,.png" required onchange="showFile(this)">
          <div>📎 Click to upload Trade License / Registration Certificate</div>
          <div class="field-note">PDF, JPG, or PNG · max 5MB</div>
          <div class="file-chosen" id="file_trade_license"></div>
        </div>
      </div>
    </div>

    <!-- ── Section 2: Client Servicing Contact ── -->
    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Section 2 — Client Servicing Contact</h2></div>

      <div class="grid2">
        <div class="field">
          <label class="field-label">Contact Person Name <span style="color:#FF3D33">*</span></label>
          <input type="text" name="cs_name" required placeholder="Full name">
        </div>
        <div class="field">
          <label class="field-label">Position / Title <span style="color:#FF3D33">*</span></label>
          <input type="text" name="cs_position" required placeholder="e.g. Marketing Manager">
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Email Address <span style="color:#FF3D33">*</span></label>
          <input type="email" name="cs_email" required placeholder="name@company.com">
        </div>
        <div class="field">
          <label class="field-label">Contact Number <span style="color:#FF3D33">*</span></label>
          <input type="tel" name="cs_phone" required placeholder="+974 XXXX XXXX">
        </div>
      </div>
    </div>

    <!-- ── Section 3: Finance Contact ── -->
    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Section 3 — Finance Department Contact</h2></div>

      <div class="grid2">
        <div class="field">
          <label class="field-label">Contact Person Name <span style="color:#FF3D33">*</span></label>
          <input type="text" name="fin_name" required placeholder="Full name">
        </div>
        <div class="field">
          <label class="field-label">Position / Title <span style="color:#FF3D33">*</span></label>
          <input type="text" name="fin_position" required placeholder="e.g. Finance Manager">
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Email Address <span style="color:#FF3D33">*</span></label>
          <input type="email" name="fin_email" required placeholder="finance@company.com">
        </div>
        <div class="field">
          <label class="field-label">Contact Number <span style="color:#FF3D33">*</span></label>
          <input type="tel" name="fin_phone" required placeholder="+974 XXXX XXXX">
        </div>
      </div>
    </div>

    <!-- ── Section 4: Financial Results & Credit Check ── -->
    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Section 4 — Financial Results &amp; Credit Check</h2></div>

      <div class="grid2">
        <div class="field">
          <label class="field-label">Revenue — Last Financial Year (QAR) <span style="color:#FF3D33">*</span></label>
          <input type="text" name="revenue" required placeholder="e.g. 5,000,000">
        </div>
        <div class="field">
          <label class="field-label">Net Profit Before Tax (QAR) <span style="color:#FF3D33">*</span></label>
          <input type="text" name="net_profit" required placeholder="e.g. 1,200,000">
        </div>
      </div>

      <div class="field">
        <label class="field-label">Audited Financial Statement Available? <span style="color:#FF3D33">*</span></label>
        <div class="radio-group" style="flex-direction:row;gap:12px">
          <label class="radio-item"><input type="radio" name="audited_financials" value="Yes" required> Yes</label>
          <label class="radio-item"><input type="radio" name="audited_financials" value="No"> No</label>
        </div>
      </div>

      <div class="field">
        <label class="field-label">Results of Credit Check <span style="color:#FF3D33">*</span></label>
        <textarea name="credit_check_results" rows="3" required placeholder="Describe the results of the credit check performed"></textarea>
      </div>

      <div class="grid2">
        <div class="field">
          <label class="field-label">Requested Credit Limit (QAR) <span style="color:#FF3D33">*</span></label>
          <input type="text" name="credit_limit" required placeholder="e.g. 250,000">
        </div>
        <div class="field">
          <label class="field-label">Credit Period (Days) <span style="color:#FF3D33">*</span></label>
          <input type="number" name="credit_period_days" required min="0" max="365" placeholder="e.g. 30">
        </div>
      </div>

      <div class="field">
        <label class="field-label">Related-Party Checks <span style="color:#FF3D33">*</span></label>
        <div class="notice" style="margin-bottom:10px">Indicate all related-party checks performed before submitting.</div>
        <textarea name="related_party_checks" rows="3" required placeholder="e.g. Checked against shareholder registry — no related-party relationship identified."></textarea>
      </div>
    </div>

    <!-- ── Section 5: Declaration ── -->
    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Section 5 — Authorized Representative</h2></div>

      <div class="grid2">
        <div class="field">
          <label class="field-label">Full Name <span style="color:#FF3D33">*</span></label>
          <input type="text" name="rep_name" required placeholder="Signatory's full name">
        </div>
        <div class="field">
          <label class="field-label">Designation <span style="color:#FF3D33">*</span></label>
          <input type="text" name="rep_designation" required placeholder="e.g. Managing Director">
        </div>
      </div>
      <div class="field" style="max-width:280px">
        <label class="field-label">Date <span style="color:#FF3D33">*</span></label>
        <input type="date" name="rep_date" required value="<?= date('Y-m-d') ?>">
      </div>

      <div class="notice">
        By submitting this form, I confirm that all information provided is true, accurate, and complete to the best of my knowledge.
      </div>
    </div>

  </div><!-- /form-body -->

  <div class="form-footer">
    <button type="submit" class="submit-btn" id="submitBtn">Submit Registration →</button>
  </div>
  </form>
</div>

<script>
function showFile(input) {
  const id = 'file_' + input.name.replace('[]','');
  const el = document.getElementById(id);
  if (el && input.files.length) {
    el.textContent = '✓ ' + input.files[0].name + ' (' + (input.files[0].size/1024).toFixed(1) + ' KB)';
    el.style.color = '#15803d';
  }
}
document.getElementById('clientForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.textContent = 'Submitting…';
  btn.disabled = true;
});
</script>
</body>
</html>
