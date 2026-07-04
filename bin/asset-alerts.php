<?php
/**
 * Asset alert cron — run daily via cPanel cron:
 *   php /home/greykktq/g2tools.greydoha.com/bin/asset-alerts.php
 *
 * Sends email alerts for:
 *  - Maintenance due within 7 days
 *  - Warranty expiring within 14 days
 */

define('RUNNING_AS_CLI', true);
require __DIR__.'/../config.php';
require __DIR__.'/../mailer.php';

$ADMIN_EMAIL = 'fadi.chehade@greydoha.com';
$today       = date('Y-m-d');

// ── Maintenance due alerts (within 7 days) ────────────────────────────────
$stmt = db()->prepare("
    SELECT a.id, a.name, a.asset_tag, a.next_maintenance_date,
           u.name AS assigned_to, u.email AS assigned_email,
           l.name AS location
    FROM assets a
    LEFT JOIN users u ON u.id = a.assigned_to_user
    LEFT JOIN asset_locations l ON l.id = a.location_id
    WHERE a.status IN ('active','retired')
      AND a.next_maintenance_date BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)
    ORDER BY a.next_maintenance_date ASC
");
$stmt->execute([$today, $today]);
$maintenance = $stmt->fetchAll();

if ($maintenance) {
    $rows_html = '';
    foreach ($maintenance as $a) {
        $due  = date('d M Y', strtotime($a['next_maintenance_date']));
        $days = (int)((strtotime($a['next_maintenance_date']) - strtotime($today)) / 86400);
        $rows_html .= "<tr>
            <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0'><strong>{$a['asset_tag']}</strong></td>
            <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0'>".htmlspecialchars($a['name'])."</td>
            <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0'>".htmlspecialchars($a['location'] ?? '—')."</td>
            <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0;color:".($days<=2?'#dc2626':'#d97706')."'><strong>{$due} ({$days}d)</strong></td>
        </tr>";
    }
    $body = mail_template('Maintenance Due — Action Required', "
        <p>The following assets have maintenance scheduled within the next <strong>7 days</strong>:</p>
        <table style='width:100%;border-collapse:collapse;font-size:13px;margin:16px 0'>
        <thead><tr style='background:#f5f6f8'>
          <th style='padding:8px 12px;text-align:left;font-size:11px;color:#aaa;text-transform:uppercase'>Tag</th>
          <th style='padding:8px 12px;text-align:left;font-size:11px;color:#aaa;text-transform:uppercase'>Asset</th>
          <th style='padding:8px 12px;text-align:left;font-size:11px;color:#aaa;text-transform:uppercase'>Location</th>
          <th style='padding:8px 12px;text-align:left;font-size:11px;color:#aaa;text-transform:uppercase'>Due</th>
        </tr></thead>
        <tbody>{$rows_html}</tbody>
        </table>
        <a class='btn' href='https://g2tools.greydoha.com/assets/list.php'>View Assets</a>");
    send_mail($ADMIN_EMAIL, count($maintenance).' Asset(s) Require Maintenance', $body);
    echo "[".date('Y-m-d H:i:s')."] Maintenance alert sent for ".count($maintenance)." asset(s)\n";
}

// ── Warranty expiry alerts (within 14 days) ───────────────────────────────
$stmt2 = db()->prepare("
    SELECT a.id, a.name, a.asset_tag, a.warranty_expiry,
           l.name AS location
    FROM assets a
    LEFT JOIN asset_locations l ON l.id = a.location_id
    WHERE a.status IN ('active','retired')
      AND a.warranty_expiry BETWEEN ? AND DATE_ADD(?, INTERVAL 14 DAY)
    ORDER BY a.warranty_expiry ASC
");
$stmt2->execute([$today, $today]);
$warranty = $stmt2->fetchAll();

if ($warranty) {
    $rows_html = '';
    foreach ($warranty as $a) {
        $exp  = date('d M Y', strtotime($a['warranty_expiry']));
        $days = (int)((strtotime($a['warranty_expiry']) - strtotime($today)) / 86400);
        $rows_html .= "<tr>
            <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0'><strong>{$a['asset_tag']}</strong></td>
            <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0'>".htmlspecialchars($a['name'])."</td>
            <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0'>".htmlspecialchars($a['location'] ?? '—')."</td>
            <td style='padding:8px 12px;border-bottom:1px solid #f0f0f0;color:".($days<=3?'#dc2626':'#d97706')."'><strong>{$exp} ({$days}d)</strong></td>
        </tr>";
    }
    $body = mail_template('Warranty Expiring Soon', "
        <p>The following assets have warranties expiring within the next <strong>14 days</strong>:</p>
        <table style='width:100%;border-collapse:collapse;font-size:13px;margin:16px 0'>
        <thead><tr style='background:#f5f6f8'>
          <th style='padding:8px 12px;text-align:left;font-size:11px;color:#aaa;text-transform:uppercase'>Tag</th>
          <th style='padding:8px 12px;text-align:left;font-size:11px;color:#aaa;text-transform:uppercase'>Asset</th>
          <th style='padding:8px 12px;text-align:left;font-size:11px;color:#aaa;text-transform:uppercase'>Location</th>
          <th style='padding:8px 12px;text-align:left;font-size:11px;color:#aaa;text-transform:uppercase'>Expires</th>
        </tr></thead>
        <tbody>{$rows_html}</tbody>
        </table>
        <a class='btn' href='https://g2tools.greydoha.com/assets/list.php'>View Assets</a>");
    send_mail($ADMIN_EMAIL, count($warranty).' Asset Warrant'.(count($warranty)===1?'y':'ies').' Expiring Soon', $body);
    echo "[".date('Y-m-d H:i:s')."] Warranty alert sent for ".count($warranty)." asset(s)\n";
}

if (!$maintenance && !$warranty) {
    echo "[".date('Y-m-d H:i:s')."] No alerts to send today.\n";
}
