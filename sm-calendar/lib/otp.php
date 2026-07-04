<?php
/**
 * OTP challenges for both staff and client auth.
 * subject_type/subject_id ties back to g2forms.users.id (staff) or client_contacts.id (client).
 */
function sm_otp_create(string $subject_type, int $subject_id, string $purpose = 'login'): string {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    sm_db()->prepare(
        "INSERT INTO auth_otp_challenges (subject_type, subject_id, code_hash, purpose, expires_at) VALUES (?,?,?,?,?)"
    )->execute([$subject_type, $subject_id, $hash, $purpose, $expires]);

    return $code;
}

function sm_otp_verify(string $subject_type, int $subject_id, string $purpose, string $code): bool {
    $stmt = sm_db()->prepare(
        "SELECT * FROM auth_otp_challenges
         WHERE subject_type=? AND subject_id=? AND purpose=? AND consumed_at IS NULL
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$subject_type, $subject_id, $purpose]);
    $row = $stmt->fetch();

    if (!$row) return false;
    if (strtotime($row['expires_at']) < time()) return false;
    if ($row['attempts'] >= 5) return false;

    sm_db()->prepare("UPDATE auth_otp_challenges SET attempts = attempts + 1 WHERE id=?")->execute([$row['id']]);

    if (!password_verify($code, $row['code_hash'])) return false;

    sm_db()->prepare("UPDATE auth_otp_challenges SET consumed_at = NOW() WHERE id=?")->execute([$row['id']]);
    return true;
}

/** Sends the OTP via the existing G2 Tools-style mail() pattern; logs (doesn't fail) if mail isn't configured. */
function sm_otp_send_email(string $email, string $name, string $code): bool {
    $subject = 'Your G2 SM Calendar Tool verification code';
    $body = "Hi {$name},\n\nYour one-time verification code is: {$code}\n\nThis code expires in 10 minutes.\n\nIf you didn't request this, you can ignore this email.\n\n— G2 SM Calendar Tool";
    $headers = "From: " . MAILJET_SENDER_NAME . " <" . MAILJET_SENDER_EMAIL . ">\r\n";
    $ok = @mail($email, $subject, $body, $headers);
    if (!$ok) error_log("[sm-calendar] OTP email not sent (mail not configured). Code for {$email}: {$code}");
    return $ok;
}
