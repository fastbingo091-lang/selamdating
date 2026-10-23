<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid logout request. Return to the admin panel and try again.');
}

try {
    $deletePresence = database()->prepare('DELETE FROM admin_presence WHERE admin_id = ?');
    $deletePresence->execute([(int) $_SESSION['admin_id']]);
} catch (Throwable $exception) {
    error_log('Selam admin presence cleanup failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Unable to finish admin logout right now. Please try again.');
}

unset($_SESSION['admin_id'], $_SESSION['admin_username']);
session_regenerate_id(true);
header('Location: login.php?logged_out=1');
exit;
