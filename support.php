<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';

start_app_session();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$supportEmailInput = getenv('SELAM_SUPPORT_EMAIL');
$supportEmail = is_string($supportEmailInput) && filter_var($supportEmailInput, FILTER_VALIDATE_EMAIL)
    ? $supportEmailInput
    : null;
$supportTelegram = 'accessme2018';
$supportWhatsAppInput = getenv('SELAM_SUPPORT_WHATSAPP');
$supportWhatsApp = is_string($supportWhatsAppInput)
    && preg_match('/\A\+?[1-9][0-9]{7,14}\z/', $supportWhatsAppInput) === 1
    ? preg_replace('/\D/', '', $supportWhatsAppInput)
    : null;

$appRoot = '';
$pageTitle = 'Support';
$pageDescription = 'Help with your wallet, product purchases, and account.';
$activeNav = 'support';
require __DIR__ . '/includes/app_header.php';
?>
<section class="content-grid">
    <div class="data-panel">
        <div class="data-panel-header"><h2>Contact Selam Support</h2></div>
        <div class="p-4">
            <?php if ($supportEmail === null && $supportTelegram === null && $supportWhatsApp === null): ?>
                <div class="alert alert-warning mb-0" role="status">
                    Support contact channels have not been configured yet. For an urgent account or wallet issue, contact the Selam administrator through your official channel. Never share your password or one-time codes.
                </div>
            <?php else: ?>
                <p class="muted">Contact us through one of these channels. Include your Selam user ID and request number, but never send your password or one-time codes.</p>
                <div class="support-contact-list">
                    <?php if ($supportEmail !== null): ?>
                        <a class="support-contact-link" href="mailto:<?= html_escape($supportEmail) ?>">
                            <i class="bi bi-envelope" aria-hidden="true"></i><span>Email support</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($supportTelegram !== null): ?>
                        <a class="support-contact-link" href="https://t.me/<?= rawurlencode($supportTelegram) ?>" target="_blank" rel="noopener noreferrer">
                            <img class="support-telegram-logo" src="assets/images/telegram.png" alt="">
                            <span>Customer support @<?= html_escape($supportTelegram) ?> on Telegram</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($supportWhatsApp !== null): ?>
                        <a class="support-contact-link" href="https://wa.me/<?= html_escape($supportWhatsApp) ?>" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-whatsapp" aria-hidden="true"></i><span>Message on WhatsApp</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <aside class="data-panel">
        <div class="data-panel-header"><h2>Quick help</h2></div>
        <div class="p-4 support-help-links">
            <a href="wallet/deposit.php"><i class="bi bi-arrow-down-circle" aria-hidden="true"></i> Deposit status and receipt</a>
            <a href="wallet/withdraw.php"><i class="bi bi-arrow-up-circle" aria-hidden="true"></i> Withdrawal status and payout</a>
            <a href="wallet/transactions.php"><i class="bi bi-receipt" aria-hidden="true"></i> Wallet activity</a>
            <a href="my-products.php"><i class="bi bi-box-seam" aria-hidden="true"></i> Purchased products and daily profit</a>
            <p class="small-note mb-0">Pending deposits do not change your balance until approved. Withdrawal funds remain reserved while a request is pending.</p>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/includes/app_footer.php'; ?>
