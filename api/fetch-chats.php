<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('GET');
$user = require_auth();

$sessionId = (int)($_GET['session_id'] ?? 0);
$lastId = (int)($_GET['last_id'] ?? 0);

if ($sessionId <= 0) {
    json_response(['ok' => false, 'error' => 'session_id is required.'], 422);
}

$session = fetch_session($sessionId);
if (!$session) {
    json_response(['ok' => false, 'error' => 'Session not found.'], 404);
}
validate_session_active($session);

$role = session_role($sessionId, (int)$user['id']);
if (!$role) {
    json_response(['ok' => false, 'error' => 'Session permission denied.'], 403);
}

$stmt = pdo()->prepare(
    'SELECT id, sender_role, message, created_at
     FROM chats
     WHERE session_id = :session_id AND id > :last_id
     ORDER BY id ASC
     LIMIT 200'
);
$stmt->execute(['session_id' => $sessionId, 'last_id' => $lastId]);
$rows = $stmt->fetchAll();

$chats = array_map(static function (array $chat): array {
    return [
        'id' => (int)$chat['id'],
        'sender_role' => (string)$chat['sender_role'],
        'message' => (string)$chat['message'],
        'created_at' => $chat['created_at'],
    ];
}, $rows);

json_response([
    'ok' => true,
    'chats' => $chats,
    'last_id' => $chats ? end($chats)['id'] : $lastId,
]);
