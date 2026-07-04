<?php
define('SM_DB_HOST', 'localhost');
define('SM_DB_NAME', 'g2_sm_calendar_tool');
define('SM_DB_USER', 'root');
define('SM_DB_PASS', '');
define('G2_DB_NAME', 'g2forms'); // shared identity source

define('SM_BASE_URL', '/g2forms/sm-calendar');
define('SM_STORAGE_PATH', __DIR__ . '/storage/');
define('SM_ARTWORK_PATH', __DIR__ . '/storage/artwork/');
define('SM_LOGO_PATH', __DIR__ . '/storage/logos/');

define('REVIEW_LINK_SECRET', 'change-this-in-production-9f8e7d6c5b4a3210');
define('REVIEW_LINK_TTL_HOURS', 72);

// Mailjet — optional, degrades gracefully when unset
define('MAILJET_API_KEY', getenv('MAILJET_API_KEY') ?: '');
define('MAILJET_API_SECRET', getenv('MAILJET_API_SECRET') ?: '');
define('MAILJET_SENDER_EMAIL', getenv('MAILJET_SENDER_EMAIL') ?: 'noreply@g2group.com');
define('MAILJET_SENDER_NAME', 'G2 SM Calendar Tool');

// Meta — optional, degrades gracefully when unset
define('META_APP_ID', getenv('META_APP_ID') ?: '');
define('META_APP_SECRET', getenv('META_APP_SECRET') ?: '');
define('META_REDIRECT_URI', getenv('META_REDIRECT_URI') ?: 'http://localhost' . SM_BASE_URL . '/publishing/meta_callback.php');
define('META_OAUTH_SCOPES', 'pages_show_list,pages_manage_posts,pages_read_engagement,instagram_basic,instagram_content_publish,business_management');

function sm_db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . SM_DB_HOST . ';dbname=' . SM_DB_NAME . ';charset=utf8mb4',
            SM_DB_USER, SM_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

/** Same MySQL server — query g2forms.users directly via qualified table name (shared identity, no duplication). */
function g2_users_table(): string { return '`' . G2_DB_NAME . '`.`users`'; }

function sm_current_staff(): ?array { return $_SESSION['sm_staff'] ?? null; }
function sm_current_client(): ?array { return $_SESSION['sm_client'] ?? null; }

function sm_require_staff(): void {
    if (!sm_current_staff()) {
        $back = urlencode($_SERVER['REQUEST_URI']);
        header('Location: ' . SM_BASE_URL . '/staff-auth/login.php?redirect=' . $back);
        exit;
    }
}

function sm_require_client(): void {
    if (!sm_current_client()) {
        header('Location: ' . SM_BASE_URL . '/client-auth/login.php');
        exit;
    }
}

function sm_is_admin(): bool {
    $s = sm_current_staff();
    return $s && $s['sm_role'] === 'admin';
}

function sm_require_admin(): void {
    sm_require_staff();
    if (!sm_is_admin()) { header('Location: ' . SM_BASE_URL . '/'); exit; }
}
