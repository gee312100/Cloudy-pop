<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('POST');
$user = require_auth();
$body = read_json_body();

$code = preg_replace('/\D+/', '', (string)($body['code'] ?? ''));
if (!$code || strlen($code) !== 6) {
    json_response(['ok' => false, 'error' => 'A 6-digit master code is required.'], 422);
}

$pdo = pdo();
$stmt = $pdo->prepare('SELECT id, master_user_id, code, status FROM sessions WHERE code = :code');
$stmt->execute(['code' => $code]);
$session = $stmt->fetch();

if (!$session) {
    json_response(['ok' => false, 'error' => 'Session not found.'], 404);
}
validate_session_active($session);

$sessionId = (int)$session['id'];
$userId = (int)$user['id'];

$role = session_role($sessionId, $userId);
if ($role !== 'sub') {
    $insert = $pdo->prepare(
        'INSERT INTO session_members (session_id, user_id, role) VALUES (:session_id, :user_id, :role)
         ON DUPLICATE KEY UPDATE role = VALUES(role)'
    );
    $insert->execute(['session_id' => $sessionId, 'user_id' => $userId, 'role' => 'sub']);
}

log_action($userId, 'session_joined', ['session_id' => $sessionId, 'code' => $code]);

json_response([
    'ok' => true,
    'session' => [
        'id' => $sessionId,
        'code' => (string)$session['code'],
        'role' => 'sub',
    ],
]);
