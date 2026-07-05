<?php
session_start();
require '../../config.php';
require_login();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR Code Generator — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --red:#FF3D33; --red-dk:#c0170e; --border:#e8eaee; --bg:#f6f7f9; --dark:#1a1a1a; --mid:#555; --muted:#888; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: var(--bg); }

  .page-wrap { padding: 40px 48px 80px; max-width: 820px; }
  .tool-hero { margin-bottom: 36px; }
  .tool-hero h1 { font-size: 28px; font-weight: 800; color: var(--dark); margin-bottom: 6px; }
  .tool-hero p  { font-size: 14px; color: var(--muted); }

  .qr-layout { display: grid; grid-template-columns: 1fr 280px; gap: 24px; align-items: start; }

  .panel {
    background: #fff; border-radius: 16px; border: 1px solid var(--border);
    box-shadow: 0 4px 24px rgba(0,0,0,.07); padding: 28px 28px;
  }
  .panel h2 { font-size: 14px; font-weight: 700; color: var(--dark); margin-bottom: 20px; }

  .field { margin-bottom: 16px; }
  .field label { display: block; font-size: 12px; font-weight: 600; color: var(--mid);
                 text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }
  input[type=text], textarea, select {
    width: 100%; padding: 10px 14px; border: 1.5px solid var(--border);
    border-radius: 8px; font-size: 14px; color: var(--dark); font-family: inherit;
    transition: border-color .18s;
  }
  input[type=text]:focus, textarea:focus, select:focus {
    outline: none; border-color: var(--red); box-shadow: 0 0 0 3px rgba(255,61,51,.1);
  }
  textarea { resize: vertical; min-height: 80px; }

  .type-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
  .tab-btn {
    padding: 7px 16px; border-radius: 20px; font-size: 13px; font-weight: 600;
    border: 1.5px solid var(--border); background: #fff; color: var(--mid); cursor: pointer;
    transition: all .15s;
  }
  .tab-btn.active { background: var(--red); border-color: var(--red); color: #fff; }

  .tab-content { display: none; }
  .tab-content.active { display: block; }

  .color-row { display: flex; gap: 12px; }
  .color-field { flex: 1; }
  .color-field input[type=color] {
    width: 100%; height: 40px; padding: 2px 4px; border: 1.5px solid var(--border);
    border-radius: 8px; cursor: pointer; background: #fff;
  }

  .gen-btn {
    width: 100%; padding: 12px; background: var(--red); color: #fff; border: none;
    border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer;
    transition: background .2s; margin-top: 6px;
  }
  .gen-btn:hover { background: var(--red-dk); }

  /* Preview panel */
  .qr-preview-panel {
    background: #fff; border-radius: 16px; border: 1px solid var(--border);
    box-shadow: 0 4px 24px rgba(0,0,0,.07); padding: 28px; text-align: center;
    position: sticky; top: 24px;
  }
  .qr-preview-panel h2 { font-size: 14px; font-weight: 700; color: var(--dark); margin-bottom: 20px; }
  #qrCanvas {
    border-radius: 10px; max-width: 200px; width: 100%;
    border: 1px solid var(--border); display: block; margin: 0 auto 16px;
  }
  .qr-placeholder {
    width: 200px; height: 200px; border-radius: 10px; border: 2px dashed var(--border);
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 10px; margin: 0 auto 16px;
    color: var(--muted); font-size: 13px;
  }
  .qr-placeholder span { font-size: 36px; }
  .dl-btn {
    display: block; width: 100%; padding: 11px; background: var(--dark); color: #fff;
    border: none; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer;
    text-decoration: none; transition: background .2s; margin-bottom: 8px;
  }
  .dl-btn:hover { background: #333; }
  .dl-btn.disabled { background: #ccc; cursor: not-allowed; pointer-events: none; }
  .size-note { font-size: 11px; color: var(--muted); }
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>

<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/">G2 Tools</a>
  <span class="topbar-title">QR Code Generator</span>
</div>
<div class="page-wrap">

  <div class="tool-hero">
    <h1>📱 QR Code Generator</h1>
    <p>Generate a QR code for a URL, plain text, WiFi credentials, or contact info. Download as PNG.</p>
  </div>

  <div class="qr-layout">
    <div class="panel">
      <div class="type-tabs">
        <button class="tab-btn active" onclick="switchTab('url',this)">🔗 URL</button>
        <button class="tab-btn" onclick="switchTab('text',this)">📝 Text</button>
        <button class="tab-btn" onclick="switchTab('wifi',this)">📶 WiFi</button>
        <button class="tab-btn" onclick="switchTab('contact',this)">👤 Contact</button>
      </div>

      <!-- URL -->
      <div class="tab-content active" id="tab-url">
        <div class="field">
          <label>URL</label>
          <input type="text" id="url-input" placeholder="https://example.com" oninput="liveGenerate()">
        </div>
      </div>

      <!-- Text -->
      <div class="tab-content" id="tab-text">
        <div class="field">
          <label>Text</label>
          <textarea id="text-input" placeholder="Enter any text…" oninput="liveGenerate()"></textarea>
        </div>
      </div>

      <!-- WiFi -->
      <div class="tab-content" id="tab-wifi">
        <div class="field">
          <label>Network Name (SSID)</label>
          <input type="text" id="wifi-ssid" placeholder="MyNetwork" oninput="liveGenerate()">
        </div>
        <div class="field">
          <label>Password</label>
          <input type="text" id="wifi-pass" placeholder="Leave blank if open" oninput="liveGenerate()">
        </div>
        <div class="field">
          <label>Security</label>
          <select id="wifi-enc" onchange="liveGenerate()">
            <option value="WPA">WPA / WPA2</option>
            <option value="WEP">WEP</option>
            <option value="nopass">None (Open)</option>
          </select>
        </div>
      </div>

      <!-- Contact -->
      <div class="tab-content" id="tab-contact">
        <div class="field"><label>Full Name</label><input type="text" id="vc-name" placeholder="John Doe" oninput="liveGenerate()"></div>
        <div class="field"><label>Phone</label><input type="text" id="vc-phone" placeholder="+1 555 000 0000" oninput="liveGenerate()"></div>
        <div class="field"><label>Email</label><input type="text" id="vc-email" placeholder="john@example.com" oninput="liveGenerate()"></div>
        <div class="field"><label>Company</label><input type="text" id="vc-company" placeholder="G2 Group" oninput="liveGenerate()"></div>
      </div>

      <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">

      <div class="field">
        <label>Size</label>
        <select id="sizeSelect" onchange="liveGenerate()">
          <option value="200">Small (200×200)</option>
          <option value="300" selected>Medium (300×300)</option>
          <option value="500">Large (500×500)</option>
        </select>
      </div>
      <div class="color-row">
        <div class="color-field field">
          <label>Foreground</label>
          <input type="color" id="fgColor" value="#000000" oninput="liveGenerate()">
        </div>
        <div class="color-field field">
          <label>Background</label>
          <input type="color" id="bgColor" value="#ffffff" oninput="liveGenerate()">
        </div>
      </div>
    </div>

    <!-- Preview -->
    <div class="qr-preview-panel">
      <h2>Preview</h2>
      <div class="qr-placeholder" id="qrPlaceholder">
        <span>📱</span>
        Fill in the form to generate
      </div>
      <canvas id="qrCanvas" style="display:none"></canvas>
      <a class="dl-btn disabled" id="dlBtn" href="#" download="qrcode.png" onclick="prepareDownload(event)">⬇ Download PNG</a>
      <p class="size-note" id="sizeNote"></p>
    </div>
  </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
let currentTab = 'url';

function switchTab(tab, btn) {
  currentTab = tab;
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  btn.classList.add('active');
  liveGenerate();
}

function getQRData() {
  switch(currentTab) {
    case 'url':
      return document.getElementById('url-input').value.trim();
    case 'text':
      return document.getElementById('text-input').value.trim();
    case 'wifi': {
      const ssid = document.getElementById('wifi-ssid').value.trim();
      if (!ssid) return '';
      const pass = document.getElementById('wifi-pass').value;
      const enc  = document.getElementById('wifi-enc').value;
      return `WIFI:T:${enc};S:${ssid};P:${pass};;`;
    }
    case 'contact': {
      const name    = document.getElementById('vc-name').value.trim();
      if (!name) return '';
      const phone   = document.getElementById('vc-phone').value.trim();
      const email   = document.getElementById('vc-email').value.trim();
      const company = document.getElementById('vc-company').value.trim();
      return `BEGIN:VCARD\nVERSION:3.0\nFN:${name}\n${phone?'TEL:'+phone+'\n':''}${email?'EMAIL:'+email+'\n':''}${company?'ORG:'+company+'\n':''}END:VCARD`;
    }
  }
  return '';
}

let debounceTimer;
function liveGenerate() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(generate, 250);
}

function generate() {
  const data = getQRData();
  const canvas = document.getElementById('qrCanvas');
  const placeholder = document.getElementById('qrPlaceholder');
  const dlBtn = document.getElementById('dlBtn');
  const sizeNote = document.getElementById('sizeNote');
  const size = parseInt(document.getElementById('sizeSelect').value);
  const fg   = document.getElementById('fgColor').value;
  const bg   = document.getElementById('bgColor').value;

  if (!data) {
    canvas.style.display = 'none';
    placeholder.style.display = 'flex';
    dlBtn.classList.add('disabled');
    sizeNote.textContent = '';
    return;
  }

  QRCode.toCanvas(canvas, data, {
    width: size, margin: 2,
    color: { dark: fg, light: bg }
  }, err => {
    if (err) { console.error(err); return; }
    canvas.style.display = 'block';
    placeholder.style.display = 'none';
    dlBtn.classList.remove('disabled');
    sizeNote.textContent = size + '×' + size + ' px';
  });
}

function prepareDownload(e) {
  const dlBtn = document.getElementById('dlBtn');
  if (dlBtn.classList.contains('disabled')) { e.preventDefault(); return; }
  const canvas = document.getElementById('qrCanvas');
  dlBtn.href = canvas.toDataURL('image/png');
}
</script>
</body>
</html>
