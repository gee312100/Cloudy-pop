<?php
declare(strict_types=1);

session_start();

set_exception_handler(function (Throwable $exception): void {
    error_log('[cloudypop] Unhandled exception: ' . $exception->getMessage());
    json_response(['ok' => false, 'error' => 'Server error.'], 500);
});

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_value('DB_HOST', 'db');
    $port = env_value('DB_PORT', '3306');
    $name = env_value('DB_NAME', 'cloudypop');
    $user = env_value('DB_USER', 'cloudypop');
    $pass = env_value('DB_PASS', 'cloudypop');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST ?: [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    return $decoded;
}

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId)) {
        return null;
    }

    $stmt = pdo()->prepare('SELECT id, email, role, suspended, created_at FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function login_user(int $userId): void
{
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function request_ip(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (is_string($ip) && str_contains($ip, ',')) {
        $parts = array_map('trim', explode(',', $ip));
        return $parts[0] ?: 'unknown';
    }
    return is_string($ip) && $ip !== '' ? $ip : 'unknown';
}

function request_user_agent(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    return is_string($ua) && $ua !== '' ? substr($ua, 0, 255) : 'unknown';
}

function log_action(?int $userId, string $action, ?array $details = null): void
{
    $stmt = pdo()->prepare(
        'INSERT INTO access_logs (user_id, action, ip_address, user_agent, details) VALUES (:user_id, :action, :ip, :ua, :details)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'action' => $action,
        'ip' => request_ip(),
        'ua' => request_user_agent(),
        'details' => $details ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function ensure_not_suspended(array $user): void
{
    if ((int)($user['suspended'] ?? 0) === 1) {
        log_action((int)$user['id'], 'blocked_suspended');
        json_response(['ok' => false, 'error' => 'Account suspended.'], 403);
    }
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
    }
    ensure_not_suspended($user);
    return $user;
}

function require_admin(): array
{
    $user = require_auth();
    if (($user['role'] ?? 'user') !== 'admin') {
        json_response(['ok' => false, 'error' => 'Admin role required.'], 403);
    }
    return $user;
}

function generate_session_code(): string
{
    $pdo = pdo();
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $code = (string)random_int(100000, 999999);
        $stmt = $pdo->prepare('SELECT id FROM sessions WHERE code = :code');
        $stmt->execute(['code' => $code]);
        if (!$stmt->fetch()) {
            return $code;
        }
    }

    json_response(['ok' => false, 'error' => 'Unable to generate session code.'], 500);
}

function session_role(int $sessionId, int $userId): ?string
{
    $stmt = pdo()->prepare('SELECT role FROM session_members WHERE session_id = :session_id AND user_id = :user_id');
    $stmt->execute(['session_id' => $sessionId, 'user_id' => $userId]);
    $row = $stmt->fetch();
    return $row['role'] ?? null;
}

function require_session_role(int $sessionId, int $userId, string $expectedRole): void
{
    $role = session_role($sessionId, $userId);
    if ($role !== $expectedRole) {
        json_response(['ok' => false, 'error' => 'Session permission denied.'], 403);
    }
}

function fetch_session(int $sessionId): ?array
{
    $stmt = pdo()->prepare('SELECT id, master_user_id, code, status, created_at, updated_at FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $session = $stmt->fetch();
    return $session ?: null;
}

function validate_session_active(array $session): void
{
    if (($session['status'] ?? 'closed') !== 'active') {
        json_response(['ok' => false, 'error' => 'Session is closed.'], 400);
    }
}
