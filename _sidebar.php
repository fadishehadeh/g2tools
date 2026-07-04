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

// Resolve what this user can see
$_can_finance    = can('finance');
$_can_petty      = can('petty_cash');
$_can_vendor     = can('vendor');
$_can_assets     = can('assets');
$_user_office    = $_u['office'] ?? null;
if ($_user_office === '') $_user_office = null;

// Determine which section is active so it auto-opens
$_active_section = '';
if (str_starts_with($_path, '/g2forms/office/') || str_starts_with($_path, '/g2forms/office/pantry/')) $_active_section = 'office';
elseif (str_starts_with($_path, '/g2forms/amex/') || str_starts_with($_path, '/g2forms/accountability/') || str_starts_with($_path, '/g2forms/finance/') || str_starts_with($_path, '/g2forms/history')) $_active_section = 'finance';
elseif (str_starts_with($_path, '/g2forms/vendor/')) $_active_section = 'vendor';
elseif (str_starts_with($_path, '/g2forms/assets/')) $_active_section = 'assets';
elseif (str_starts_with($_path, '/g2forms/admin/')) $_active_section = 'admin';
elseif ($_path === '/g2forms/' || $_path === '/g2forms/index.php') $_active_section = 'home';
?>
<aside class="sidebar">
  <div class="sb-logo">
    <a href="/g2forms/"><img src="/g2forms/logo.png" alt="G2"></a>
  </div>

  <nav class="sb-nav">

    <!-- Home (no group) -->
    <a class="sb-item<?= _sb_exact('/g2forms/') ?>" href="/g2forms/">
      <span class="sb-icon">⊞</span> Home
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
        <?php if (is_admin()): ?>
          <?php foreach (OFFICES as $_ok => $_ov): ?>
          <a class="sb-item<?= _sb_act('/g2forms/office/petty-cash/') && ($_GET['office']??'') === $_ok ? ' active' : '' ?>"
             href="/g2forms/office/petty-cash/?office=<?= $_ok ?>">
            <span class="sb-icon">💸</span> Petty Cash <?= $_ov['flag'] ?>
          </a>
          <?php endforeach; ?>
        <?php elseif ($_user_office): ?>
          <a class="sb-item<?= _sb_act('/g2forms/office/petty-cash/') ? ' active' : '' ?>"
             href="/g2forms/office/petty-cash/?office=<?= $_user_office ?>">
            <span class="sb-icon">💸</span> Petty Cash <?= OFFICES[$_user_office]['flag'] ?? '' ?>
          </a>
        <?php endif; ?>
        <a class="sb-item<?= _sb_act('/g2forms/office/pantry/') ?>" href="/g2forms/office/pantry/">
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
        <a class="sb-item<?= _sb_act('/g2forms/amex/') ?>" href="/g2forms/amex/">
          <span class="sb-icon">💳</span> Credit Card Auth
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/accountability/') ?>" href="/g2forms/accountability/">
          <span class="sb-icon">📦</span> Accountability
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/finance/debit-note/') ?>" href="/g2forms/finance/debit-note/">
          <span class="sb-icon">📄</span> Debit Note
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/finance/credit-note/') ?>" href="/g2forms/finance/credit-note/">
          <span class="sb-icon">📋</span> Credit Note
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/finance/vendor-recon/') ?>" href="/g2forms/finance/vendor-recon/">
          <span class="sb-icon">📊</span> Vendor Recon
        </a>
        <a class="sb-item<?= _sb_exact('/g2forms/history.php') ?>" href="/g2forms/history.php">
          <span class="sb-icon">☰</span> My Submissions
        </a>
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
        <a class="sb-item<?= _sb_act('/g2forms/vendor/') ?>" href="/g2forms/vendor/">
          <span class="sb-icon">🏢</span> Vendor Registration
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
        <a class="sb-item<?= _sb_exact('/g2forms/assets/') ?>" href="/g2forms/assets/">
          <span class="sb-icon">⊞</span> Dashboard
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/list') ?>" href="/g2forms/assets/list.php">
          <span class="sb-icon">📋</span> All Assets
        </a>
        <?php if (is_it_admin()): ?>
        <a class="sb-item<?= _sb_act('/g2forms/assets/add') ?>" href="/g2forms/assets/add.php">
          <span class="sb-icon">➕</span> Add Asset
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/import') ?>" href="/g2forms/assets/import.php">
          <span class="sb-icon">⬆</span> Bulk Import
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/transfer') ?>" href="/g2forms/assets/transfer.php">
          <span class="sb-icon">↔</span> Transfers
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/disposal') ?>" href="/g2forms/assets/disposal.php">
          <span class="sb-icon">🗑</span> Disposal
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/qr-labels') ?>" href="/g2forms/assets/qr-labels.php">
          <span class="sb-icon">🔲</span> QR Labels
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/lookups') ?>" href="/g2forms/assets/lookups.php">
          <span class="sb-icon">🏷️</span> Categories / Locations
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/settings') ?>" href="/g2forms/assets/settings.php">
          <span class="sb-icon">⚙</span> Asset Settings
        </a>
        <?php endif; ?>
        <a class="sb-item<?= _sb_act('/g2forms/assets/depreciation') ?>" href="/g2forms/assets/depreciation.php">
          <span class="sb-icon">📉</span> Depreciation
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/report') ?>" href="/g2forms/assets/report.php">
          <span class="sb-icon">📊</span> Report
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/assets/audit-log') ?>" href="/g2forms/assets/audit-log.php">
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
        <a class="sb-item<?= _sb_act('/g2forms/admin/submissions') ?>" href="/g2forms/admin/submissions.php">
          <span class="sb-icon">◈</span> All Submissions
        </a>
        <a class="sb-item<?= _sb_act('/g2forms/admin/users') ?>" href="/g2forms/admin/users/">
          <span class="sb-icon">👥</span> Users
        </a>
        <?php endif; ?>
        <?php if (is_superadmin()): ?>
        <a class="sb-item<?= _sb_act('/g2forms/admin/settings') ?>" href="/g2forms/admin/settings.php">
          <span class="sb-icon">⚙</span> Settings
        </a>
        <?php endif; ?>
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
    <a class="sb-logout" href="/g2forms/logout.php" title="Sign out">⏻</a>
  </div>
</aside>
<script>
function sbToggle(btn) {
  const group = btn.closest('.sb-group');
  const isOpen = group.classList.contains('open');
  // Close all other groups (accordion)
  document.querySelectorAll('.sb-group').forEach(g => {
    if (g !== group) g.classList.remove('open');
  });
  group.classList.toggle('open', !isOpen);
}
document.querySelectorAll('.sb-group').forEach(g => {
  if (g.querySelector('.sb-item.active')) g.classList.add('has-active');
});
</script>
