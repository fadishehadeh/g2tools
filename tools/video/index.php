<?php
session_start();
require '../../config.php';
require_login();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Video Downloader — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --red:#FF3D33; --red-dk:#c0170e; --border:#e8eaee; --bg:#f6f7f9; --dark:#1a1a1a; --mid:#555; --muted:#888; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: var(--bg); }

  .page-wrap { padding: 40px 48px 80px; max-width: 760px; }

  .tool-hero { margin-bottom: 36px; }
  .tool-hero h1 { font-size: 28px; font-weight: 800; color: var(--dark); margin-bottom: 6px; }
  .tool-hero p  { font-size: 14px; color: var(--muted); }

  .supported { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
  .s-badge {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 20px;
    font-size: 12px; font-weight: 600;
    background: #fff; border: 1.5px solid var(--border); color: var(--mid);
  }

  .input-card {
    background: #fff; border-radius: 16px; border: 1px solid var(--border);
    box-shadow: 0 4px 24px rgba(0,0,0,.07); padding: 32px 32px 28px;
    margin-bottom: 24px;
  }
  .url-row { display: flex; gap: 12px; }
  .url-row input {
    flex: 1; padding: 13px 18px; border: 1.5px solid var(--border);
    border-radius: 10px; font-size: 15px; font-family: inherit; color: var(--dark);
    transition: border-color .18s;
  }
  .url-row input:focus { outline: none; border-color: var(--red); box-shadow: 0 0 0 3px rgba(255,61,51,.1); }
  .fetch-btn {
    padding: 13px 26px; background: var(--red); color: #fff; border: none;
    border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer;
    white-space: nowrap; transition: background .2s;
  }
  .fetch-btn:hover { background: var(--red-dk); }
  .fetch-btn:disabled { background: #ccc; cursor: not-allowed; }

  .options-row { display: flex; gap: 12px; margin-top: 14px; }
  .options-row select {
    padding: 9px 14px; border: 1.5px solid var(--border); border-radius: 8px;
    font-size: 13px; font-family: inherit; color: var(--dark); background: #fff; cursor: pointer;
  }
  .options-row select:focus { outline: none; border-color: var(--red); }

  /* Result card */
  .result-card {
    background: #fff; border-radius: 16px; border: 1px solid var(--border);
    box-shadow: 0 4px 24px rgba(0,0,0,.07); overflow: hidden; display: none;
  }
  .result-card.show { display: block; }
  .result-inner { display: flex; gap: 0; }
  .result-thumb {
    width: 200px; min-height: 130px; flex-shrink: 0;
    background: #111; position: relative; overflow: hidden;
  }
  .result-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .result-thumb .no-thumb {
    width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
    font-size: 40px; min-height: 130px;
  }
  .result-info { padding: 24px 28px; flex: 1; }
  .result-info h3 { font-size: 15px; font-weight: 700; color: var(--dark); margin-bottom: 6px; line-height: 1.4; }
  .result-meta { font-size: 12px; color: var(--muted); margin-bottom: 18px; }
  .dl-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 24px; background: var(--red); color: #fff; border: none;
    border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer;
    text-decoration: none; transition: background .2s;
  }
  .dl-btn:hover { background: var(--red-dk); }

  /* Progress */
  .progress-wrap { display: none; padding: 24px 32px; }
  .progress-wrap.show { display: block; }
  .progress-label { font-size: 13px; color: var(--mid); margin-bottom: 10px; }
  .progress-bar-bg { background: #f0f0f0; border-radius: 10px; height: 8px; overflow: hidden; }
  .progress-bar    { height: 8px; background: var(--red); border-radius: 10px; width: 0%; transition: width .3s; }

  /* Error */
  .err-box {
    display: none; padding: 14px 18px; background: #fff5f5; border: 1.5px solid #fca5a5;
    border-radius: 10px; color: #dc2626; font-size: 13px; margin-top: 14px;
  }
  .err-box.show { display: block; }

  /* History */
  .history-card {
    background: #fff; border-radius: 16px; border: 1px solid var(--border);
    box-shadow: 0 2px 10px rgba(0,0,0,.05); margin-top: 32px; overflow: hidden;
  }
  .history-head { padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 700; color: var(--dark); }
  .history-item {
    display: flex; align-items: center; gap: 14px; padding: 13px 24px;
    border-bottom: 1px solid #f5f5f5; font-size: 13px;
  }
  .history-item:last-child { border-bottom: none; }
  .history-icon { font-size: 20px; flex-shrink: 0; }
  .history-name { flex: 1; color: var(--dark); font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .history-dl { color: var(--red); font-weight: 700; text-decoration: none; font-size: 12px; white-space: nowrap; }
  .history-dl:hover { text-decoration: underline; }
  .history-empty { padding: 20px 24px; font-size: 13px; color: var(--muted); }
</style>
</head>
<body>
<?php require '../../_sidebar.php'; ?>

<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="/">G2 Tools</a>
  <span class="topbar-title">Video Downloader</span>
</div>
<div class="page-wrap">

  <div class="tool-hero">
    <h1>🎬 Video Downloader</h1>
    <p>Paste a link from any supported platform to download the video.</p>
    <div class="supported">
      <span class="s-badge">📸 Instagram</span>
      <span class="s-badge">📘 Facebook</span>
      <span class="s-badge">▶️ YouTube</span>
      <span class="s-badge">🎵 TikTok</span>
      <span class="s-badge">🐦 Twitter / X</span>
      <span class="s-badge">+ 1000 more</span>
    </div>
  </div>

  <div class="input-card">
    <div class="url-row">
      <input type="text" id="urlInput" placeholder="Paste video URL here…" autocomplete="off" spellcheck="false">
      <button class="fetch-btn" id="fetchBtn" onclick="fetchInfo()">Fetch Info</button>
    </div>
    <div class="options-row">
      <select id="qualitySelect">
        <option value="best">Best Quality (Video + Audio)</option>
        <option value="bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best">Best MP4</option>
        <option value="worstvideo+worstaudio/worst">Smallest File</option>
        <option value="bestaudio/best">Audio Only (MP3)</option>
      </select>
    </div>
    <div class="err-box" id="errBox"></div>
  </div>

  <div class="progress-wrap" id="progressWrap">
    <div class="progress-label" id="progressLabel">Fetching video info…</div>
    <div class="progress-bar-bg"><div class="progress-bar" id="progressBar"></div></div>
  </div>

  <div class="result-card" id="resultCard">
    <div class="result-inner">
      <div class="result-thumb" id="thumbWrap">
        <div class="no-thumb">🎬</div>
      </div>
      <div class="result-info">
        <h3 id="videoTitle">Video Title</h3>
        <div class="result-meta" id="videoMeta"></div>
        <button class="dl-btn" id="dlBtn" onclick="startDownload()">⬇ Download</button>
      </div>
    </div>
  </div>

  <div class="history-card" id="historyCard" style="display:none">
    <div class="history-head">Recent Downloads</div>
    <div id="historyList"></div>
  </div>

</div>
</div>

<script>
let currentInfo = null;

function showErr(msg) {
  const b = document.getElementById('errBox');
  b.textContent = msg;
  b.classList.add('show');
}
function hideErr() { document.getElementById('errBox').classList.remove('show'); }

function setProgress(pct, label) {
  const w = document.getElementById('progressWrap');
  w.classList.add('show');
  document.getElementById('progressBar').style.width = pct + '%';
  document.getElementById('progressLabel').textContent = label;
}
function hideProgress() { document.getElementById('progressWrap').classList.remove('show'); }

async function fetchInfo() {
  const url = document.getElementById('urlInput').value.trim();
  if (!url) { showErr('Please paste a video URL.'); return; }
  hideErr();
  document.getElementById('resultCard').classList.remove('show');
  document.getElementById('fetchBtn').disabled = true;
  setProgress(20, 'Fetching video info…');

  try {
    const res  = await fetch('info.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'url='+encodeURIComponent(url) });
    const data = await res.json();
    if (!data.ok) { showErr(data.error || 'Could not fetch video info.'); hideProgress(); document.getElementById('fetchBtn').disabled = false; return; }

    currentInfo = data;
    setProgress(100, 'Ready!');
    setTimeout(hideProgress, 500);

    document.getElementById('videoTitle').textContent = data.title || 'Untitled';
    document.getElementById('videoMeta').textContent  = [data.uploader, data.duration_str, data.platform].filter(Boolean).join(' · ');

    const tw = document.getElementById('thumbWrap');
    if (data.thumbnail) {
      tw.innerHTML = '<img src="thumb.php?url='+encodeURIComponent(data.thumbnail)+'" onerror="this.parentNode.innerHTML=\'<div class=no-thumb>🎬</div>\'">';
    }

    document.getElementById('resultCard').classList.add('show');
  } catch(e) {
    showErr('Network error. Please try again.');
    hideProgress();
  }
  document.getElementById('fetchBtn').disabled = false;
}

function startDownload() {
  const url     = document.getElementById('urlInput').value.trim();
  const quality = document.getElementById('qualitySelect').value;
  const btn     = document.getElementById('dlBtn');
  btn.textContent = '⏳ Downloading…';
  btn.style.background = '#888';

  const title = currentInfo?.title || 'video';
  addHistory(title, url, quality);

  // Open download in new tab — PHP will stream the file
  window.open('download.php?url='+encodeURIComponent(url)+'&quality='+encodeURIComponent(quality), '_blank');

  setTimeout(() => { btn.innerHTML = '⬇ Download'; btn.style.background = ''; }, 3000);
}

// ── History (localStorage) ──
function addHistory(title, url, quality) {
  let h = JSON.parse(localStorage.getItem('vdl_history') || '[]');
  h.unshift({ title, url, quality, ts: Date.now() });
  h = h.slice(0, 10);
  localStorage.setItem('vdl_history', JSON.stringify(h));
  renderHistory();
}
function renderHistory() {
  const h = JSON.parse(localStorage.getItem('vdl_history') || '[]');
  if (!h.length) return;
  const card = document.getElementById('historyCard');
  const list = document.getElementById('historyList');
  card.style.display = 'block';
  list.innerHTML = h.map(item => `
    <div class="history-item">
      <span class="history-icon">🎬</span>
      <span class="history-name" title="${item.title}">${item.title}</span>
      <a class="history-dl" href="download.php?url=${encodeURIComponent(item.url)}&quality=${encodeURIComponent(item.quality)}" target="_blank">⬇ Download</a>
    </div>
  `).join('');
}
renderHistory();

document.getElementById('urlInput').addEventListener('keydown', e => { if (e.key === 'Enter') fetchInfo(); });
</script>
</body>
</html>
