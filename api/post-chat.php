<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('POST');
$user = require_auth();
$body = read_json_body();

$sessionId = (int)($body['session_id'] ?? 0);
$message = trim((string)($body['message'] ?? ''));

if ($sessionId <= 0 || $message === '') {
    json_response(['ok' => false, 'error' => 'session_id and message are required.'], 422);
}

$session = fetch_session($sessionId);
if (!$session) {
    json_response(['ok' => false, 'error' => 'Session not found.'], 404);
}
validate_session_active($session);
require_session_role($sessionId, (int)$user['id'], 'master');

$stmt = pdo()->prepare('INSERT INTO chats (session_id, sender_role, message) VALUES (:session_id, :role, :message)');
$stmt->execute([
    'session_id' => $sessionId,
    'role' => 'master',
    'message' => $message,
]);

$chatId = (int)pdo()->lastInsertId();
log_action((int)$user['id'], 'chat_sent', ['session_id' => $sessionId, 'chat_id' => $chatId]);

json_response([
    'ok' => true,
    'chat' => [
        'id' => $chatId,
        'session_id' => $sessionId,
        'sender_role' => 'master',
        'message' => $message,
    ],
]);
