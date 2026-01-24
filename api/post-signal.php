<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('POST');
$user = require_auth();
$body = read_json_body();

$sessionId = (int)($body['session_id'] ?? 0);
$targetRole = (string)($body['target_role'] ?? '');
$signalType = trim((string)($body['signal_type'] ?? ''));
$signalData = $body['signal_data'] ?? null;

if ($sessionId <= 0 || ($targetRole !== 'master' && $targetRole !== 'sub') || $signalType === '' || !is_array($signalData)) {
    json_response(['ok' => false, 'error' => 'session_id, target_role, signal_type, and signal_data array are required.'], 422);
}

$session = fetch_session($sessionId);
if (!$session) {
    json_response(['ok' => false, 'error' => 'Session not found.'], 404);
}
validate_session_active($session);

$sourceRole = session_role($sessionId, (int)$user['id']);
if (!$sourceRole) {
    json_response(['ok' => false, 'error' => 'Session permission denied.'], 403);
}

if ($sourceRole === $targetRole) {
    json_response(['ok' => false, 'error' => 'Signals must target the opposite role.'], 422);
}

$stmt = pdo()->prepare(
    'INSERT INTO signals (session_id, source_role, target_role, signal_type, signal_data)
     VALUES (:session_id, :source_role, :target_role, :signal_type, :signal_data)'
);
$stmt->execute([
    'session_id' => $sessionId,
    'source_role' => $sourceRole,
    'target_role' => $targetRole,
    'signal_type' => $signalType,
    'signal_data' => json_encode($signalData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
]);

json_response(['ok' => true]);
