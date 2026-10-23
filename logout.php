<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';

start_app_session();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid logout request. Return to the login page and try again.');
}

destroy_app_session();
header('Location: login.php?logged_out=1');
exit;