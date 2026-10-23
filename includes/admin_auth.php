<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/database.php';

function require_active_admin(): int
{
    start_app_session();
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0) {
        header('Location: login.php');
        exit;
    }

    $statement = database()->prepare('SELECT status FROM admins WHERE id = ? LIMIT 1');
    $statement->execute([$adminId]);
    if ($statement->fetchColumn() !== 'active') {
        unset($_SESSION['admin_id'], $_SESSION['admin_username']);
        header('Location: login.php?disabled=1');
        exit;
    }

    return $adminId;
}
