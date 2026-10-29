<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$username = $argv[1] ?? '';
if (!is_string($username) || preg_match('/\A[A-Za-z0-9_]{3,50}\z/', $username) !== 1) {
    fwrite(STDERR, "Usage: php docker/create_admin.php <username>\n");
    exit(2);
}

$terminalState = shell_exec('stty -g 2>/dev/null');
if (!is_string($terminalState) || trim($terminalState) === '') {
    fwrite(STDERR, "Run this command from an interactive terminal so the password can be entered securely.\n");
    exit(2);
}

shell_exec('stty -echo');
try {
    fwrite(STDOUT, "Admin password (minimum 12 characters): ");
    $password = fgets(STDIN);
    fwrite(STDOUT, "\nConfirm password: ");
    $passwordConfirmation = fgets(STDIN);
} finally {
    shell_exec('stty echo');
    fwrite(STDOUT, "\n");
}

$password = is_string($password) ? rtrim($password, "\r\n") : '';
$passwordConfirmation = is_string($passwordConfirmation) ? rtrim($passwordConfirmation, "\r\n") : '';
if (strlen($password) < 12 || strlen($password) > 200 || !hash_equals($password, $passwordConfirmation)) {
    fwrite(STDERR, "Passwords must match and be between 12 and 200 characters.\n");
    exit(2);
}

try {
    $insert = database()->prepare('INSERT INTO admins (username, password_hash, status) VALUES (?, ?, "active")');
    $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Admin account created. Sign in at /admin/login.php.\n");
} catch (PDOException $exception) {
    error_log('Unable to create deployment admin: ' . $exception->getMessage());
    fwrite(STDERR, "Unable to create the admin account. Check the database connection and whether the username is already in use.\n");
    exit(1);
}
