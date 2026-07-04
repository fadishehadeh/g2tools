<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/sessions.php';
require_once __DIR__ . '/otp.php';
require_once __DIR__ . '/password_policy.php';

const SM_STAFF_COOKIE  = 'sm_staff_token';
const SM_CLIENT_COOKIE = 'sm_client_token';

// Identity is re-validated against the DB-backed session on EVERY request (not cached in
// $_SESSION) so that a revoked sm_user_access grant or deleted user takes effect immediately,
// rather than persisting until the cookie's natural 7-day expiry.
unset($_SESSION['sm_staff'], $_SESSION['sm_client']);

if (!empty($_COOKIE[SM_STAFF_COOKIE])) {
    $staff = sm_resolve_staff_session($_COOKIE[SM_STAFF_COOKIE]);
    if ($staff) $_SESSION['sm_staff'] = $staff;
    else setcookie(SM_STAFF_COOKIE, '', time() - 3600, SM_BASE_URL);
}

if (!empty($_COOKIE[SM_CLIENT_COOKIE])) {
    $client = sm_resolve_client_session($_COOKIE[SM_CLIENT_COOKIE]);
    if ($client) $_SESSION['sm_client'] = $client;
    else setcookie(SM_CLIENT_COOKIE, '', time() - 3600, SM_BASE_URL);
}
