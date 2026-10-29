
<?php

require_once __DIR__ . '/security.php';

$activeNav = isset($activeNav) && is_string($activeNav)
    ? $activeNav
    : 'home';

$appRoot = isset($appRoot) && is_string($appRoot)
    ? $appRoot
    : '';

$authenticated = isset(
    $_SESSION['user_id'],
    $_SESSION['username']
);
?>

<footer class="site-footer">
    <div class="site-footer-main">

        <!-- BRAND -->
        <div class="site-footer-brand">
            <?php
            $brandHref = $appRoot . './';
            require __DIR__ . '/brand.php';
            ?>

            <p>Selam Market · Buy once and earn daily profit.</p>
        </div>

        <!-- FOOTER LINKS -->
        <div class="site-footer-links">

            <!-- MAIN NAVIGATION -->
            <div>
                <h2 class="footer-heading">Navigation</h2>

                <nav class="footer-nav" aria-label="Main footer navigation">

                    <?php if ($authenticated): ?>

                        <a href="<?= html_escape($appRoot . 'products.php') ?>"
                           <?= $activeNav === 'products' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-bag" aria-hidden="true"></i>
                            Products
                        </a>

                        <a href="<?= html_escape($appRoot . 'my-products.php') ?>"
                           <?= $activeNav === 'my-products' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-box-seam" aria-hidden="true"></i>
                            My Products
                        </a>

                        <a href="<?= html_escape($appRoot . 'wallet/index.php') ?>"
                           <?= $activeNav === 'wallet' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-wallet2" aria-hidden="true"></i>
                            Wallet
                        </a>

                        <a href="<?= html_escape($appRoot . 'wallet/transactions.php') ?>"
                           <?= $activeNav === 'transactions' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-receipt" aria-hidden="true"></i>
                            Activity
                        </a>

                        <a href="<?= html_escape($appRoot . 'wallet/transfer.php') ?>"
                           <?= $activeNav === 'transfer' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-send" aria-hidden="true"></i>
                            Transfer
                        </a>

                        <a href="<?= html_escape($appRoot . 'notifications.php') ?>"
                           <?= $activeNav === 'notifications' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            Notifications
                        </a>

                        <a href="<?= html_escape($appRoot . 'support.php') ?>"
                           <?= $activeNav === 'support' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-life-preserver" aria-hidden="true"></i>
                            Support
                        </a>

                    <?php else: ?>

                        <a href="<?= html_escape($appRoot . 'products.php') ?>"
                           <?= $activeNav === 'home' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-bag" aria-hidden="true"></i>
                            Shop
                        </a>

                        <a href="<?= html_escape($appRoot . 'login.php') ?>"
                           <?= $activeNav === 'login' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            Login
                        </a>

                        <a href="<?= html_escape($appRoot . 'register.php') ?>"
                           <?= $activeNav === 'register' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-person-plus" aria-hidden="true"></i>
                            Register
                        </a>

                    <?php endif; ?>

                </nav>
            </div>

            <!-- ACCOUNT -->
            <?php if ($authenticated): ?>
                <div>
                    <h2 class="footer-heading">Account</h2>

                    <nav class="footer-nav" aria-label="Account navigation">

                        <a href="<?= html_escape($appRoot . 'team.php') ?>"
                           <?= $activeNav === 'team' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-people" aria-hidden="true"></i>
                            My Team
                        </a>

                        <form method="post"
                              action="<?= html_escape($appRoot . 'logout.php') ?>">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= html_escape(csrf_token()) ?>"
                            >

                            <button type="submit">
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                Logout
                            </button>
                        </form>

                    </nav>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- FOOTER META -->
    <div class="site-footer-meta">
        <span>© <?= date('Y') ?> Selam Market</span>
        <span>Buy once. Earn daily.</span>
    </div>
</footer>

<!-- MOBILE BOTTOM NAVIGATION -->
<nav class="bottom-nav" aria-label="Mobile navigation">

    <?php if ($authenticated): ?>

        <a class="bottom-nav-item<?= $activeNav === 'products' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'products.php') ?>">
            <i class="bi bi-bag" aria-hidden="true"></i>
            <span>Products</span>
        </a>

        <a class="bottom-nav-item<?= $activeNav === 'my-products' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'my-products.php') ?>">
            <i class="bi bi-box-seam" aria-hidden="true"></i>
            <span>My Products</span>
        </a>

        <a class="bottom-nav-item<?= $activeNav === 'wallet' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'wallet/index.php') ?>">
            <i class="bi bi-wallet2" aria-hidden="true"></i>
            <span>Wallet</span>
        </a>

        <a class="bottom-nav-item<?= $activeNav === 'team' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'team.php') ?>">
            <i class="bi bi-people" aria-hidden="true"></i>
            <span>Team</span>
        </a>

        <a class="bottom-nav-item<?= $activeNav === 'support' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'support.php') ?>">
            <i class="bi bi-life-preserver" aria-hidden="true"></i>
            <span>Support</span>
        </a>

    <?php else: ?>

        <a class="bottom-nav-item<?= $activeNav === 'home' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'products.php') ?>">
            <i class="bi bi-bag" aria-hidden="true"></i>
            <span>Shop</span>
        </a>

        <a class="bottom-nav-item<?= $activeNav === 'login' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'login.php') ?>">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
            <span>Log in</span>
        </a>

        <a class="bottom-nav-item<?= $activeNav === 'register' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'register.php') ?>">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
            <span>Sign up</span>
        </a>

    <?php endif; ?>

</nav>
