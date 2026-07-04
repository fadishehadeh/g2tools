<?php
function sm_password_errors(string $pw): array {
    $errors = [];
    if (strlen($pw) < 10)           $errors[] = 'At least 10 characters';
    if (!preg_match('/[A-Z]/', $pw)) $errors[] = 'At least 1 uppercase letter';
    if (!preg_match('/[a-z]/', $pw)) $errors[] = 'At least 1 lowercase letter';
    if (!preg_match('/[0-9]/', $pw)) $errors[] = 'At least 1 number';
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) $errors[] = 'At least 1 special character';
    return $errors;
}

function sm_password_valid(string $pw): bool {
    return empty(sm_password_errors($pw));
}
