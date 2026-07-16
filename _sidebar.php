<?php
// Shared sidebar — include after session_start() + require config.php
$_cur = $_SERVER['REQUEST_URI'];
$_u   = current_user();
$_path = parse_url($_cur, PHP_URL_PATH);

function _sb_act(string $prefix): string {
    global $_path;
    return str_starts_with($_path, $prefix) ? ' active' : '';
}
function _sb_exact(string $path): string {
    global $_path;
    return ($path === $_path || $path.'index.php' === $_path) ? ' active' : '';
}

// Resolve what this user can see (granular)
$_can_finance    = can_any_finance();
$_can_petty      = can_any_petty();
$_can_vendor     = can('vendor');
$_can_assets     = can('assets');
$_can_fc         = can('finance_cc');
$_can_fa         = can('finance_accountability');
$_can_fdn        = can('finance_debit_note');
$_can_fcn        = can('finance_credit_note');
$_can_fvr        = can('finance_vendor_recon');
$_can_pc_doha    = is_admin() || can('petty_cash_doha')   || can('petty_cash');
$_can_pc_beirut  = is_admin() || can('petty_cash_beirut') || can('petty_cash');
$_user_office    = $_u['office'] ?? null;
if ($_user_office === '') $_user_office = null;

// Determine which section is active so it auto-opens
$_active_section = '';
if (str_starts_with($_path, '/g2forms/office/') || str_starts_with($_path, '/g2forms/office/pantry/')) $_active_section = 'office';
elseif (str_starts_with($_path, '/g2forms/amex/') || str_starts_with($_path, '/g2forms/accountability/') || str_starts_with($_path, '/g2forms/finance/') || str_starts_with($_path, '/g2forms/history')) $_active_section = 'finance';
elseif (str_starts_with($_path, '/g2forms/vendor/')) $_active_section = 'vendor';
elseif (str_starts_with($_path, '/g2forms/assets/')) $_active_section = 'assets';
elseif (str_starts_with($_path, '/g2forms/admin/')) $_active_section = 'admin';
elseif (str_starts_with($_path, '/admin/')) $_active_section = 'admin';
elseif ($_path === '/g2forms/' || $_path === '/g2forms/index.php') $_active_section = 'home';
?>
<aside class="sidebar">
  <div class="sb-logo">
    <a href="/"><img src="/logo.png" alt="G2"></a>
  </div>

  <nav class="sb-nav">

    <!-- Dashboard -->
    <a class="sb-item<?= _sb_act('/g2forms/dashboard') || _sb_exact('/g2forms/') ? ' active' : '' ?>" href="/dashboard.php">
      <span class="sb-icon">📊</span> Dashboard
    </a>

    <?php if ($_can_petty): ?>
    <!-- Office -->
    <div class="sb-group <?= $_active_section === 'office' ? 'open' : '' ?>" data-group="office">
      <button class="sb-group-header" onclick="sbToggle(this)">
        <span class="sb-icon">🏢</span>
        <span class="sb-group-label">Office</span>
        <span class="sb-chevron">›</span>
      </button>
      <div class="sb-group-body">
        <?php if ($_can_pc_doha): ?>
          <a class="sb-item<?= _sb_act('/g2forms/office/petty-cash/') && ($_GET['office']??'doha')==='doha' ? ' active' : '' ?>"
             href="/office/petty-cash/?office=doha">
            <span class="sb-icon">💸</span> Petty Cash 🇶🇦
          </a>
        <?php endif; ?>
        <?php if ($_can_pc_beirut): ?>
          <a class="sb-item<?= _sb_act('/g2forms/office/petty-cash/') && ($_GET['office']??'')==='beirut' ? ' active' : '' ?>"
             href="/office/petty-cash/?office=beirut">
            <span class="sb-icon">💸</span> Petty Cash 🇱🇧
          </a>
        <?php endif; ?>
        <a class="sb-item<?= _sb_act('/g2forms/office/pantry/') ?>" href="/office/pantry/">
          <span class="sb-icon">🍵</span> Pantry
        </a>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($_can_finance): ?>
    <!-- Finance -->
    <div class="sb-group <?= $_active_section === 'finance' ? 'open' : '' ?>" data-group="finance">
      <button class="sb-group-header" onclick="sbToggle(this)">
        <span class="sb-icon">💼</span>
        <span class="sb-group-label">Finance</span>
        <span class="sb-chevron">›</span>
      </button>
      <div class="sb-group-body">
        <?php if (is_admin() || $_can_fc): ?>
        <a class="sb-item<?= _sb_act('/g2forms/amex/') ?>" href="/amex/">
          <span class="sb-icon">💳</span> Credit Card Auth
        </a>
        <?php endif; ?>
        <?php if (is_admin() || $_can_fa): ?>
        <a class="sb-item<?= _sb_act('/g2forms/accountability/') ?>" href="/accountability/">
          <span class="sb-icon">📦</span> Accountability
        </a>
        <?php endif; ?>
        <?php if (is_admin() || $_can_fdn): ?>
        <a class="sb-item<?= _sb_act('/g2forms/finance/debit-note/') ?>" href="/finance/debit-note/">
          <span class="sb-icon">📄</span> Debit Note
        </a>
        <?php endif; ?>
        <?php if (is_admin() || $_can_fcn): ?>
        <a class="sb-item<?= _sb_act('/g2forms/finance/credit-note/') ?>" href="/finance/credit-note/">
          <span class="sb-icon">📋</span> Credit Note
        </a>
        <?php endif; ?>
        <?php if (is_admin() || $_can_fvr): ?>
        <a class="sb-item<?= _sb_act('/g2forms/finance/vendor-recon/') ?>" href="/finance/vendor-recon/">
          <span class="sb-icon">📊</span> Vendor Recon
        </a>
        <?php endif; ?>
        <?php if ($_can_finance): ?>
        <a class="sb-item<?= _sb_exact('/g2forms/history.php') ?>" href="/history.php">
          <span class="sb-icon">☰</span> My Submissions
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($_can_vendor && !$_can_finance): ?>
    <!-- Vendor (standalone when no finance access) -->
    <div class="sb-group <?= $_active_section === 'vendor' ? 'open' : '' ?>" data-group="vendor">
      <button class="sb-group-header" onclick="sbToggle(this)">
        <span class="sb-icon">🏢</span>
        <span class="sb-group-label">Vendor</span>
        <span class="sb-chevron">›</span>
      </button>
      <div class="sb-group-body">
        <a class="sb-item<?= _sb_act('/g2forms/vendor/') ?>" href="/vendor/">
          <span class="sb-icon">🏢</span> Vendor Registration
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/client/') ?>" href="/client/">
          <span class="sb-icon">👥</span> Client Registration
        </a>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($_can_assets): ?>
    <!-- Assets -->
    <div class="sb-group <?= $_active_section === 'assets' ? 'open' : '' ?>" data-group="assets">
      <button class="sb-group-header" onclick="sbToggle(this)">
        <span class="sb-icon">🖥️</span>
        <span class="sb-group-label">Assets</span>
        <span class="sb-chevron">›</span>
      </button>
      <div class="sb-group-body">
        <a class="sb-item<?= _sb_exact('/g2forms/assets/') ?>" href="/assets/">
          <span class="sb-icon">⊞</span> Dashboard
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/list') ?>" href="/assets/list.php">
          <span class="sb-icon">📋</span> All Assets
        </a>
        <?php if (is_it_admin()): ?>
        <a class="sb-item<?= _sb_act('/g2forms/assets/add') ?>" href="/assets/add.php">
          <span class="sb-icon">➕</span> Add Asset
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/import') ?>" href="/assets/import.php">
          <span class="sb-icon">⬆</span> Bulk Import
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/transfer') ?>" href="/assets/transfer.php">
          <span class="sb-icon">↔</span> Transfers
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/disposal') ?>" href="/assets/disposal.php">
          <span class="sb-icon">🗑</span> Disposal
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/qr-labels') ?>" href="/assets/qr-labels.php">
          <span class="sb-icon">🔲</span> QR Labels
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/lookups') ?>" href="/assets/lookups.php">
          <span class="sb-icon">🏷️</span> Categories / Locations
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/settings') ?>" href="/assets/settings.php">
          <span class="sb-icon">⚙</span> Asset Settings
        </a>
        <?php endif; ?>
        <a class="sb-item<?= _sb_act('/g2forms/assets/depreciation') ?>" href="/assets/depreciation.php">
          <span class="sb-icon">📉</span> Depreciation
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/report') ?>" href="/assets/report.php">
          <span class="sb-icon">📊</span> Report
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/audit-log') ?>" href="/assets/audit-log.php">
          <span class="sb-icon">📜</span> Audit Log
        </a>
      </div>
    </div>
    <?php endif; ?>

    <?php if (is_admin()): ?>
    <!-- Admin -->
    <div class="sb-group <?= $_active_section === 'admin' ? 'open' : '' ?>" data-group="admin">
      <button class="sb-group-header" onclick="sbToggle(this)">
        <span class="sb-icon">🛡</span>
        <span class="sb-group-label">Admin</span>
        <span class="sb-chevron">›</span>
      </button>
      <div class="sb-group-body">
        <?php if (is_finance_admin()): ?>
        <a class="sb-item<?= _sb_act('/g2forms/admin/submissions') ?>" href="/admin/submissions.php">
          <span class="sb-icon">◈</span> All Submissions
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/admin/users') ?>" href="/admin/users/">
          <span class="sb-icon">👥</span> Users
        </a>
        <?php endif; ?>
        <?php if (is_superadmin()): ?>
        <a class="sb-item<?= _sb_act('/g2forms/admin/settings') ?>" href="/admin/settings.php">
          <span class="sb-icon">⚙</span> Settings
        </a>
        <?php endif; ?>
        <a class="sb-item<?= _sb_act('/g2forms/admin/dev-tools') ?>" href="/admin/dev-tools.php">
          <span class="sb-icon">🛠</span> Dev Tools
        </a>
      </div>
    </div>
    <?php endif; ?>

  </nav>

  <div class="sb-user">
    <div class="sb-avatar"><?= strtoupper(substr($_u['name'] ?? '?', 0, 1)) ?></div>
    <div class="sb-info">
      <div class="sb-name"><?= htmlspecialchars($_u['name'] ?? '') ?></div>
      <div class="sb-role"><?= ROLES[$_u['role'] ?? 'user'] ?? 'User' ?></div>
    </div>
    <a class="sb-logout" href="/logout.php" title="Sign out">⏻</a>
  </div>
</aside>
<script>
function sbToggle(btn) {
  btn.closest('.sb-group').classList.toggle('open');
}
document.querySelectorAll('.sb-group').forEach(g => {
  if (g.querySelector('.sb-item.active')) g.classList.add('has-active');
});
</script>
