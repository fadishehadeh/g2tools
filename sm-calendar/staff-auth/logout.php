<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if (!empty($_COOKIE['sm_staff_token'])) {
    sm_destroy_staff_session($_COOKIE['sm_staff_token']);
    setcookie('sm_staff_token', '', time() - 3600, SM_BASE_URL);
}
unset($_SESSION['sm_staff']);
header('Location: ' . SM_BASE_URL . '/staff-auth/login.php');
exit;
