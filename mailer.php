<?php
/**
 * Mailjet mailer — single entry point for all outbound email.
 * Uses Mailjet REST API v3.1 (no Composer required).
 */

define('MJ_API_KEY',    '52a50c2c536f896054b8a36ef928817c');
define('MJ_API_SECRET', 'da2bd1c30dbd521c47129c8cfddb1d6e');
define('MJ_FROM_EMAIL', 'hrdoha@greydoha.com');
define('MJ_FROM_NAME',  'G2 Tools');

/**
 * Send an email via Mailjet.
 *
 * @param string|array $to       Single email string or ['email'=>..,'name'=>..]
 * @param string       $subject
 * @param string       $html     HTML body
 * @param string       $text     Plain-text fallback (auto-stripped if empty)
 * @return bool
 */
function send_mail($to, string $subject, string $html, string $text = ''): bool {
    if (is_string($to)) {
        $to = ['Email' => $to, 'Name' => ''];
    } else {
        $to = ['Email' => $to['email'] ?? $to['Email'], 'Name' => $to['name'] ?? $to['Name'] ?? ''];
    }

    if (!$text) {
        $text = strip_tags(preg_replace('/<(br|p|li|tr)[^>]*>/i', "\n", $html));
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
    }

    $payload = json_encode([
        'Messages' => [[
            'From'     => ['Email' => MJ_FROM_EMAIL, 'Name' => MJ_FROM_NAME],
            'To'       => [$to],
            'Subject'  => $subject,
            'HTMLPart' => $html,
            'TextPart' => $text,
        ]]
    ]);

    $ch = curl_init('https://api.mailjet.com/v3.1/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_USERPWD        => MJ_API_KEY.':'.MJ_API_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200;
}

/** Branded HTML email wrapper */
function mail_template(string $title, string $body_html): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8">
<style>
  body{margin:0;padding:0;background:#f2f3f6;font-family:-apple-system,'Segoe UI',Arial,sans-serif}
  .wrap{max-width:560px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
  .hdr{background:#0d0f14;padding:24px 32px;display:flex;align-items:center;gap:12px}
  .hdr-logo{font-size:20px;font-weight:900;color:#fff;letter-spacing:-1px}
  .hdr-logo span{color:#FF3D33}
  .hdr-bar{flex:1;height:2px;background:rgba(255,61,51,.4);border-radius:2px;margin-left:8px}
  .body{padding:32px}
  .body h2{font-size:18px;font-weight:800;color:#1a1a1a;margin:0 0 8px}
  .body p{font-size:14px;color:#555;line-height:1.6;margin:0 0 16px}
  .btn{display:inline-block;padding:11px 24px;background:#FF3D33;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none}
  .info-box{background:#f8f9fb;border-left:3px solid #FF3D33;border-radius:0 8px 8px 0;padding:14px 18px;margin:16px 0;font-size:13px;color:#444}
  .info-box strong{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#aaa;margin-bottom:3px}
  .ftr{padding:20px 32px;border-top:1px solid #f0f0f0;font-size:11px;color:#bbb;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <div class="hdr-logo">G2<span>Tools</span></div>
    <div class="hdr-bar"></div>
  </div>
  <div class="body">
    <h2>{$title}</h2>
    {$body_html}
  </div>
  <div class="ftr">G2 Group &mdash; This is an automated message, please do not reply.</div>
</div>
</body>
</html>
HTML;
}
