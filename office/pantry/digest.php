<?php
/**
 * Daily low-stock digest — call via cron or Windows Task Scheduler:
 *   php C:\xampp\htdocs\g2forms\office\pantry\digest.php
 * Or via HTTP with a secret token:
 *   GET /g2forms/office/pantry/digest.php?token=YOUR_SECRET
 */
if (PHP_SAPI !== 'cli') {
    $token = getenv('PANTRY_DIGEST_TOKEN') ?: 'g2pantry2025';
    if (($_GET['token'] ?? '') !== $token) {
        http_response_code(403); echo 'Forbidden'; exit;
    }
}

require_once __DIR__ . '/../../config.php';

$low_items = db()->query(
    "SELECT * FROM pantry_items WHERE active=1 AND current_stock < par_level ORDER BY (current_stock/par_level) ASC"
)->fetchAll();

if (empty($low_items)) {
    echo "No low-stock items. Nothing to send.\n";
    exit;
}

$admins = db()->query("SELECT email FROM users WHERE role IN ('finance_admin','it_admin') AND email != ''")->fetchAll();
$admin_emails = array_column($admins, 'email');

if (empty($admin_emails)) {
    echo "No admin emails found.\n";
    exit;
}

$subject = 'Pantry Daily Digest — ' . count($low_items) . ' item(s) need restocking (' . date('d M Y') . ')';

$body = "Good morning,\n\n";
$body .= "Here is your daily pantry stock digest. The following items are below par level:\n\n";
$body .= str_pad('ITEM', 30) . str_pad('CURRENT', 12) . str_pad('PAR', 12) . "STATUS\n";
$body .= str_repeat('─', 68) . "\n";

foreach ($low_items as $item) {
    $status = $item['current_stock'] <= 0 ? '🔴 OUT OF STOCK' : ($item['current_stock'] / $item['par_level'] <= 0.25 ? '🟠 CRITICAL' : '🟡 LOW');
    $body .= str_pad($item['name'], 30)
           . str_pad(number_format($item['current_stock'],1) . ' ' . $item['unit'], 12)
           . str_pad(number_format($item['par_level'],1) . ' ' . $item['unit'], 12)
           . $status . "\n";
}

$body .= "\n" . str_repeat('─', 68) . "\n";
$body .= "Please arrange restocking for the items above.\n\n";
$body .= "View the full pantry dashboard: http://yourserver/g2forms/office/pantry/\n\n";
$body .= "— G2 Tools\n";

$headers = "From: G2 Tools <noreply@g2group.com>\r\n";
$ok = mail(implode(', ', $admin_emails), $subject, $body, $headers);

// Log alerts sent
foreach ($low_items as $item) {
    db()->prepare("INSERT INTO pantry_alert_log (item_id) VALUES (?)")->execute([$item['id']]);
}

echo $ok ? "Digest sent to: " . implode(', ', $admin_emails) . "\n" : "Mail send failed.\n";
