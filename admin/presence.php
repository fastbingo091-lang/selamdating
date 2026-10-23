<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit;
}

try {
    $db = database();
    $adminStatus = $db->prepare('SELECT status FROM admins WHERE id = ? LIMIT 1');
    $adminStatus->execute([(int) $_SESSION['admin_id']]);
    if ($adminStatus->fetchColumn() !== 'active') {
        http_response_code(403);
        exit;
    }

    $presence = $db->prepare("
        INSERT INTO admin_presence (admin_id, last_seen_at)
        VALUES (?, NOW())
        ON DUPLICATE KEY UPDATE last_seen_at = NOW()
    ");
    $presence->execute([(int) $_SESSION['admin_id']]);
    http_response_code(204);
} catch (Throwable $exception) {
    error_log('Selam admin presence update failed: ' . $exception->getMessage());
    http_response_code(503);
}
