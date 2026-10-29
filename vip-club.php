<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';

start_app_session();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$db = database();
$error = '';
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Reload the page and try again.';
    } else {
        try {
            $db->beginTransaction();

            $settingsQuery = $db->query('SELECT upgrade_price, enabled FROM marketplace_partner_settings WHERE id = 1 FOR UPDATE');
            $settings = $settingsQuery->fetch(PDO::FETCH_ASSOC);
            if (!$settings || (int) $settings['enabled'] !== 1 || (float) $settings['upgrade_price'] <= 0) {
                throw new RuntimeException('VIP Club upgrades are not available right now.');
            }

            $membershipQuery = $db->prepare('SELECT user_id FROM marketplace_partner_memberships WHERE user_id = ? FOR UPDATE');
            $membershipQuery->execute([$userId]);
            if ($membershipQuery->fetchColumn() !== false) {
                throw new RuntimeException('Your account already has permanent Partner status.');
            }

            $price = number_format((float) $settings['upgrade_price'], 2, '.', '');
            $walletQuery = $db->prepare("
                SELECT id, balance, locked_balance
                FROM wallets
                WHERE user_id = ? AND currency = 'ETB'
                ORDER BY id
                LIMIT 1
                FOR UPDATE
            ");
            $walletQuery->execute([$userId]);
            $wallet = $walletQuery->fetch(PDO::FETCH_ASSOC);
            if (!$wallet) {
                throw new RuntimeException('Your ETB wallet is not ready for the upgrade.');
            }

            if ((float) $wallet['balance'] - (float) $wallet['locked_balance'] < (float) $price) {
                throw new RuntimeException('Your available ETB balance is not enough for this upgrade.');
            }

            $balanceBefore = (string) $wallet['balance'];
            $debit = $db->prepare('UPDATE wallets SET balance = balance - ? WHERE id = ? AND user_id = ?');
            $debit->execute([$price, (int) $wallet['id'], $userId]);
            if ($debit->rowCount() !== 1) {
                throw new RuntimeException('Unable to debit your wallet for the VIP upgrade.');
            }

            $balanceQuery = $db->prepare('SELECT balance FROM wallets WHERE id = ? AND user_id = ?');
            $balanceQuery->execute([(int) $wallet['id'], $userId]);
            $balanceAfter = $balanceQuery->fetchColumn();
            if (!is_string($balanceAfter)) {
                throw new RuntimeException('Unable to verify your wallet balance after the upgrade.');
            }

            $reference = 'PARTNER-' . strtoupper(bin2hex(random_bytes(8)));
            $transactionInsert = $db->prepare("
                INSERT INTO transactions
                    (user_id, wallet_id, type, amount, balance_before, balance_after, reference, description, status)
                VALUES (?, ?, 'BET', ?, ?, ?, ?, 'VIP Club permanent Partner upgrade', 'completed')
            ");
            $transactionInsert->execute([
                $userId,
                (int) $wallet['id'],
                '-' . number_format((float) $price, 8, '.', ''),
                $balanceBefore,
                $balanceAfter,
                $reference,
            ]);
            $transactionId = (int) $db->lastInsertId();

            $membershipInsert = $db->prepare("
                INSERT INTO marketplace_partner_memberships (user_id, amount_paid, transaction_id)
                VALUES (?, ?, ?)
            ");
            $membershipInsert->execute([$userId, $price, $transactionId]);

            $db->commit();
            $message = 'Welcome to VIP Club. Your permanent Partner status is active.';
        } catch (RuntimeException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log(sprintf('VIP Club upgrade failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
            $error = 'Unable to complete your VIP Club upgrade. Please try again.';
        }
    }
}

$settingsQuery = $db->query('SELECT upgrade_price, enabled FROM marketplace_partner_settings WHERE id = 1');
$settings = $settingsQuery->fetch(PDO::FETCH_ASSOC);
$membershipQuery = $db->prepare('SELECT amount_paid, upgraded_at FROM marketplace_partner_memberships WHERE user_id = ? LIMIT 1');
$membershipQuery->execute([$userId]);
$membership = $membershipQuery->fetch(PDO::FETCH_ASSOC);
$upgradeAvailable = $settings && (int) $settings['enabled'] === 1 && (float) $settings['upgrade_price'] > 0;

$appRoot = '';
$pageTitle = 'VIP Club';
$pageDescription = 'Upgrade to permanent Partner status and unlock additional marketplace rewards.';
$activeNav = 'rewards';
require __DIR__ . '/includes/app_header.php';
?>
<section class="vip-club-panel data-panel">
    <img class="vip-club-art" src="assets/images/vipclub.png" alt="VIP Club">
    <div class="vip-club-content">
        <span class="eyebrow">Premium membership</span>
        <h2>Become a Selam Partner</h2>
        <p class="muted">A one-time wallet upgrade gives you permanent Partner status.</p>
        <ul class="vip-club-benefits">
            <li><i class="bi bi-check-circle" aria-hidden="true"></i> Keep your existing 15% / 5% / 3% referral commissions.</li>
            <li><i class="bi bi-check-circle" aria-hidden="true"></i> Earn an additional 9% from direct invitees' product-profit claims.</li>
            <li><i class="bi bi-check-circle" aria-hidden="true"></i> Receive an additional 3% on your own product daily profit.</li>
        </ul>
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
        <?php if ($message !== ''): ?><div class="alert alert-success" role="status"><?= html_escape($message) ?></div><?php endif; ?>
        <?php if ($membership): ?>
            <div class="vip-club-status">
                <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                <span>Partner status active · upgraded <?= html_escape((string) $membership['upgraded_at']) ?></span>
            </div>
        <?php elseif (!$upgradeAvailable): ?>
            <div class="alert alert-info" role="status">VIP Club upgrades are not available right now.</div>
        <?php else: ?>
            <div class="vip-club-price"><?= number_format((float) $settings['upgrade_price'], 2) ?> <small>ETB · one time</small></div>
            <form method="post" data-loading-form>
                <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                <button class="btn btn-primary" type="submit" data-loading-button>Upgrade to Partner</button>
            </form>
            <p class="form-text mt-2">The amount is deducted from your available ETB wallet balance. Partner status is permanent.</p>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/app_footer.php'; ?>
