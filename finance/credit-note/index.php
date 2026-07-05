<?php
session_start();
require '../../config.php';
require_login();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Credit Note — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
<style>
  .items-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  .items-table th { font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase;
                    letter-spacing: .5px; padding: 0 0 8px; text-align: left; }
  .items-table th:last-child { text-align: right; width: 130px; }
  .items-table td { padding: 5px 0; vertical-align: middle; }
  .items-table td:last-child { text-align: right; width: 130px; }
  .item-desc { width: 100%; padding: 9px 12px; border: 1.5px solid #e8eaee; border-radius: 7px;
               font-size: 13px; font-family: inherit; color: #1a1a1a; }
  .item-desc:focus { outline: none; border-color: #FF3D33; box-shadow: 0 0 0 3px rgba(255,61,51,.08); }
  .item-amt  { width: 120px; padding: 9px 12px; border: 1.5px solid #e8eaee; border-radius: 7px;
               font-size: 13px; font-family: inherit; color: #1a1a1a; text-align: right; }
  .item-amt:focus { outline: none; border-color: #FF3D33; box-shadow: 0 0 0 3px rgba(255,61,51,.08); }
  .remove-row { background: none; border: none; color: #ffb3b0; font-size: 18px; cursor: pointer;
                padding: 0 6px; line-height: 1; transition: color .15s; }
  .remove-row:hover { color: #FF3D33; }
  .add-row-btn { font-size: 12px; font-weight: 600; color: #FF3D33; background: none; border: none;
                 cursor: pointer; padding: 6px 0; }
  .add-row-btn:hover { text-decoration: underline; }
  .total-row { display: flex; justify-content: space-between; align-items: center;
               padding: 12px 0; border-top: 2px solid #e8eaee; margin-top: 6px; }
  .total-row .lbl { font-size: 13px; font-weight: 700; color: #1a1a1a; }
  .total-row .val { font-size: 16px; font-weight: 800; color: #FF3D33; }
  .words-row { font-size: 12px; color: #888; font-style: italic; margin-bottom: 16px; }

  .attach-row { display: flex; gap: 20px; flex-wrap: wrap; }
  .attach-item { display: flex; align-items: center; gap: 7px; font-size: 13px; color: #555; cursor: pointer; }
  .attach-item input[type=checkbox] { width: 16px; height: 16px; accent-color: #FF3D33; cursor: pointer; }
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/">G2 Tools</a>
  <span class="topbar-title">Credit Note</span>
</div>
<div class="form-page-wrap">
<div class="form-card">

  <div class="form-header">
    <div class="fh-logo"><img id="headerLogo" src="/grey.jpeg" style="height:38px;display:block;object-fit:contain;max-width:140px"></div>
    <div class="fh-divider"></div>
    <div class="fh-text">
      <h1>Credit Note</h1>
      <p>Fill in all fields and submit to generate the PDF</p>
    </div>
    <div class="fh-accent">📄</div>
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
      <img id="companyLogoPreview" src="/grey.jpeg" style="height:28px;object-fit:contain">
    </div>
  </div>

  <form method="POST" action="generate.php">
  <input type="hidden" name="company" id="companyHidden" value="grey">
  <div class="form-body">

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Header</h2></div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">To (Vendor / Company)</label>
          <input type="text" name="to_name" placeholder="e.g. Pin and Notch" required>
        </div>
        <div class="field">
          <label class="field-label">Attention</label>
          <input type="text" name="attention" placeholder="e.g. M/S Raman">
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Date</label>
          <input type="text" name="dn_date" value="<?= date('j-M-Y') ?>" required>
        </div>
        <div class="field">
          <label class="field-label">Currency</label>
          <select name="currency">
            <option value="QAR" selected>QAR</option>
            <option value="USD">USD</option>
          </select>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Line Items</h2></div>
      <table class="items-table">
        <thead><tr><th>Description</th><th>Amount</th><th style="width:30px"></th></tr></thead>
        <tbody id="itemsBody">
          <tr>
            <td><input class="item-desc" type="text" name="desc[]" placeholder="Description" required></td>
            <td><input class="item-amt" type="number" name="amt[]" placeholder="0.00" step="0.01" min="0" oninput="calcTotal()" required></td>
            <td><button type="button" class="remove-row" onclick="removeRow(this)">×</button></td>
          </tr>
        </tbody>
      </table>
      <button type="button" class="add-row-btn" onclick="addRow()">+ Add line item</button>
      <div class="total-row">
        <span class="lbl">Total</span>
        <span class="val" id="totalDisplay">0.00</span>
      </div>
      <div class="words-row" id="wordsDisplay"></div>
      <input type="hidden" name="total" id="totalHidden" value="0">
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Attachments</h2></div>
      <div class="attach-row">
        <label class="attach-item"><input type="checkbox" name="attach_invoice" value="1"> Invoice Copy</label>
        <label class="attach-item"><input type="checkbox" name="attach_contract" value="1"> Contract</label>
        <label class="attach-item"><input type="checkbox" name="attach_email" value="1"> Email</label>
      </div>
    </div>

    <div class="section">
      <div class="section-header"><div class="s-dot"></div><h2>Signatories</h2></div>
      <div class="grid2">
        <div class="field">
          <label class="field-label">Prepared By</label>
          <input type="text" name="prepared_by" placeholder="Name">
        </div>
        <div class="field">
          <label class="field-label">Approved By</label>
          <input type="text" name="approved_by" placeholder="Name">
        </div>
      </div>
    </div>

  </div>
  <div class="form-footer">
    <button type="submit" class="submit-btn">⬇ Generate &amp; Download PDF</button>
    <p class="submit-note">You can download or email to finance after generating.</p>
  </div>
  </form>
</div>
</div>
</div>

<script>
function addRow() {
  const tbody = document.getElementById('itemsBody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input class="item-desc" type="text" name="desc[]" placeholder="Description" required></td>
    <td><input class="item-amt" type="number" name="amt[]" placeholder="0.00" step="0.01" min="0" oninput="calcTotal()"></td>
    <td><button type="button" class="remove-row" onclick="removeRow(this)">×</button></td>`;
  tbody.appendChild(tr);
}
function removeRow(btn) {
  const rows = document.getElementById('itemsBody').querySelectorAll('tr');
  if (rows.length > 1) { btn.closest('tr').remove(); calcTotal(); }
}
function calcTotal() {
  let t = 0;
  document.querySelectorAll('.item-amt').forEach(i => { t += parseFloat(i.value) || 0; });
  document.getElementById('totalDisplay').textContent = t.toLocaleString('en', {minimumFractionDigits:2, maximumFractionDigits:2});
  document.getElementById('totalHidden').value = t.toFixed(2);
  document.getElementById('wordsDisplay').textContent = t > 0 ? numberToWords(t) + ' only.' : '';
}
function numberToWords(n) {
  const a=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
  const b=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
  function words(n){
    if(n<20)return a[n];
    if(n<100)return b[Math.floor(n/10)]+(n%10?' '+a[n%10]:'');
    return a[Math.floor(n/100)]+' Hundred'+(n%100?' '+words(n%100):'');
  }
  const int=Math.floor(n), dec=Math.round((n-int)*100);
  const cur = document.querySelector('select[name="currency"]')?.value || 'QAR';
  let s = words(int) + ' ' + cur;
  if(dec>0) s += ' and ' + words(dec) + ' Fils';
  return s;
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
