<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$db = database();
$userId = (int) $_SESSION['user_id'];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? '')) {
        $error = 'Your form expired. Please reload the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'claim_daily_profit') {
            $purchaseId = filter_var($_POST['purchase_id'] ?? null, FILTER_VALIDATE_INT);

            if (!$purchaseId) {
                $error = 'Invalid product purchase.';
            } else {
                try {
                    $db->beginTransaction();

                    $purchaseStmt = $db->prepare(
                        'SELECT up.id, up.product_id, up.user_id, up.daily_profit, up.duration_days,
                                up.created_at, up.status, up.product_name AS name,
                                (NOW() < DATE_ADD(up.created_at, INTERVAL up.duration_days DAY)) AS profit_window_open,
                                u.referred_by_user_id
                         FROM marketplace_purchases up
                         INNER JOIN users u ON u.id = up.user_id
                         WHERE up.id = ? AND up.user_id = ?
                         LIMIT 1
                         FOR UPDATE'
                    );
                    $purchaseStmt->execute([$purchaseId, $userId]);
                    $purchase = $purchaseStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$purchase) {
                        throw new RuntimeException('This product is not in your account.');
                    }
                    if ($purchase['status'] !== 'active') {
                        throw new RuntimeException('This product is inactive and cannot earn profit.');
                    }

                    $dailyProfit = (float) $purchase['daily_profit'];
                    $durationDays = (int) $purchase['duration_days'];

                    if ($dailyProfit <= 0) {
                        throw new RuntimeException('This product has no daily profit to claim.');
                    }
                    if ($durationDays < 1) {
                        throw new RuntimeException('The profit period for this product has ended.');
                    }

                    if ((int) $purchase['profit_window_open'] !== 1) {
                        throw new RuntimeException('The profit period for this product has ended.');
                    }

                    $partnerQuery = $db->prepare('SELECT 1 FROM marketplace_partner_memberships WHERE user_id = ? LIMIT 1');
                    $partnerQuery->execute([$userId]);
                    $isPartner = $partnerQuery->fetchColumn() !== false;
                    $partnerProductBonus = $isPartner ? round($dailyProfit * 3 / 100, 8) : 0.0;
                    $claimAmount = $dailyProfit + $partnerProductBonus;

                    $claimCountStmt = $db->prepare(
                        'SELECT COUNT(*) FROM product_claims WHERE user_id = ? AND purchase_id = ?'
                    );
                    $claimCountStmt->execute([$userId, $purchaseId]);
                    if ((int) $claimCountStmt->fetchColumn() >= $durationDays) {
                        throw new RuntimeException('You have claimed all daily profits for this product.');
                    }

                    $lastClaimStmt = $db->prepare(
                        'SELECT claimed_at, TIMESTAMPDIFF(SECOND, claimed_at, NOW()) AS seconds_since_claim
                         FROM product_claims
                         WHERE user_id = ? AND purchase_id = ?
                         ORDER BY claimed_at DESC
                         LIMIT 1'
                    );
                    $lastClaimStmt->execute([$userId, $purchaseId]);
                    $lastClaim = $lastClaimStmt->fetch(PDO::FETCH_ASSOC);

                    if ($lastClaim && (int) $lastClaim['seconds_since_claim'] < 86400) {
                        throw new RuntimeException('You already claimed today\'s profit for this product.');
                    }

                    $walletStmt = $db->prepare(
                        'SELECT id, balance, locked_balance
                         FROM wallets
                        WHERE user_id = ? AND currency = "ETB"
                         ORDER BY id ASC
                         LIMIT 1
                         FOR UPDATE'
                    );
                    $walletStmt->execute([$userId]);
                    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$wallet) {
                        throw new RuntimeException('Your wallet is missing.');
                    }

                    $walletBefore = (float) ($wallet['balance'] ?? 0.0);
                    $walletAfter = $walletBefore + $claimAmount;

                    $walletUpdate = $db->prepare(
                        'UPDATE wallets
                         SET balance = ?
                         WHERE id = ? AND user_id = ?'
                    );
                    $walletUpdate->execute([
                        number_format($walletAfter, 8, '.', ''),
                        (int) $wallet['id'],
                        $userId,
                    ]);

                    $claimInsert = $db->prepare(
                        'INSERT INTO product_claims (user_id, product_id, purchase_id, amount, claimed_at)
                         VALUES (?, ?, ?, ?, NOW())'
                    );
                    $claimInsert->execute([
                        $userId,
                        (int) $purchase['product_id'],
                        $purchaseId,
                        number_format($claimAmount, 8, '.', ''),
                    ]);
                    $claimId = (int) $db->lastInsertId();

                    $transactionStmt = $db->prepare(
                        'INSERT INTO transactions
                        (user_id, wallet_id, type, amount, balance_before, balance_after, reference, description, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, "completed")'
                    );

                    $reference = 'CLAIM-' . strtoupper(bin2hex(random_bytes(8)));
                    $transactionStmt->execute([
                        $userId,
                        (int) $wallet['id'],
                        'WIN',
                        number_format($claimAmount, 8, '.', ''),
                        number_format($walletBefore, 8, '.', ''),
                        number_format($walletAfter, 8, '.', ''),
                        $reference,
                        'Daily profit payout for ' . (string) $purchase['name'] . ($partnerProductBonus > 0 ? ' including 3% Partner bonus' : ''),
                    ]);

                    $commissionRates = [1 => 15.0, 2 => 5.0, 3 => 3.0];
                    $referrerId = (int) ($purchase['referred_by_user_id'] ?? 0);
                    $referrerQuery = $db->prepare(
                        'SELECT u.id, u.referred_by_user_id,
                                EXISTS(SELECT 1 FROM marketplace_partner_memberships pm WHERE pm.user_id = u.id) AS is_partner
                         FROM users u
                         WHERE u.id = ?
                         LIMIT 1'
                    );
                    $commissionInsert = $db->prepare(
                        'INSERT INTO product_commissions
                         (claim_id, beneficiary_user_id, level, rate_percent, amount)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $commissionTransaction = $db->prepare(
                        'INSERT INTO transactions
                         (user_id, wallet_id, type, amount, balance_before, balance_after, reference, description, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, "completed")'
                    );

                    foreach ($commissionRates as $level => $ratePercent) {
                        if ($referrerId < 1) {
                            break;
                        }

                        $referrerQuery->execute([$referrerId]);
                        $referrer = $referrerQuery->fetch(PDO::FETCH_ASSOC);
                        if (!$referrer) {
                            throw new RuntimeException('The product referral chain is invalid.');
                        }

                        if ($level === 1 && (int) $referrer['is_partner'] === 1) {
                            $ratePercent += 9.0;
                        }

                        $commissionAmount = round($dailyProfit * $ratePercent / 100, 8);
                        if ($commissionAmount > 0) {
                            $commissionWalletStmt = $db->prepare(
                                "SELECT id, balance FROM wallets
                                 WHERE user_id = ? AND currency = 'ETB'
                                 LIMIT 1 FOR UPDATE"
                            );
                            $commissionWalletStmt->execute([$referrerId]);
                            $commissionWallet = $commissionWalletStmt->fetch(PDO::FETCH_ASSOC);
                            if (!$commissionWallet) {
                                throw new RuntimeException('A referred team member does not have an ETB wallet.');
                            }

                            $commissionBalanceBefore = (float) $commissionWallet['balance'];
                            $commissionBalanceAfter = $commissionBalanceBefore + $commissionAmount;
                            $commissionWalletUpdate = $db->prepare(
                                'UPDATE wallets SET balance = ? WHERE id = ? AND user_id = ?'
                            );
                            $commissionWalletUpdate->execute([
                                number_format($commissionBalanceAfter, 8, '.', ''),
                                (int) $commissionWallet['id'],
                                $referrerId,
                            ]);

                            $commissionInsert->execute([
                                $claimId,
                                $referrerId,
                                $level,
                                number_format($ratePercent, 2, '.', ''),
                                number_format($commissionAmount, 8, '.', ''),
                            ]);

                            $commissionTransaction->execute([
                                $referrerId,
                                (int) $commissionWallet['id'],
                                'WIN',
                                number_format($commissionAmount, 8, '.', ''),
                                number_format($commissionBalanceBefore, 8, '.', ''),
                                number_format($commissionBalanceAfter, 8, '.', ''),
                                'TEAM-' . strtoupper(bin2hex(random_bytes(8))),
                                'Level ' . $level . ' referral commission from product profit claim #' . $claimId,
                            ]);
                        }

                        $referrerId = (int) ($referrer['referred_by_user_id'] ?? 0);
                    }

                    $db->commit();
                    $message = 'Daily profit' . ($partnerProductBonus > 0 ? ' and your 3% Partner bonus' : '') . ' and any eligible team commissions were credited to the wallets.';
                } catch (Throwable $caught) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    error_log('Daily profit claim error: ' . $caught->getMessage());
                    $error = $caught->getMessage() !== '' ? $caught->getMessage() : 'Unable to claim the daily profit.';
                }
            }
        }
    }
}

