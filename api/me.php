<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('GET');
$user = current_user();
if (!$user) {
    json_response(['ok' => true, 'user' => null]);
}
ensure_not_suspended($user);

json_response([
    'ok' => true,
    'user' => [
        'id' => (int)$user['id'],
        'email' => (string)$user['email'],
        'role' => (string)$user['role'],
        'suspended' => (int)$user['suspended'] === 1,
    ],
]);
