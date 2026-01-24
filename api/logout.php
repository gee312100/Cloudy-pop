<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

require_method('POST');
$user = current_user();
if ($user) {
    log_action((int)$user['id'], 'logout');
}
logout_user();

json_response(['ok' => true]);
