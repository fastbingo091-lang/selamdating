<?php

declare(strict_types=1);

require_once __DIR__ . '/admin_auth.php';

$activeAdminId = require_active_admin();

$adminTitle = isset($adminTitle) && is_string($adminTitle)
    ? $adminTitle
    : 'Admin';

$adminActive = isset($adminActive) && is_string($adminActive)
    ? $adminActive
    : '';

$adminRoot = isset($adminRoot) && is_string($adminRoot)
    ? $adminRoot
    : '';

$adminUsername = (string) (
    $_SESSION['admin_username'] ?? 'Admin'
);

$adminNavigation = [

    'dashboard' => [
        'label' => 'Dashboard',
        'icon'  => 'bi-grid-1x2',
        'href'  => 'dashboard.php',
    ],

    'users' => [
        'label' => 'Users',
        'icon'  => 'bi-people',
        'href'  => 'users.php',
    ],

    'notifications' => [
        'label' => 'Notifications',
        'icon'  => 'bi-bell',
        'href'  => 'notifications.php',
    ],

    'rewards' => [
        'label' => 'Rewards',
        'icon'  => 'bi-gift',
        'href'  => 'rewards.php',
    ],

    'wallets' => [
        'label' => 'Wallets',
        'icon'  => 'bi-wallet2',
        'href'  => 'wallets.php',
    ],

    'products' => [
        'label' => 'Products',
        'icon'  => 'bi-box-seam',
        'href'  => 'products.php',
    ],

    'profit-claims' => [
        'label' => 'Profit Claims',
        'icon'  => 'bi-cash-stack',
        'href'  => 'profit-claims.php',
    ],

    'deposits' => [
        'label' => 'Deposit Requests',
        'icon'  => 'bi-cash-stack',
        'href'  => 'deposit-requests.php',
    ],

    'agent-deposit' => [
        'label' => 'Agent Deposit',
        'icon'  => 'bi-cash-coin',
        'href'  => 'agent-deposit.php',
    ],

    'withdrawals' => [
        'label' => 'Withdrawal Requests',
        'icon'  => 'bi-bank',
        'href'  => 'withdrawal-requests.php',
    ],

    'transactions' => [
        'label' => 'Transactions',
        'icon'  => 'bi-arrow-left-right',
        'href'  => 'transactions.php',
    ],

    'logs' => [
        'label' => 'Activity Logs',
        'icon'  => 'bi-journal-text',
        'href'  => 'logs.php',
    ],

    'settings' => [
        'label' => 'Settings',
        'icon'  => 'bi-gear',
        'href'  => 'settings.php',
    ],

];

?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="theme-color"
        content="#0b0b0f"
    >

    <meta
        name="description"
        content="Selam Market administration panel"
    >

    <title>
        <?= html_escape($adminTitle) ?> · Selam Admin
    </title>

    <link rel="icon" type="image/svg+xml" href="../assets/images/selam-icon.svg">

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=selam-5"
    >
    <link
        rel="stylesheet"
        href="../assets/css/tiktok-app.css?v=market-4"
    >
    <link
        rel="stylesheet"
        href="../assets/css/tiktok-admin.css?v=market-2"
    >

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        defer
    ></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const endpoint = <?= json_encode($adminRoot . 'presence.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const token = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const heartbeat = () => {
                if (!navigator.sendBeacon(endpoint, new URLSearchParams({ csrf_token: token }))) {
                    console.error('Unable to queue the admin presence update.');
                }
            };
            heartbeat();
            window.setInterval(heartbeat, 60000);
        });
    </script>

</head>

<body>

<div class="admin-layout">

    <aside class="admin-sidebar">

        <?php

        $appRoot = '../';

        $brandHref = $adminRoot . 'dashboard.php';

        require __DIR__ . '/brand.php';

        ?>

        <span class="small-note px-2 mb-2">
            OPERATIONS
        </span>

        <nav
            class="admin-nav"
            aria-label="Admin navigation"
        >

            <?php foreach ($adminNavigation as $key => $item): ?>

                <a
                    href="<?= html_escape($adminRoot . $item['href']) ?>"
                    class="<?= $adminActive === $key ? 'active' : '' ?>"
                    <?= $adminActive === $key
                        ? 'aria-current="page"'
                        : '' ?>
                >

                    <i
                        class="bi <?= html_escape($item['icon']) ?>"
                        aria-hidden="true"
                    ></i>

                    <span>
                        <?= html_escape($item['label']) ?>
                    </span>

                </a>

            <?php endforeach; ?>

        </nav>

        <div class="mt-auto">

            <div class="small-note px-2 mb-2">

                Signed in as

                <strong>
                    <?= html_escape($adminUsername) ?>
                </strong>

            </div>

            <form method="post" action="<?= html_escape($adminRoot . 'logout.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                <button class="admin-nav-link" type="submit">
                    <i class="bi bi-box-arrow-left" aria-hidden="true"></i>
                    <span>Logout</span>
                </button>
            </form>

        </div>

    </aside>

    <div class="admin-main">

        <header class="admin-topbar">

            <div class="d-md-none">

                <?php

                $brandHref = $adminRoot . 'dashboard.php';

                require __DIR__ . '/brand.php';

                ?>

            </div>

            <div class="admin-search-wrapper">

                <label
                    class="visually-hidden"
                    for="admin-search"
                >
                    Search admin
                </label>

                <div class="position-relative">

                    <i
                        class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-secondary"
                        aria-hidden="true"
                    ></i>

                    <input
                        class="form-control admin-search ps-5"
                        id="admin-search"
                        type="search"
                        placeholder="Search"
                        autocomplete="off"
                    >

                </div>

            </div>

            <div class="d-flex align-items-center gap-3">

                <span class="topbar-user">

                    <i
                        class="bi bi-person-circle"
                        aria-hidden="true"
                    ></i>

                    <span>
                        <?= html_escape($adminUsername) ?>
                    </span>

                </span>

            </div>

        </header>

        <nav
            class="admin-nav-mobile"
            aria-label="Admin navigation"
        >

            <?php foreach ($adminNavigation as $key => $item): ?>

                <a
                    href="<?= html_escape($adminRoot . $item['href']) ?>"
                    class="<?= $adminActive === $key ? 'active' : '' ?>"
                    <?= $adminActive === $key
                        ? 'aria-current="page"'
                        : '' ?>
                >

                    <i
                        class="bi <?= html_escape($item['icon']) ?>"
                        aria-hidden="true"
                    ></i>

                    <span>
                        <?= html_escape($item['label']) ?>
                    </span>

                </a>

            <?php endforeach; ?>

        </nav>

        <main class="admin-content">

            <div class="page-heading">

                <div>

                    <div class="eyebrow">
                        Selam · Operations
                    </div>

                    <h1>
                        <?= html_escape($adminTitle) ?>
                    </h1>

                    <p>
                        Manage the Selam platform.
                    </p>

                </div>

            </div>