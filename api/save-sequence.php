<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('POST');
$user = require_auth();
$body = read_json_body();

$name = trim((string)($body['name'] ?? ''));
$sequence = $body['sequence'] ?? null;

if ($name === '' || !is_array($sequence) || $sequence === []) {
    json_response(['ok' => false, 'error' => 'name and non-empty sequence are required.'], 422);
}

$stmt = pdo()->prepare('INSERT INTO command_sequences (user_id, name, sequence_json) VALUES (:user_id, :name, :sequence_json)');
$stmt->execute([
    'user_id' => (int)$user['id'],
    'name' => $name,
    'sequence_json' => json_encode($sequence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
]);

$sequenceId = (int)pdo()->lastInsertId();
log_action((int)$user['id'], 'sequence_saved', ['sequence_id' => $sequenceId]);

json_response([
    'ok' => true,
    'sequence' => [
        'id' => $sequenceId,
        'name' => $name,
    ],
]);
