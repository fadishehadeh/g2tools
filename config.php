<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'g2forms');
define('DB_USER', 'root');
define('DB_PASS', '');
define('STORAGE_PATH', __DIR__ . '/storage/pdfs/');
define('BASE_URL', '/g2forms');
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

/**
 * Check if the current user can access a module.
 * Admins always can. Regular users depend on their per-user access flags.
 */
function can(string $module): bool {
    $u = current_user();
    if (!$u) return false;
    if (is_admin()) return true;
    // Per-user access stored as JSON in users.access_modules
    $modules = json_decode($u['access_modules'] ?? '[]', true) ?: [];
    return in_array($module, $modules);
}

function require_can(string $module): void {
    require_login();
    if (!can($module)) { header('Location: ' . BASE_URL . '/'); exit; }
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
