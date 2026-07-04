<?php
// Expects sm_current_staff() to be available (bootstrap.php already required by the caller)
$_sm_cur = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
function _sm_nav_active(string $prefix): string {
    global $_sm_cur;
    return str_starts_with($_sm_cur, $prefix) ? ' active' : '';
}
?>
<aside class="sm-sidebar">
  <div class="sm-sidebar-logo"><span class="dot"></span> G2 SM Calendar</div>
  <a class="sm-nav-item<?= _sm_nav_active(SM_BASE_URL . '/index.php') ?><?= $_sm_cur === SM_BASE_URL . '/' ? ' active' : '' ?>" href="<?= SM_BASE_URL ?>/">Dashboard</a>
  <a class="sm-nav-item<?= _sm_nav_active(SM_BASE_URL . '/calendars/') ?>" href="<?= SM_BASE_URL ?>/calendars/">Calendars</a>
  <a class="sm-nav-item<?= _sm_nav_active(SM_BASE_URL . '/posts/') ?>" href="<?= SM_BASE_URL ?>/posts/">Posts</a>
  <a class="sm-nav-item<?= _sm_nav_active(SM_BASE_URL . '/clients/') ?>" href="<?= SM_BASE_URL ?>/clients/">Clients</a>
  <a class="sm-nav-item<?= _sm_nav_active(SM_BASE_URL . '/publishing/') ?>" href="<?= SM_BASE_URL ?>/publishing/">Publishing</a>
  <?php if (sm_is_admin()): ?>
  <a class="sm-nav-item<?= _sm_nav_active(SM_BASE_URL . '/users/') ?>" href="<?= SM_BASE_URL ?>/users/access.php">Access Control</a>
  <?php endif; ?>
  <a class="sm-nav-item" href="<?= SM_BASE_URL ?>/staff-auth/change-password.php">Change Password</a>
  <a class="sm-nav-item" href="<?= SM_BASE_URL ?>/staff-auth/logout.php">Log Out</a>
</aside>
