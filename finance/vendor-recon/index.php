<?php
session_start();
require '../../config.php';
require_login();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Payable Reconciliation — G2 Tools</title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<link rel="stylesheet" href="/g2forms/form.css">
<style>
  .form-card { max-width: 780px; }
  .items-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  .items-table th { font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase;
                    letter-spacing: .4px; padding: 0 6px 8px; text-align: left; border-bottom: 1.5px solid #e8eaee; }
  .items-table th:nth-child(n+3) { text-align: right; }
  .items-table td { padding: 5px 4px; vertical-align: middle; }
  .ti { width:100%; padding:8px 10px; border:1.5px solid #e8eaee; border-radius:6px;
        font-size:12px; font-family:inherit; color:#1a1a1a; }
  .ti:focus { outline:none; border-color:#FF3D33; box-shadow:0 0 0 3px rgba(255,61,51,.07); }
  .ti-num { text-align:right; }
  .remove-row { background:none; border:none; color:#ffb3b0; font-size:16px; cursor:pointer; padding:0 4px; }
  .remove-row:hover { color:#FF3D33; }
  .add-row-btn { font-size:12px; font-weight:600; color:#FF3D33; background:none; border:none;
                 cursor:pointer; padding:6px 0; }
  .add-row-btn:hover { text-decoration:underline; }
  .recon-totals { background:#f8f9fb; border-radius:10px; border:1px solid #e8eaee;
                  padding:16px 18px; margin-top:6px; }
  .rt-row { display:flex; justify-content:space-between; align-items:center;
            padding:5px 0; font-size:13px; }
  .rt-row + .rt-row { border-top:1px solid #eee; }
  .rt-row .lbl { color:#666; }
  .rt-row .val { font-weight:700; color:#1a1a1a; }
  .rt-row.variance .val { color:#FF3D33; }
  .rt-row.highlight { background:#fff3f2; margin:0 -18px; padding:8px 18px; border-radius:0; }
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/g2forms/">G2 Tools</a>
  <span class="topbar-title">Vendor Payable Reconciliation</span>
</div>
<div class="form-page-wrap">
<div class="form-card">

  <div class="form-header">
    <div class="fh-logo"><img id="headerLogo" src="/g2forms/grey.jpeg" style="height:38px;display:block;object-fit:contain;max-width:140px"></div>
    <div class="fh-divider"></div>
    <div class="fh-text">
      <h1>Vendor Payable Reconciliation</h1>
      <p>Fill in all fields and submit to generate the PDF</p>
    </div>
    <div class="fh-accent">📊</div>
  </div>
  <div class="form-accent-bar"></div>

  <div class="company-bar">
    <label>Company</label>
    <div class="company-select-wrap">
      <select id="companySelect">
        <option value="g2" data-logo="/g2forms/logo.png">G2 Group</option>
        <option value="pn" data-logo="/g2forms/PN.gif">PIN and Notch</option>
        <option value="grey" data-logo="/g2forms/grey.jpeg" selected>Grey</option>
      </select>
      <img id="companyLogoPreview" src="/g2forms/grey.jpeg" style="height:28px;object-fit:contain">
    </div>
  </div>

  <form method="POST" action="generate.php">
  <input type="hidden" name="company" id="companyHidden" value="grey">
  <div class="form-body">

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Vendor Details</h2></div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Vendor Name <span style="font-size:10px;color:#aaa">(incl. AUX Code)</span></label>
          <input type="text" name="vendor_name" placeholder="e.g. Vendor Co. AUX-001" required>
        </div>
        <div class="field">
          <label class="field-label">Vendor No#</label>
          <input type="text" name="vendor_no" placeholder="e.g. V-00123">
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Date</label>
          <input type="text" name="recon_date" value="<?= date('j-M-Y') ?>" required>
        </div>
        <div class="field">
          <label class="field-label">Balance as per Grey SOA</label>
          <input type="number" name="grey_soa_balance" id="greySoa" step="0.01" placeholder="0.00" oninput="calc()" required>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Reconciling Items</h2></div>
      <table class="items-table">
        <thead>
          <tr>
            <th style="width:100px">Date</th>
            <th>Particulars</th>
            <th style="width:110px">Grey PO#</th>
            <th style="width:110px">Vendor Inv#</th>
            <th style="width:110px;text-align:right">Amount</th>
            <th style="width:30px"></th>
          </tr>
        </thead>
        <tbody id="reconBody">
          <tr>
            <td><input class="ti" type="text" name="r_date[]" placeholder="Date"></td>
            <td><input class="ti" type="text" name="r_particular[]" placeholder="Description"></td>
            <td><input class="ti" type="text" name="r_po[]" placeholder="PO#"></td>
            <td><input class="ti" type="text" name="r_inv[]" placeholder="Inv#"></td>
            <td><input class="ti ti-num" type="number" name="r_amt[]" placeholder="0.00" step="0.01" oninput="calc()"></td>
            <td><button type="button" class="remove-row" onclick="removeRow(this)">×</button></td>
          </tr>
        </tbody>
      </table>
      <button type="button" class="add-row-btn" onclick="addRow()">+ Add row</button>

      <div class="recon-totals" style="margin-top:16px">
        <div class="rt-row"><span class="lbl">Total Reconciling Items</span><span class="val" id="totalRecon">0.00</span></div>
        <div class="rt-row"><span class="lbl">Net Balance as per Grey SOA</span><span class="val" id="netGrey">0.00</span></div>
        <div class="rt-row">
          <span class="lbl">Net Balance as per Vendor SOA</span>
          <input type="number" name="vendor_soa_balance" id="vendorSoa" step="0.01" placeholder="0.00"
                 oninput="calc()" style="width:140px;padding:6px 10px;border:1.5px solid #e8eaee;border-radius:6px;font-size:13px;text-align:right">
        </div>
        <div class="rt-row variance highlight"><span class="lbl"><strong>Variance</strong></span><span class="val" id="variance">0.00</span></div>
      </div>
      <input type="hidden" name="total_recon" id="totalReconH">
      <input type="hidden" name="net_grey"    id="netGreyH">
      <input type="hidden" name="variance"    id="varianceH">
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Signatories</h2></div>
      <div class="grid2">
        <div class="field"><label class="field-label">Prepared By</label><input type="text" name="prepared_by" placeholder="Name"></div>
        <div class="field"><label class="field-label">Reviewed By</label><input type="text" name="reviewed_by" placeholder="Name"></div>
      </div>
      <div class="field" style="max-width:280px">
        <label class="field-label">Approved By</label><input type="text" name="approved_by" placeholder="Name">
      </div>
    </div>

  </div>
  <div class="form-footer">
    <button type="submit" class="submit-btn">⬇ Generate &amp; Download PDF</button>
    <p class="submit-note">You can download or send to finance after generating.</p>
  </div>
  </form>
</div>
</div>
</div>
<script>
function addRow() {
  const tb = document.getElementById('reconBody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input class="ti" type="text" name="r_date[]" placeholder="Date"></td>
    <td><input class="ti" type="text" name="r_particular[]" placeholder="Description"></td>
    <td><input class="ti" type="text" name="r_po[]" placeholder="PO#"></td>
    <td><input class="ti" type="text" name="r_inv[]" placeholder="Inv#"></td>
    <td><input class="ti ti-num" type="number" name="r_amt[]" placeholder="0.00" step="0.01" oninput="calc()"></td>
    <td><button type="button" class="remove-row" onclick="removeRow(this)">×</button></td>`;
  tb.appendChild(tr);
}
function removeRow(btn) {
  const rows = document.getElementById('reconBody').querySelectorAll('tr');
  if (rows.length > 1) { btn.closest('tr').remove(); calc(); }
}
function fmt(n) { return n.toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function calc() {
  let recon = 0;
  document.querySelectorAll('input[name="r_amt[]"]').forEach(i => { recon += parseFloat(i.value)||0; });
  const grey    = parseFloat(document.getElementById('greySoa').value)||0;
  const vendor  = parseFloat(document.getElementById('vendorSoa').value)||0;
  const netGrey = grey + recon;
  const variance= netGrey - vendor;
  document.getElementById('totalRecon').textContent = fmt(recon);
  document.getElementById('netGrey').textContent    = fmt(netGrey);
  document.getElementById('variance').textContent   = fmt(variance);
  document.getElementById('totalReconH').value = recon.toFixed(2);
  document.getElementById('netGreyH').value    = netGrey.toFixed(2);
  document.getElementById('varianceH').value   = variance.toFixed(2);
}
</script>
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
