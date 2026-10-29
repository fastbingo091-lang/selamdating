<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/database.php';

start_app_session();
$appRoot = isset($appRoot) && is_string($appRoot) ? $appRoot : '';
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'Selam Market';
$activeNav = isset($activeNav) && is_string($activeNav) ? $activeNav : 'home';
$pageDescription = isset($pageDescription) && is_string($pageDescription) ? $pageDescription : '';
$pageClass = isset($pageClass) && is_string($pageClass) ? $pageClass : '';
$unreadNotificationCount = 0;
if (isset($_SESSION['user_id']) && (!isset($databaseConnected) || $databaseConnected === true)) {
    $unreadQuery = database()->prepare("
        SELECT COUNT(*)
        FROM user_notifications n
        LEFT JOIN user_notification_reads r
            ON r.notification_id = n.id AND r.user_id = :read_user_id
        WHERE (n.recipient_user_id IS NULL OR n.recipient_user_id = :recipient_user_id)
          AND r.notification_id IS NULL
    ");
    $unreadQuery->execute([
        'read_user_id' => (int) $_SESSION['user_id'],
        'recipient_user_id' => (int) $_SESSION['user_id'],
    ]);
    $unreadNotificationCount = (int) $unreadQuery->fetchColumn();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0b0f">
    <meta name="description" content="<?= html_escape($pageDescription !== '' ? $pageDescription : 'Selam Market') ?>">
    <title><?= html_escape($pageTitle) ?> | Selam Market</title>
    <link rel="icon" type="image/svg+xml" href="<?= html_escape($appRoot . 'assets/images/selam-icon.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= html_escape($appRoot . 'assets/css/style.css?v=selam-5') ?>" rel="stylesheet">
    <link href="<?= html_escape($appRoot . 'assets/css/tiktok-app.css?v=market-9') ?>" rel="stylesheet">
</head>
<body class="<?= html_escape($pageClass) ?>">
<div class="app-shell">
    <header class="app-topbar">
        <?php $brandHref = $appRoot . './'; require __DIR__ . '/brand.php'; ?>
        <div class="topbar-actions">
            <?php if (isset($_SESSION['username']) && is_string($_SESSION['username'])): ?>
                <a class="topbar-notifications" href="<?= html_escape($appRoot . 'notifications.php') ?>" aria-label="Notifications<?= $unreadNotificationCount > 0 ? ', ' . $unreadNotificationCount . ' unread' : '' ?>">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    <?php if ($unreadNotificationCount > 0): ?><span class="notification-count"><?= $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount ?></span><?php endif; ?>
                </a>
                <a class="topbar-profile desktop-account-link" href="<?= html_escape($appRoot . 'team.php') ?>" aria-label="My team, Selam user ID #<?= (int) ($_SESSION['user_id'] ?? 0) ?>"><i class="bi bi-person-circle" aria-hidden="true"></i><span><?= html_escape($_SESSION['username']) ?><small class="topbar-user-id">#<?= (int) ($_SESSION['user_id'] ?? 0) ?></small></span></a>
                <details class="mobile-account-menu">
                    <summary aria-label="Open account menu">
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                    </summary>
                    <div class="mobile-account-menu-panel">
                        <div class="mobile-account-identity">
                            <strong><?= html_escape($_SESSION['username']) ?></strong>
                            <small>User #<?= (int) ($_SESSION['user_id'] ?? 0) ?></small>
                        </div>
                        <a href="<?= html_escape($appRoot . 'profile.php') ?>">
                            <i class="bi bi-person-gear" aria-hidden="true"></i>
                            Edit profile
                        </a>
                        <form method="post" action="<?= html_escape($appRoot . 'logout.php') ?>">
                            <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                            <button type="submit">
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                Log out
                            </button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <a href="<?= html_escape($appRoot . 'auth/login.php') ?>" class="gb-login-btn">
                    <span>Log in</span>
                </a>
                <a href="<?= html_escape($appRoot . 'auth/register.php') ?>" class="gb-signup-btn">
                    <span>Sign up</span>
                    <span class="gb-gift" aria-hidden="true">🎁</span>
                </a>
            <?php endif; ?>
        </div>
    </header>
    <main class="app-main">
        <?php if ($pageTitle !== ''): ?>
            <div class="page-heading">
                <div>
                    <div class="eyebrow">Selam · Market</div>
                    <h1><?= html_escape($pageTitle) ?></h1>
                    <?php if ($pageDescription !== ''): ?><p><?= html_escape($pageDescription) ?></p><?php endif; ?>
                </div>
                <span class="demo-badge"><i class="bi bi-bag-check" aria-hidden="true"></i> Buy once, earn daily</span>
            </div>
        <?php endif; ?>