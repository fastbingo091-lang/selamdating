<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

header('Location: ../products.php');
exit;
