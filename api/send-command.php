<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('POST');
$user = require_auth();
$body = read_json_body();

$sessionId = (int)($body['session_id'] ?? 0);
$type = trim((string)($body['type'] ?? ''));
$payload = $body['payload'] ?? null;
$executeAtRaw = $body['execute_at'] ?? null;

if ($sessionId <= 0 || $type === '') {
    json_response(['ok' => false, 'error' => 'session_id and type are required.'], 422);
}

$session = fetch_session($sessionId);
if (!$session) {
    json_response(['ok' => false, 'error' => 'Session not found.'], 404);
}
validate_session_active($session);
require_session_role($sessionId, (int)$user['id'], 'master');

$executeAt = null;
if ($executeAtRaw) {
    $timestamp = strtotime((string)$executeAtRaw);
    if ($timestamp !== false) {
        $executeAt = date('Y-m-d H:i:s', $timestamp);
    }
}

$stmt = pdo()->prepare('INSERT INTO commands (session_id, type, payload, execute_at) VALUES (:session_id, :type, :payload, :execute_at)');
$stmt->execute([
    'session_id' => $sessionId,
    'type' => $type,
    'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
    'execute_at' => $executeAt,
]);

$commandId = (int)pdo()->lastInsertId();
log_action((int)$user['id'], 'command_sent', ['session_id' => $sessionId, 'type' => $type, 'command_id' => $commandId]);

json_response([
    'ok' => true,
    'command' => [
        'id' => $commandId,
        'session_id' => $sessionId,
        'type' => $type,
        'payload' => $payload,
        'execute_at' => $executeAt,
    ],
]);
