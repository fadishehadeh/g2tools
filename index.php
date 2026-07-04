<?php
session_start();
require 'config.php';
require_login();
$user = current_user();

// Asset stats for hero + card
$asset_stats = null;
if (can('assets')) {
    $asset_stats = db()->query("SELECT COUNT(*) total, SUM(status='active') active, COALESCE(SUM(purchase_value),0) total_value FROM assets")->fetch();
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>G2 Tools</title>
<link rel="stylesheet" href="/g2forms/sidebar.css">
<style>
* { box-sizing: border-box; }

.home-wrap { padding: 0 0 80px; }

/* ── Hero ── */
.hero {
  background: #111;
  background-image:
    radial-gradient(ellipse at 80% 50%, rgba(255,61,51,.18) 0%, transparent 55%),
    radial-gradient(ellipse at 10% 80%, rgba(255,61,51,.07) 0%, transparent 50%);
  padding: 52px 48px 48px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  display: flex; align-items: center; justify-content: space-between; gap: 32px;
}
.hero-left {}
.hero-eyebrow {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: 10px; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase;
  color: #ff7060; background: rgba(255,61,51,.12); border: 1px solid rgba(255,61,51,.25);
  padding: 5px 13px; border-radius: 20px; margin-bottom: 20px;
}
.hero-eyebrow span { width: 6px; height: 6px; border-radius: 50%; background: #FF3D33; display: block; }
.hero h1 {
  font-size: 44px; font-weight: 900; color: #fff; letter-spacing: -1.5px;
  line-height: 1.05; margin: 0 0 14px;
}
.hero h1 em { color: #FF3D33; font-style: normal; }
.hero-sub { font-size: 14px; color: rgba(255,255,255,.35); line-height: 1.7; max-width: 380px; }

.hero-stats { display: flex; gap: 28px; }
.hstat { text-align: center; }
.hstat-val { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: -1px; }
.hstat-lbl { font-size: 10px; color: rgba(255,255,255,.3); text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }
.hstat-divider { width: 1px; background: rgba(255,255,255,.08); align-self: stretch; }

/* ── Section ── */
.section-wrap { padding: 40px 48px 0; }
.section-label {
  display: flex; align-items: center; gap: 14px; margin-bottom: 20px;
}
.section-label-text {
  font-size: 10px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase;
  color: #aaa; white-space: nowrap;
}
.section-label-line { flex: 1; height: 1px; background: #e8eaee; }

/* ── Cards grid ── */
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 16px;
}

/* ── Card ── */
.card {
  background: #fff;
  border-radius: 16px;
  border: 1.5px solid #eef0f3;
  display: flex; flex-direction: column;
  text-decoration: none; color: inherit;
  padding: 24px;
  transition: transform .18s, box-shadow .18s, border-color .18s;
  position: relative; overflow: hidden;
}
.card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 3px;
  background: var(--card-color, #FF3D33);
  transform: scaleX(0); transform-origin: left;
  transition: transform .22s;
}
.card:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,.08); border-color: #dde0e6; }
.card:hover::before { transform: scaleX(1); }

.card-icon {
  width: 48px; height: 48px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 18px; flex-shrink: 0;
  background: var(--icon-bg, #fff3f2);
}
.card-icon svg { width: 24px; height: 24px; }

.card-title { font-size: 15px; font-weight: 700; color: #111; margin-bottom: 7px; line-height: 1.25; }
.card-desc  { font-size: 12.5px; color: #8a8f9a; line-height: 1.6; flex: 1; }

.card-foot {
  margin-top: 20px;
  display: flex; align-items: center; justify-content: space-between;
}
.card-tag {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px;
  padding: 3px 10px; border-radius: 20px;
  background: var(--tag-bg, #fff3f2); color: var(--tag-color, #FF3D33);
}
.card-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 9px 18px;
  background: var(--card-color, #FF3D33); color: #fff;
  border-radius: 30px; font-size: 12px; font-weight: 700;
  transition: opacity .15s, transform .12s;
}
.card:hover .card-btn { opacity: .9; }
.card:active .card-btn { transform: scale(.96); }

/* Color themes */
.card-finance  { --card-color:#FF3D33; --icon-bg:#fff2f1; --tag-bg:#fff2f1; --tag-color:#FF3D33; }
.card-hr       { --card-color:#7c3aed; --icon-bg:#f5f3ff; --tag-bg:#f5f3ff; --tag-color:#7c3aed; }
.card-office   { --card-color:#0891b2; --icon-bg:#ecfeff; --tag-bg:#ecfeff; --tag-color:#0891b2; }
.card-pantry   { --card-color:#16a34a; --icon-bg:#f0fdf4; --tag-bg:#f0fdf4; --tag-color:#16a34a; }
.card-vendor   { --card-color:#d97706; --icon-bg:#fffbeb; --tag-bg:#fffbeb; --tag-color:#d97706; }
.card-assets   { --card-color:#0f766e; --icon-bg:#f0fdfa; --tag-bg:#f0fdfa; --tag-color:#0f766e; }

/* Asset card mini stats */
.card-mini-stats { display:flex; gap:16px; margin-top:14px; padding-top:14px; border-top:1px solid #f0f1f3; }
.cms-item { flex:1; }
.cms-val  { font-size:18px; font-weight:800; color:#111; letter-spacing:-.5px; }
.cms-lbl  { font-size:10px; color:#aaa; text-transform:uppercase; letter-spacing:.6px; margin-top:1px; }

/* ── Footer ── */
.home-footer {
  margin: 48px 48px 0; padding: 20px 0 0;
  border-top: 1px solid #eef0f3;
  font-size: 12px; color: #ccc;
  display: flex; align-items: center; justify-content: space-between;
}
.home-footer img { height: 18px; opacity: .25; }
.home-footer span { color: #ddd; }
</style>
</head>
<body>
<?php require '_sidebar.php'; ?>

<div class="main-content">
<div class="home-wrap">

  <!-- ── Hero ── -->
  <div class="hero">
    <div class="hero-left">
      <div class="hero-eyebrow"><span></span>Internal Portal</div>
      <h1>G2 <em>Tools</em></h1>
      <p class="hero-sub">Your internal workspace — finance forms, office management, and more in one place.</p>
    </div>
    <div class="hero-stats">
      <div class="hstat">
        <div class="hstat-val">5</div>
        <div class="hstat-lbl">Finance Forms</div>
      </div>
      <div class="hstat-divider"></div>
      <div class="hstat">
        <div class="hstat-val">2</div>
        <div class="hstat-lbl">Office Tools</div>
      </div>
      <?php if ($asset_stats): ?>
      <div class="hstat-divider"></div>
      <div class="hstat">
        <div class="hstat-val"><?= $asset_stats['total'] ?></div>
        <div class="hstat-lbl">Assets</div>
      </div>
      <?php endif; ?>
      <div class="hstat-divider"></div>
      <div class="hstat">
        <div class="hstat-val">1</div>
        <div class="hstat-lbl">Vendor Portal</div>
      </div>
    </div>
  </div>

  <!-- ── Finance ── -->
  <div class="section-wrap">
    <div class="section-label">
      <span class="section-label-text">Finance</span>
      <div class="section-label-line"></div>
    </div>
    <div class="cards-grid">

      <a class="card card-finance" href="/g2forms/amex/">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#FF3D33" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
          </svg>
        </div>
        <div class="card-title">Credit Card Authorization</div>
        <div class="card-desc">Request authorization to use the company AMEX card for a billable or non-billable expense.</div>
        <div class="card-foot">
          <span class="card-tag">AMEX</span>
          <span class="card-btn">Open →</span>
        </div>
      </a>

      <a class="card card-hr" href="/g2forms/accountability/">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
          </svg>
        </div>
        <div class="card-title">Accountability for Company Property</div>
        <div class="card-desc">Acknowledge receipt and responsibility for equipment or property issued to you.</div>
        <div class="card-foot">
          <span class="card-tag">HR / Admin</span>
          <span class="card-btn">Open →</span>
        </div>
      </a>

      <a class="card card-finance" href="/g2forms/finance/debit-note/">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#FF3D33" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/>
            <line x1="12" y1="12" x2="12" y2="18"/>
          </svg>
        </div>
        <div class="card-title">Debit Note</div>
        <div class="card-desc">Generate a debit note to charge a vendor's account for agreed amounts.</div>
        <div class="card-foot">
          <span class="card-tag">Finance</span>
          <span class="card-btn">Open →</span>
        </div>
      </a>

      <a class="card card-finance" href="/g2forms/finance/credit-note/">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#FF3D33" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/>
          </svg>
        </div>
        <div class="card-title">Credit Note</div>
        <div class="card-desc">Generate a credit note to credit a vendor's account after an adjustment.</div>
        <div class="card-foot">
          <span class="card-tag">Finance</span>
          <span class="card-btn">Open →</span>
        </div>
      </a>

      <a class="card card-finance" href="/g2forms/finance/vendor-recon/">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#FF3D33" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6" y1="20" x2="6" y2="14"/>
          </svg>
        </div>
        <div class="card-title">Vendor Payable Reconciliation</div>
        <div class="card-desc">Reconcile vendor payable balances against Grey SOA and identify variances.</div>
        <div class="card-foot">
          <span class="card-tag">Finance</span>
          <span class="card-btn">Open →</span>
        </div>
      </a>

    </div>
  </div>

  <!-- ── Office ── -->
  <div class="section-wrap" style="margin-top:44px">
    <div class="section-label">
      <span class="section-label-text">Office</span>
      <div class="section-label-line"></div>
    </div>
    <div class="cards-grid">

      <a class="card card-office" href="/g2forms/office/petty-cash/?office=doha">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#0891b2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 6v2m0 8v2M9.5 9.5a3 3 0 0 1 5 0c0 1.6-1.5 2.5-2.5 3s-2.5 1.5-2.5 3a3 3 0 0 0 5 0"/>
          </svg>
        </div>
        <div class="card-title">Petty Cash — Doha 🇶🇦</div>
        <div class="card-desc">Submit and manage petty cash requests for the Doha office. Tracked in QAR.</div>
        <div class="card-foot">
          <span class="card-tag">QAR</span>
          <span class="card-btn">Open →</span>
        </div>
      </a>

      <a class="card" style="--card-color:#7c3aed;--icon-bg:#f5f3ff;--tag-bg:#f5f3ff;--tag-color:#7c3aed" href="/g2forms/office/petty-cash/?office=beirut">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 6v2m0 8v2M9.5 9.5a3 3 0 0 1 5 0c0 1.6-1.5 2.5-2.5 3s-2.5 1.5-2.5 3a3 3 0 0 0 5 0"/>
          </svg>
        </div>
        <div class="card-title">Petty Cash — Beirut 🇱🇧</div>
        <div class="card-desc">Submit and manage petty cash requests for the Beirut office. Tracked in USD.</div>
        <div class="card-foot">
          <span class="card-tag">USD</span>
          <span class="card-btn">Open →</span>
        </div>
      </a>

      <a class="card card-pantry" href="/g2forms/office/pantry/">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 11l19-9-9 19-2-8-8-2z"/>
          </svg>
        </div>
        <div class="card-title">Pantry Control</div>
        <div class="card-desc">Monitor pantry stock levels in real time. Get instant alerts when items run low.</div>
        <div class="card-foot">
          <span class="card-tag">Office</span>
          <span class="card-btn">Open →</span>
        </div>
      </a>

    </div>
  </div>

  <!-- ── Assets ── -->
  <?php if ($asset_stats): ?>
  <div class="section-wrap" style="margin-top:44px">
    <div class="section-label">
      <span class="section-label-text">Asset Management</span>
      <div class="section-label-line"></div>
    </div>
    <div class="cards-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px,1fr))">

      <a class="card card-assets" href="/g2forms/assets/">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2"/>
            <path d="M8 21h8M12 17v4"/>
          </svg>
        </div>
        <div class="card-title">Asset Management</div>
        <div class="card-desc">Track company equipment, manage assignments, depreciation schedules, disposals, and transfers across all offices.</div>
        <div class="card-mini-stats">
          <div class="cms-item">
            <div class="cms-val"><?= $asset_stats['total'] ?></div>
            <div class="cms-lbl">Total Assets</div>
          </div>
          <div class="cms-item">
            <div class="cms-val" style="color:#16a34a"><?= $asset_stats['active'] ?></div>
            <div class="cms-lbl">Active</div>
          </div>
          <div class="cms-item">
            <div class="cms-val" style="font-size:14px">QAR <?= number_format($asset_stats['total_value'] / 1000, 1) ?>k</div>
            <div class="cms-lbl">Total Value</div>
          </div>
        </div>
        <div class="card-foot" style="margin-top:14px">
          <span class="card-tag">IT / Admin</span>
          <span class="card-btn">Open →</span>
        </div>
      </a>

    </div>
  </div>
  <?php endif; ?>

  <!-- ── Vendor Portal ── -->
  <div class="section-wrap" style="margin-top:44px">
    <div class="section-label">
      <span class="section-label-text">Vendor Portal</span>
      <div class="section-label-line"></div>
    </div>
    <div class="cards-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px,1fr))">

      <a class="card card-vendor" href="/g2forms/vendor/" target="_blank">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </div>
        <div class="card-title">Vendor Registration</div>
        <div class="card-desc">External vendors register their company and bank details. No login required — share this link directly with suppliers.</div>
        <div class="card-foot">
          <span class="card-tag">Public</span>
          <span class="card-btn">Open ↗</span>
        </div>
      </a>

    </div>
  </div>

  <div class="home-footer">
    <div style="display:flex;align-items:center;gap:10px">
      <img src="/g2forms/logo.png" alt="G2">
      <span>G2 Tools — Internal Use Only</span>
    </div>
    <span>Logged in as <strong style="color:#555"><?= htmlspecialchars($user['name']) ?></strong></span>
  </div>

</div>
</div>
</body>
</html>
