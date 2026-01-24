<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('POST');
$user = require_auth();
$userId = (int)$user['id'];

$pdo = pdo();
$code = generate_session_code();

$insert = $pdo->prepare('INSERT INTO sessions (master_user_id, code) VALUES (:master_user_id, :code)');
$insert->execute(['master_user_id' => $userId, 'code' => $code]);
$sessionId = (int)$pdo->lastInsertId();

$member = $pdo->prepare('INSERT INTO session_members (session_id, user_id, role) VALUES (:session_id, :user_id, :role)');
$member->execute(['session_id' => $sessionId, 'user_id' => $userId, 'role' => 'master']);

log_action($userId, 'session_created', ['session_id' => $sessionId, 'code' => $code]);

json_response([
    'ok' => true,
    'session' => [
        'id' => $sessionId,
        'code' => $code,
        'role' => 'master',
    ],
]);
