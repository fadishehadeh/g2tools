<?php
session_start();
require '../config.php';
require_login();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Accountability for Company Property — G2</title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<link rel="stylesheet" href="/g2forms/form.css">
</head>
<body>

<?php require '../_sidebar.php'; ?>

<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/g2forms/">Forms</a>
  <span class="topbar-title">Accountability for Company Property</span>
</div>
<div class="form-page-wrap">
<div class="form-card">

  <div class="form-header">
    <div class="fh-logo">
      <img src="/g2forms/logo.png" style="height:38px;display:block">
    </div>
    <div class="fh-divider"></div>
    <div class="fh-text">
      <h1>Accountability for Company Property</h1>
      <p>Fill in all fields and submit to download the PDF</p>
    </div>
    <div class="fh-accent">📦</div>
  </div>
  <div class="form-accent-bar"></div>

  <div class="company-bar">
    <label>Company</label>
    <div class="company-select-wrap">
      <select id="companySelect">
        <option value="g2" data-logo="/g2forms/logo.png">G2 Group</option>
        <option value="pn" data-logo="/g2forms/PN.gif">PIN and Notch</option>
        <option value="grey" data-logo="/g2forms/grey.jpeg">Grey</option>
      </select>
      <img id="companyLogoPreview" src="/g2forms/logo.png" style="height:28px;object-fit:contain">
    </div>
  </div>

  <form method="POST" action="generate.php">
  <input type="hidden" name="company" id="companyHidden" value="g2">
  <div class="form-body">

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Employee Information</h2></div>
      <div class="field">
        <label class="field-label">Request By</label>
        <input type="text" name="request_by" placeholder="Full Name" required>
      </div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Department</label>
          <input type="text" name="department" placeholder="e.g. Creative">
        </div>
        <div class="field">
          <label class="field-label">Position</label>
          <input type="text" name="position" placeholder="e.g. Art Director">
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Item Details</h2></div>
      <div class="field">
        <label class="field-label">Item Name</label>
        <input type="text" name="item_name" placeholder="e.g. Macbook Pro" required>
      </div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Name / Serial Number</label>
          <input type="text" name="serial_number" placeholder="e.g. D0007H2YDC" required>
        </div>
        <div class="field">
          <label class="field-label">Estimated Life</label>
          <input type="text" name="estimated_life" placeholder="e.g. 5 years">
        </div>
      </div>
    </div>

    <div class="ack-box">
      <strong>Acknowledgement of Receipt of Company Property</strong>
      By signing this form, I agree to the following: (1) I am responsible for the equipment or property issued to me. (2) I will use it/them in the manner intended. (3) I will be responsible for any damage done (excluding normal wear and tear). (4) Upon separation from the Company, I will return the item(s) issued to me in proper working order (excluding normal wear &amp; tear). (5) I will replace any items issued to me that are damaged or lost due to my negligence at my expense.
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Acknowledgement</h2></div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Received By</label>
          <input type="text" name="received_by" placeholder="Full Name" required>
        </div>
        <div class="field">
          <label class="field-label">Date</label>
          <input type="text" name="received_date" placeholder="e.g. 9-Jun-26" value="<?php echo date('j-M-y'); ?>">
        </div>
      </div>
    </div>

  </div>
  <div class="form-footer">
    <button type="submit" class="submit-btn">⬇ &nbsp;Generate &amp; Download PDF</button>
    <p class="submit-note">The PDF will download automatically after submission.</p>
  </div>
  </form>

</div><!-- .form-card -->
</div><!-- .form-page-wrap -->
</div><!-- .main-content -->
<style>
.company-bar{background:#f8f9fb;border-bottom:1px solid #e8eaee;padding:12px 28px;display:flex;align-items:center;gap:14px}
.company-bar label{font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#999;white-space:nowrap}
.company-select-wrap{display:flex;align-items:center;gap:12px}
.company-select-wrap select{padding:8px 14px;border:1.5px solid #e8eaee;border-radius:8px;font-size:13px;font-weight:600;color:#1a1a1a;background:#fff;font-family:inherit;cursor:pointer;min-width:180px}
.company-select-wrap select:focus{outline:none;border-color:#FF3D33}
</style>
<script>
const sel=document.getElementById('companySelect'),hid=document.getElementById('companyHidden'),prev=document.getElementById('companyLogoPreview'),hdr=document.getElementById('headerLogo');
function upd(){const o=sel.options[sel.selectedIndex];hid.value=sel.value;prev.src=o.dataset.logo;if(hdr)hdr.src=o.dataset.logo;}
sel.addEventListener('change',upd);upd();
</script>
</body>
</html>
