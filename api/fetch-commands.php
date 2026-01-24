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
require_session_role($sessionId, (int)$user['id'], 'sub');

$stmt = pdo()->prepare(
    'SELECT id, type, payload, execute_at, created_at
     FROM commands
     WHERE session_id = :session_id AND id > :last_id
     ORDER BY id ASC
     LIMIT 100'
);
$stmt->execute(['session_id' => $sessionId, 'last_id' => $lastId]);
$commands = $stmt->fetchAll();

$normalized = array_map(static function (array $command): array {
    return [
        'id' => (int)$command['id'],
        'type' => (string)$command['type'],
        'payload' => $command['payload'] ? json_decode((string)$command['payload'], true) : null,
        'execute_at' => $command['execute_at'],
        'created_at' => $command['created_at'],
    ];
}, $commands);

log_action((int)$user['id'], 'commands_polled', ['session_id' => $sessionId, 'count' => count($normalized)]);

json_response([
    'ok' => true,
    'commands' => $normalized,
    'last_id' => $normalized ? end($normalized)['id'] : $lastId,
    'server_time' => date(DATE_ATOM),
]);
