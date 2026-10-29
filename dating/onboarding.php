<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';

start_app_session();
header('Location: ../products.php');
exit;
