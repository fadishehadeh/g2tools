<?php
// Local overrides (not in git) — created on live server with correct DB/URL
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

if (!defined('DB_HOST'))   define('DB_HOST',   'localhost');
if (!defined('DB_NAME'))   define('DB_NAME',   'g2forms');
if (!defined('DB_USER'))   define('DB_USER',   'root');
if (!defined('DB_PASS'))   define('DB_PASS',   '');
if (!defined('BASE_URL'))  define('BASE_URL',  '/g2forms');
define('STORAGE_PATH', __DIR__ . '/storage/pdfs/');
define('OCR_SPACE_API_KEY', 'K81733201488957');

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function current_user(): ?array { return $_SESSION['g2_user'] ?? null; }

function require_login(): void {
    if (!current_user()) {
        $back = urlencode($_SERVER['REQUEST_URI']);
        header('Location: ' . BASE_URL . '/login.php?redirect=' . $back);
        exit;
    }
}

// Role hierarchy: superadmin > finance_admin > it_admin > user
const ROLES = [
    'superadmin'    => 'Super Admin',
    'finance_admin' => 'Finance Admin',
    'it_admin'      => 'IT Admin',
    'user'          => 'User',
];

function role(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
    $u = current_user();
    if (!$u) return $cached = 'user';
    // Always re-fetch from DB so role changes take effect without re-login
    $r = db()->prepare("SELECT role FROM users WHERE id=?");
    $r->execute([$u['id']]);
    $dbRole = $r->fetchColumn() ?: 'user';
    $_SESSION['g2_user']['role'] = $dbRole;
    return $cached = $dbRole;
}

function is_superadmin(): bool { return role() === 'superadmin'; }
function is_finance_admin(): bool { return in_array(role(), ['superadmin','finance_admin']); }
function is_it_admin(): bool { return in_array(role(), ['superadmin','it_admin']); }
function is_admin(): bool { return in_array(role(), ['superadmin','finance_admin','it_admin']); }

function require_admin(): void {
    require_login();
    if (!is_admin()) { header('Location: ' . BASE_URL . '/'); exit; }
}
function require_superadmin(): void {
    require_login();
    if (!is_superadmin()) { header('Location: ' . BASE_URL . '/'); exit; }
}

// Granular permission keys for user access control
const MODULE_PERMS = [
    'finance_cc'             => 'Credit Card Auth',
    'finance_accountability' => 'Accountability',
    'finance_debit_note'     => 'Debit Note',
    'finance_credit_note'    => 'Credit Note',
    'finance_vendor_recon'   => 'Vendor Recon',
    'petty_cash_doha'        => 'Petty Cash — Doha (QAR)',
    'petty_cash_beirut'      => 'Petty Cash — Beirut (USD)',
    'vendor'                 => 'Vendor Registration',
    'assets'                 => 'Asset Management',
];

/**
 * Check if the current user has a specific permission.
 * Admins always pass. Regular users checked against access_modules JSON.
 * Backward compat: old broad 'finance' and 'petty_cash' keys still work.
 */
function can(string $perm): bool {
    $u = current_user();
    if (!$u) return false;
    if (is_admin()) return true;
    $mods = json_decode($u['access_modules'] ?? '[]', true) ?: [];
    if (in_array($perm, $mods)) return true;
    // Backward compat: broad 'finance' covers all finance_* sub-keys
    if (str_starts_with($perm, 'finance_') && in_array('finance', $mods)) return true;
    // Backward compat: broad 'petty_cash' covers both offices
    if (in_array($perm, ['petty_cash_doha','petty_cash_beirut']) && in_array('petty_cash', $mods)) return true;
    return false;
}

function can_any_finance(): bool {
    if (is_admin()) return true;
    foreach (['finance','finance_cc','finance_accountability','finance_debit_note','finance_credit_note','finance_vendor_recon'] as $p)
        if (can($p)) return true;
    return false;
}

function can_any_petty(): bool {
    if (is_admin()) return true;
    foreach (['petty_cash','petty_cash_doha','petty_cash_beirut'] as $p)
        if (can($p)) return true;
    return false;
}

function require_can(string $perm): void {
    require_login();
    if (!can($perm)) { header('Location: ' . BASE_URL . '/'); exit; }
}

function require_it_admin(): void {
    require_login();
    if (!is_it_admin()) { header('Location: ' . BASE_URL . '/'); exit; }
}

define('OFFICES', [
    'doha'   => ['label' => 'Doha Office',  'currency' => 'QAR', 'flag' => '🇶🇦'],
    'beirut' => ['label' => 'Beirut Office', 'currency' => 'USD', 'flag' => '🇱🇧'],
]);

function get_setting(string $key, string $default = ''): string {
    try {
        $s = db()->prepare("SELECT value FROM settings WHERE `key` = ?");
        $s->execute([$key]);
        $r = $s->fetchColumn();
        return ($r !== false && $r !== '') ? $r : $default;
    } catch (\Exception $e) { return $default; }
}

function get_finance_emails(): array {
    $emails = [];
    foreach (['finance_email_1','finance_email_2','finance_email_3'] as $k) {
        $v = get_setting($k);
        if ($v && filter_var($v, FILTER_VALIDATE_EMAIL)) $emails[] = $v;
    }
    return $emails;
}

function send_pdf_to_finance(string $subject, string $body_text, string $pdf_path, string $pdf_name, ?array $override_emails = null): bool {
    $recipients = $override_emails ?? get_finance_emails();
    if (empty($recipients) || !file_exists($pdf_path)) return false;
    $boundary = '----=_G2_' . uniqid();
    $encoded  = base64_encode(file_get_contents($pdf_path));
    $headers  = "From: G2 Tools <noreply@g2group.com>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
    $body  = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$body_text}\r\n\r\n";
    $body .= "--{$boundary}\r\nContent-Type: application/pdf; name=\"{$pdf_name}\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$pdf_name}\"\r\n\r\n";
    $body .= chunk_split($encoded) . "\r\n--{$boundary}--";
    return mail(implode(', ', $recipients), $subject, $body, $headers);
}
