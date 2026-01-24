<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('POST');
$admin = require_admin();
$body = read_json_body();

$userId = (int)($body['user_id'] ?? 0);
$suspended = (int)($body['suspended'] ?? 1);

if ($userId <= 0) {
    json_response(['ok' => false, 'error' => 'user_id is required.'], 422);
}

$stmt = pdo()->prepare('UPDATE users SET suspended = :suspended WHERE id = :id');
$stmt->execute(['suspended' => $suspended === 1 ? 1 : 0, 'id' => $userId]);

log_action((int)$admin['id'], 'admin_suspend', ['target_user_id' => $userId, 'suspended' => $suspended === 1]);

json_response(['ok' => true]);
