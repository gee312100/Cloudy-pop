<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('POST');
$body = read_json_body();

$email = filter_var(trim((string)($body['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$password = (string)($body['password'] ?? '');

if (!$email || $password === '') {
    json_response(['ok' => false, 'error' => 'Email and password required.'], 422);
}

$stmt = pdo()->prepare('SELECT id, email, password_hash, role, suspended FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, (string)$user['password_hash'])) {
    log_action(null, 'login_failed', ['email' => $email]);
    json_response(['ok' => false, 'error' => 'Invalid credentials.'], 401);
}

ensure_not_suspended($user);
login_user((int)$user['id']);
log_action((int)$user['id'], 'login');

json_response([
    'ok' => true,
    'user' => [
        'id' => (int)$user['id'],
        'email' => (string)$user['email'],
        'role' => (string)$user['role'],
        'suspended' => (int)$user['suspended'] === 1,
    ],
]);
