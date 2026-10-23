<?php
require_once __DIR__ . '/security.php';

start_app_session();
$appRoot = isset($appRoot) && is_string($appRoot) ? $appRoot : '';
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'Selam Dating App';
$activeNav = isset($activeNav) && is_string($activeNav) ? $activeNav : 'home';
$pageDescription = isset($pageDescription) && is_string($pageDescription) ? $pageDescription : '';
$pageClass = isset($pageClass) && is_string($pageClass) ? $pageClass : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0b0f">
    <meta name="description" content="<?= html_escape($pageDescription !== '' ? $pageDescription : 'Selam Dating App') ?>">
    <title><?= html_escape($pageTitle) ?> | Selam Dating App</title>
    <link rel="icon" type="image/svg+xml" href="<?= html_escape($appRoot . 'assets/images/selam-icon.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= html_escape($appRoot . 'assets/css/style.css?v=selam-3') ?>" rel="stylesheet">
    <link href="<?= html_escape($appRoot . 'assets/css/tiktok-app.css?v=selam-1') ?>" rel="stylesheet">
    <?php if ($pageClass === 'tiktok-discover-page'): ?>
        <link href="<?= html_escape($appRoot . 'assets/css/tiktok-discover.css?v=selam-4') ?>" rel="stylesheet">
        <script src="<?= html_escape($appRoot . 'assets/js/tiktok-discover.js?v=selam-1') ?>" defer></script>
    <?php endif; ?>
</head>
<body class="<?= html_escape($pageClass) ?>">
<div class="app-shell">
    <header class="app-topbar">
        <?php $brandHref = $appRoot . './'; require __DIR__ . '/brand.php'; ?>
        <div class="topbar-actions">
            <?php if (isset($_SESSION['username']) && is_string($_SESSION['username'])): ?>
                <a class="topbar-profile" href="<?= html_escape($appRoot . 'profile.php') ?>" aria-label="Profile, Selam user ID #<?= (int) ($_SESSION['user_id'] ?? 0) ?>"><i class="bi bi-person-circle" aria-hidden="true"></i><span><?= html_escape($_SESSION['username']) ?><small class="topbar-user-id">#<?= (int) ($_SESSION['user_id'] ?? 0) ?></small></span></a>
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
                    <div class="eyebrow">Selam · Dating App</div>
                    <h1><?= html_escape($pageTitle) ?></h1>
                    <?php if ($pageDescription !== ''): ?><p><?= html_escape($pageDescription) ?></p><?php endif; ?>
                </div>
                <span class="demo-badge"><i class="bi bi-heart" aria-hidden="true"></i> Made for connection</span>
            </div>
        <?php endif; ?>