<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('GET');
$user = require_auth();

$stmt = pdo()->prepare('SELECT id, name, sequence_json, created_at FROM command_sequences WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 50');
$stmt->execute(['user_id' => (int)$user['id']]);
$rows = $stmt->fetchAll();

$sequences = array_map(static function (array $row): array {
    return [
        'id' => (int)$row['id'],
        'name' => (string)$row['name'],
        'sequence' => json_decode((string)$row['sequence_json'], true),
        'created_at' => $row['created_at'],
    ];
}, $rows);

json_response([
    'ok' => true,
    'sequences' => $sequences,
]);
