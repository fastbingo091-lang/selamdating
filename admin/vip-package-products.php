<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_auth.php';

require_active_admin();
header('Location: products.php');
exit;
