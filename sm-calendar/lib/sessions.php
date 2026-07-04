<?php
const SM_SESSION_TTL_DAYS = 7;

function sm_create_staff_session(int $userId): string {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + SM_SESSION_TTL_DAYS * 86400);
    sm_db()->prepare(
        "INSERT INTO staff_sessions (id,user_id,ip_address,user_agent,expires_at) VALUES (?,?,?,?,?)"
    )->execute([$token, $userId, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $expires]);
    return $token;
}

function sm_create_client_session(int $contactId): string {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + SM_SESSION_TTL_DAYS * 86400);
    sm_db()->prepare(
        "INSERT INTO client_sessions (id,contact_id,ip_address,user_agent,expires_at) VALUES (?,?,?,?,?)"
    )->execute([$token, $contactId, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $expires]);
    return $token;
}

function sm_destroy_staff_session(string $token): void {
    sm_db()->prepare("DELETE FROM staff_sessions WHERE id=?")->execute([$token]);
}

function sm_destroy_client_session(string $token): void {
    sm_db()->prepare("DELETE FROM client_sessions WHERE id=?")->execute([$token]);
}

/** Resolves a staff session token into the full staff record (joined from g2forms.users) + sm_role. */
function sm_resolve_staff_session(string $token): ?array {
    $stmt = sm_db()->prepare(
        "SELECT s.user_id, s.expires_at FROM staff_sessions s WHERE s.id=?"
    );
    $stmt->execute([$token]);
    $sess = $stmt->fetch();
    if (!$sess || strtotime($sess['expires_at']) < time()) return null;

    $u = sm_db()->prepare("SELECT id,name,email,role FROM " . g2_users_table() . " WHERE id=?");
    $u->execute([$sess['user_id']]);
    $user = $u->fetch();
    if (!$user) return null;

    $a = sm_db()->prepare("SELECT sm_role FROM sm_user_access WHERE user_id=?");
    $a->execute([$user['id']]);
    $access = $a->fetch();
    if (!$access) return null; // access revoked since session was issued

    $user['sm_role'] = $access['sm_role'];
    return $user;
}

function sm_resolve_client_session(string $token): ?array {
    $stmt = sm_db()->prepare(
        "SELECT c.id,c.client_id,c.name,c.email,c.must_change_password, cs.expires_at
         FROM client_sessions cs JOIN client_contacts c ON c.id = cs.contact_id
         WHERE cs.id=?"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row || strtotime($row['expires_at']) < time()) return null;
    unset($row['expires_at']);
    return $row;
}