$productsStmt = $db->prepare(
    'SELECT up.id AS purchase_id,
            up.product_id,
            up.product_name,
            up.price AS purchase_price,
            up.daily_profit,
            up.duration_days,
            up.image_path,
            up.quantity,
            up.status,
            p.description,
            (SELECT COUNT(*) FROM product_claims WHERE user_id = ? AND purchase_id = up.id) AS claims_made,
            (NOW() < DATE_ADD(up.created_at, INTERVAL up.duration_days DAY)) AS profit_window_open,
            COALESCE((SELECT TIMESTAMPDIFF(SECOND, MAX(claimed_at), NOW()) FROM product_claims WHERE user_id = ? AND purchase_id = up.id), 86400) AS seconds_since_last_claim
     FROM marketplace_purchases up
     LEFT JOIN products p ON p.id = up.product_id
     WHERE up.user_id = ?
     ORDER BY up.id DESC'
);
$productsStmt->execute([$userId, $userId, $userId]);
$ownedProducts = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Products';
$pageDescription = 'Track your purchased products and claim your daily profit.';
$activeNav = 'my-products';

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <span class="eyebrow">Selam Market</span>
        <h1 class="h3 mb-2">My products</h1>
        <p class="text-secondary mb-0">
            Your purchased products and any daily profit available for claim.
        </p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($ownedProducts)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-bag-check fs-1 text-secondary"></i>
                <h4 class="mt-3">No products yet</h4>
                <p class="text-secondary mb-0">Buy a product from the catalog and it will appear here.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="vstack gap-3">
            <?php foreach ($ownedProducts as $product): ?>
                <?php
                $dailyProfit = (float) ($product['daily_profit'] ?? 0.0);
                $durationDays = (int) $product['duration_days'];
                $claimsMade = (int) $product['claims_made'];
                $claimDisabled = (int) $product['seconds_since_last_claim'] < 86400
                    || (int) $product['profit_window_open'] !== 1
                    || $durationDays < 1
                    || $claimsMade >= $durationDays;
                ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <span class="badge bg-dark mb-2">Owned</span>
                                <?php if (!empty($product['image_path'])): ?>
                                    <img src="<?= htmlspecialchars((string) $product['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $product['product_name'], ENT_QUOTES, 'UTF-8') ?>" class="market-product-image mb-3">
                                <?php endif; ?>
                                <h2 class="h5 mb-1"><?= htmlspecialchars((string) $product['product_name'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <p class="text-secondary mb-0"><?= htmlspecialchars((string) $product['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <span class="badge bg-success-subtle text-success"><?= htmlspecialchars((string) $product['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-6">
                                <small class="text-secondary d-block">Purchase price</small>
                                <strong><?= number_format((float) $product['purchase_price'], 2) ?> ETB</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-secondary d-block">Daily profit</small>
                                <strong class="text-success">+<?= number_format((float) $dailyProfit, 2) ?> ETB</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-secondary d-block">Profit days claimed</small>
                                <strong><?= number_format($claimsMade) ?> / <?= number_format($durationDays) ?></strong>
                            </div>
                        </div>

                        <form method="post" class="mt-3">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="claim_daily_profit">
                            <input type="hidden" name="purchase_id" value="<?= (int) $product['purchase_id'] ?>">
                            <button type="submit" class="btn btn-primary w-100" <?= $claimDisabled ? 'disabled' : '' ?>>
                                <i class="bi bi-cash-stack me-1"></i>
                                <?= $claimsMade >= $durationDays || $durationDays < 1 || (int) $product['profit_window_open'] !== 1 ? 'Profit period completed' : ($claimDisabled ? 'Claim unavailable today' : 'Claim daily profit') ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
if (file_exists(__DIR__ . '/includes/app_footer.php')) {
    require_once __DIR__ . '/includes/app_footer.php';
}
?>
