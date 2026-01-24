<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('POST');
$body = read_json_body();

$email = filter_var(trim((string)($body['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$password = (string)($body['password'] ?? '');

if (!$email || strlen($password) < 8) {
    json_response(['ok' => false, 'error' => 'Valid email and password (8+ chars) required.'], 422);
}

$pdo = pdo();
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    json_response(['ok' => false, 'error' => 'Email already registered.'], 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$insert = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (:email, :hash)');
$insert->execute(['email' => $email, 'hash' => $hash]);
$userId = (int)$pdo->lastInsertId();

login_user($userId);
log_action($userId, 'signup');

json_response([
    'ok' => true,
    'user' => ['id' => $userId, 'email' => $email, 'role' => 'user'],
]);
