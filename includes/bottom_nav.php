
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

            <p>Selam Dating App · A little closer, every day.</p>
        </div>

        <!-- FOOTER LINKS -->
        <div class="site-footer-links">

            <!-- MAIN NAVIGATION -->
            <div>
                <h2 class="footer-heading">Navigation</h2>

                <nav class="footer-nav" aria-label="Main footer navigation">

                    <?php if ($authenticated): ?>

                        <a href="<?= html_escape($appRoot . 'dating/discover.php') ?>"
                           <?= $activeNav === 'discover' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-heart" aria-hidden="true"></i>
                            Discover
                        </a>

                        <a href="<?= html_escape($appRoot . 'wallet/index.php') ?>"
                           <?= $activeNav === 'wallet' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-wallet2" aria-hidden="true"></i>
                            Wallet
                        </a>

                        <a href="<?= html_escape($appRoot . 'dating/chats.php') ?>"
                           <?= $activeNav === 'chats' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-chat-dots" aria-hidden="true"></i>
                            Chats
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

                    <?php else: ?>

                        <a href="<?= html_escape($appRoot . './') ?>"
                           <?= $activeNav === 'home' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-heart" aria-hidden="true"></i>
                            Discover
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

                        <a href="<?= html_escape($appRoot . 'profile.php') ?>"
                           <?= $activeNav === 'profile' ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-person" aria-hidden="true"></i>
                            Profile
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
        <span>© <?= date('Y') ?> Selam Dating App</span>
        <span>Connection starts with hello.</span>
    </div>
</footer>

<!-- MOBILE BOTTOM NAVIGATION -->
<nav class="bottom-nav" aria-label="Mobile navigation">

    <?php if ($authenticated): ?>

        <a class="bottom-nav-item<?= $activeNav === 'discover' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'dating/discover.php') ?>">
            <i class="bi bi-heart" aria-hidden="true"></i>
            <span>Discover</span>
        </a>

        <a class="bottom-nav-item<?= $activeNav === 'chats' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'dating/chats.php') ?>">
            <i class="bi bi-chat-dots" aria-hidden="true"></i>
            <span>Chats</span>
        </a>

        <a class="bottom-nav-item<?= $activeNav === 'wallet' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'wallet/index.php') ?>">
            <i class="bi bi-wallet2" aria-hidden="true"></i>
            <span>Wallet</span>
        </a>

        <a class="bottom-nav-item<?= $activeNav === 'profile' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . 'profile.php') ?>">
            <i class="bi bi-person" aria-hidden="true"></i>
            <span>Profile</span>
        </a>

    <?php else: ?>

        <a class="bottom-nav-item<?= $activeNav === 'home' ? ' active' : '' ?>"
           href="<?= html_escape($appRoot . './') ?>">
            <i class="bi bi-heart" aria-hidden="true"></i>
            <span>Discover</span>
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
