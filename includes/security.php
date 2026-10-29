<?php
declare(strict_types=1);

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $forwardedHttps = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || $forwardedHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
    invalidate_inactive_user_session();
}

function invalidate_inactive_user_session(): void
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
    if ($userId === false || $userId <= 0) {
        unset($_SESSION['user_id'], $_SESSION['username']);
        return;
    }

    require_once __DIR__ . '/../config/database.php';
    $statement = database()->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$userId]);
    if ($statement->fetchColumn() !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['username']);
    }
}

function destroy_app_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_token_is_valid(mixed $submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

function html_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalize_phone_number(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $phone = preg_replace('/[\s().-]/', '', trim($value));
    if (!is_string($phone) || !preg_match('/\A\+[1-9][0-9]{7,14}\z/', $phone)) {
        return null;
    }

    return $phone;
}

function password_meets_policy(mixed $password): bool
{
    return is_string($password) && preg_match('/\A[A-Za-z0-9]{6}\z/', $password) === 1;
}

function login_is_rate_limited(): bool
{
    $cutoff = time() - 900;
    $attempts = $_SESSION['login_failures'] ?? [];
    $attempts = is_array($attempts) ? $attempts : [];
    $attempts = array_values(array_filter(
        $attempts,
        static fn (mixed $attempt): bool => is_int($attempt) && $attempt > $cutoff
    ));
    $_SESSION['login_failures'] = $attempts;

    return count($attempts) >= 5;
}

function record_login_failure(): void
{
    $attempts = $_SESSION['login_failures'] ?? [];
    $attempts = is_array($attempts) ? $attempts : [];
    $attempts[] = time();
    $_SESSION['login_failures'] = $attempts;
}