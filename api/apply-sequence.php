<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('POST');
$user = require_auth();
$body = read_json_body();

$sessionId = (int)($body['session_id'] ?? 0);
$sequenceId = (int)($body['sequence_id'] ?? 0);
$startAtRaw = $body['start_at'] ?? null;

if ($sessionId <= 0 || $sequenceId <= 0) {
    json_response(['ok' => false, 'error' => 'session_id and sequence_id are required.'], 422);
}

$session = fetch_session($sessionId);
if (!$session) {
    json_response(['ok' => false, 'error' => 'Session not found.'], 404);
}
validate_session_active($session);
require_session_role($sessionId, (int)$user['id'], 'master');

$stmt = pdo()->prepare('SELECT id, name, sequence_json FROM command_sequences WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $sequenceId, 'user_id' => (int)$user['id']]);
$row = $stmt->fetch();
if (!$row) {
    json_response(['ok' => false, 'error' => 'Sequence not found.'], 404);
}

$sequence = json_decode((string)$row['sequence_json'], true);
if (!is_array($sequence) || $sequence === []) {
    json_response(['ok' => false, 'error' => 'Sequence is empty.'], 400);
}

$startTimestamp = $startAtRaw ? strtotime((string)$startAtRaw) : time();
if ($startTimestamp === false) {
    $startTimestamp = time();
}

$pdo = pdo();
$insert = $pdo->prepare('INSERT INTO commands (session_id, type, payload, execute_at) VALUES (:session_id, :type, :payload, :execute_at)');

$currentTime = $startTimestamp;
$created = [];
foreach ($sequence as $item) {
    if (!is_array($item)) {
        continue;
    }
    $type = trim((string)($item['type'] ?? ''));
    if ($type === '') {
        continue;
    }

    $delay = isset($item['delay']) ? max(0, (int)$item['delay']) : 0;
    $currentTime += $delay;
    $executeAt = date('Y-m-d H:i:s', $currentTime);
    $payload = $item['payload'] ?? null;

    $insert->execute([
        'session_id' => $sessionId,
        'type' => $type,
        'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        'execute_at' => $executeAt,
    ]);

    $created[] = [
        'id' => (int)$pdo->lastInsertId(),
        'type' => $type,
        'execute_at' => $executeAt,
    ];
}

log_action((int)$user['id'], 'sequence_applied', ['session_id' => $sessionId, 'sequence_id' => $sequenceId, 'count' => count($created)]);

json_response([
    'ok' => true,
    'created' => $created,
]);
