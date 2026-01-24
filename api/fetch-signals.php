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

$targetRole = session_role($sessionId, (int)$user['id']);
if (!$targetRole) {
    json_response(['ok' => false, 'error' => 'Session permission denied.'], 403);
}

$pdo = pdo();
$pdo->beginTransaction();

$stmt = $pdo->prepare(
    'SELECT id, source_role, signal_type, signal_data, created_at
     FROM signals
     WHERE session_id = :session_id AND target_role = :target_role AND consumed = 0 AND id > :last_id
     ORDER BY id ASC
     LIMIT 100'
);
$stmt->execute(['session_id' => $sessionId, 'target_role' => $targetRole, 'last_id' => $lastId]);
$rows = $stmt->fetchAll();

if ($rows) {
    $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $update = $pdo->prepare("UPDATE signals SET consumed = 1 WHERE id IN ($placeholders)");
    $update->execute($ids);
}

$pdo->commit();

$signals = array_map(static function (array $row): array {
    return [
        'id' => (int)$row['id'],
        'source_role' => (string)$row['source_role'],
        'signal_type' => (string)$row['signal_type'],
        'signal_data' => json_decode((string)$row['signal_data'], true),
        'created_at' => $row['created_at'],
    ];
}, $rows);

json_response([
    'ok' => true,
    'signals' => $signals,
    'last_id' => $signals ? end($signals)['id'] : $lastId,
]);
