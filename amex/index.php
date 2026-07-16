<?php
session_start();
require '../config.php';
require_login();
require_can('finance_cc');

$companies = [
    'g2'  => ['name' => 'G2 Group',       'logo' => '/g2forms/logo.png'],
    'pn'  => ['name' => 'PIN and Notch',  'logo' => '/g2forms/PN.gif'],
    'grey'=> ['name' => 'Grey',            'logo' => '/g2forms/grey.jpeg'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Credit Card Authorization — G2</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
  .company-bar {
    background: #f8f9fb;
    border-bottom: 1px solid #e8eaee;
    padding: 14px 36px;
    display: flex;
    align-items: center;
    gap: 16px;
  }
  .company-bar label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #999;
    white-space: nowrap;
  }
  .company-select-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
  }
  #companySelect {
    padding: 9px 36px 9px 14px;
    border: 1.5px solid #e8eaee;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
    background: #fff;
    font-family: inherit;
    cursor: pointer;
    transition: border-color .2s;
    min-width: 200px;
  }
  #companySelect:focus { outline: none; border-color: #FF3D33; }
  #companyLogoPreview {
    height: 32px;
    object-fit: contain;
    opacity: 1;
    transition: opacity .2s;
  }
</style>
</head>
<body>

<?php require '../_sidebar.php'; ?>

<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/">Forms</a>
  <span class="topbar-title">Credit Card Authorization</span>
</div>
<div class="form-page-wrap">
<div class="form-card">

  <div class="form-header">
    <div class="fh-logo">
      <img id="headerLogo" src="/logo.png" style="height:38px;display:block;object-fit:contain;max-width:160px">
    </div>
    <div class="fh-divider"></div>
    <div class="fh-text">
      <h1>Credit Card Authorization Form</h1>
      <p>Fill in all fields and submit to download the PDF</p>
    </div>
    <div class="fh-accent">💳</div>
  </div>
  <div class="form-accent-bar"></div>

  <!-- Company selector -->
  <div class="company-bar">
    <label>Company</label>
    <div class="company-select-wrap">
      <select id="companySelect" name="company_preview">
        <?php foreach ($companies as $key => $co): ?>
          <option value="<?= $key ?>" data-logo="<?= $co['logo'] ?>"><?= htmlspecialchars($co['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <img id="companyLogoPreview" src="/logo.png" alt="company logo">
    </div>
  </div>

  <form method="POST" action="generate.php">
  <input type="hidden" name="company" id="companyHidden" value="g2">
  <div class="form-body">

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Credit Card Information</h2></div>

      <input type="hidden" name="card_type" value="AMEX">
      <input type="hidden" name="cardholder_name" value="KRIKOR KHAJIKIAN Grey World Wide LLC">
      <input type="hidden" name="card_last4" value="5007">

      <div class="field">
        <label class="field-label">Name of Merchant</label>
        <input type="text" name="merchant" placeholder="e.g. Claude" required>
      </div>
      <div class="field">
        <label class="field-label">Serial / Reference Number</label>
        <input type="text" id="serialNumber" placeholder="Auto-assigned on submit" readonly style="background:#fafafa;color:#aaa;cursor:default;">
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Purchase Order</h2></div>
      <div class="field">
        <label class="field-label">PO Number <span style="color:#FF3D33">*</span></label>
        <input type="text" name="po_number" placeholder="e.g. PO-2026-0042" required>
      </div>
      <div style="background:#fff8f2;border:1.5px solid #fed7aa;border-left:4px solid #f97316;border-radius:8px;padding:11px 14px;font-size:12px;color:#92400e;line-height:1.6;margin-top:4px;">
        <strong style="display:block;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#c2410c;margin-bottom:4px;">Required before proceeding</strong>
        A Purchase Order must be raised and approved before submitting this AMEX authorization. The PO number is mandatory.
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Billable</h2></div>
      <div class="billable-box">
        <div class="billable-row">
          <input type="radio" name="billable" value="YES" checked>
          <div class="bill-content">
            <div class="bill-label">YES (Vendor)</div>
            <div class="bill-sub">A copy of approval and PO/BO to be attached</div>
            <input type="text" name="client_name" placeholder="Name of Client">
          </div>
        </div>
        <div class="billable-row">
          <input type="radio" name="billable" value="NO">
          <div class="bill-content">
            <div class="bill-label">NO</div>
            <input type="text" name="nature_of_expense" placeholder="Nature of Expense" style="margin-top:8px;">
          </div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Amount</h2></div>
      <div class="field">
        <div class="amount-row">
          <select name="currency">
            <option value="USD" selected>USD</option>
            <option value="QAR">QAR</option>
          </select>
          <input type="text" name="amount" placeholder="e.g. 200 / year for MOPH" required>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Authorization</h2></div>
      <div class="auth-line">
        This is to authorize &nbsp;<input type="text" name="authorized_name" placeholder="Employee Name" required>&nbsp; to use company credit card to pay above expense.
      </div>

      <div class="grid2">
        <div class="field">
          <label class="field-label">Finance Approval</label>
          <input type="text" name="finance_approval" placeholder="Name">
        </div>
        <div class="field">
          <label class="field-label">Finance Approval Date</label>
          <input type="text" name="finance_date" placeholder="DD/MM/YYYY">
        </div>
      </div>

      <div class="grid2">
        <div class="field">
          <label class="field-label">Management Approval</label>
          <input type="text" name="mgmt_approval" placeholder="Name">
        </div>
        <div class="field">
          <label class="field-label">Management Approval Date</label>
          <input type="text" name="mgmt_date" placeholder="DD/MM/YYYY">
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

<script>
const select  = document.getElementById('companySelect');
const hidden  = document.getElementById('companyHidden');
const preview = document.getElementById('companyLogoPreview');
const header  = document.getElementById('headerLogo');

function updateLogo() {
  const opt  = select.options[select.selectedIndex];
  const logo = opt.dataset.logo;
  hidden.value = select.value;
  preview.src  = logo;
  header.src   = logo;
}

select.addEventListener('change', updateLogo);

updateLogo();
</script>
</body>
</html>
