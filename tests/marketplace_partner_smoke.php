<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$db = database();
$token = strtoupper(bin2hex(random_bytes(5)));
$userIds = [];
$productIds = [];
$couponId = null;
$settings = $db->query('SELECT upgrade_price, enabled, updated_by_admin_id FROM marketplace_partner_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$sessionIds = ['partner' => 'partner-' . $token, 'invitee' => 'partner-' . $token];

function assert_partner_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function run_partner_route(string $route, array $post, int $userId, string $sessionId): string
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_id($sessionId);
    session_start();
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = 'partner-smoke';
    $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    $csrf = $_SESSION['csrf_token'];
    session_write_close();

    session_id($sessionId);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf_token' => $csrf] + $post;
    ob_start();
    include $route;
    $html = (string) ob_get_clean();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $_POST = [];
    return $html;
}

try {
    $userInsert = $db->prepare('INSERT INTO users (username, phone, password_hash, referral_code) VALUES (?, ?, ?, ?)');
    $userInsert->execute(['partner' . strtolower($token), '+1555' . substr($token, 0, 8), password_hash('Smoke123', PASSWORD_DEFAULT), 'P' . $token]);
    $partnerId = (int) $db->lastInsertId();
    $userIds[] = $partnerId;
    $userInsert->execute(['invitee' . strtolower($token), '+1666' . substr($token, 0, 8), password_hash('Smoke123', PASSWORD_DEFAULT), 'I' . $token]);
    $inviteeId = (int) $db->lastInsertId();
    $userIds[] = $inviteeId;
    $db->prepare('UPDATE users SET referred_by_user_id = ? WHERE id = ?')->execute([$partnerId, $inviteeId]);
    $db->prepare('INSERT INTO wallets (user_id, currency, balance) VALUES (?, "ETB", 100.00), (?, "ETB", 100.00)')->execute([$partnerId, $inviteeId]);

    $productInsert = $db->prepare("
        INSERT INTO products (name, description, price, profit, profit_duration_days, quantity, status, published)
        VALUES (?, 'Temporary partner test item', 10.00, 1.00, 30, 2, 'active', 1)
    ");
    for ($index = 1; $index <= 4; $index++) {
        $productInsert->execute(['Partner smoke ' . $index . '-' . $token]);
        $productIds[] = (int) $db->lastInsertId();
    }

    $purchaseInsert = $db->prepare("
        INSERT INTO marketplace_purchases
            (user_id, product_id, product_name, price, daily_profit, duration_days, quantity, status)
        VALUES (?, ?, ?, 10.00, 10.00, 30, 1, 'active')
    ");
    $purchaseInsert->execute([$partnerId, $productIds[0], 'Partner self product']);
    $partnerPurchaseId = (int) $db->lastInsertId();
    $purchaseInsert->execute([$inviteeId, $productIds[1], 'Invitee product']);
    $inviteePurchaseId = (int) $db->lastInsertId();

    $db->prepare('UPDATE marketplace_partner_settings SET upgrade_price = 20.00, enabled = 1 WHERE id = 1')->execute();
    $vipHtml = run_partner_route(__DIR__ . '/../vip-club.php', [], $partnerId, $sessionIds['partner']);
    assert_partner_test(str_contains($vipHtml, 'Partner status is active') || str_contains($vipHtml, 'Welcome to VIP Club'), 'VIP upgrade did not return a success response.');
    assert_partner_test((float) $db->query("SELECT balance FROM wallets WHERE user_id = {$partnerId} AND currency = 'ETB'")->fetchColumn() === 80.0, 'VIP upgrade did not debit its configured fee.');
    assert_partner_test((int) $db->query("SELECT COUNT(*) FROM marketplace_partner_memberships WHERE user_id = {$partnerId}")->fetchColumn() === 1, 'VIP upgrade did not grant Partner status.');

    run_partner_route(__DIR__ . '/../my-products.php', ['action' => 'claim_daily_profit', 'purchase_id' => $partnerPurchaseId], $partnerId, $sessionIds['partner']);
    $afterPartnerClaim = (float) $db->query("SELECT balance FROM wallets WHERE user_id = {$partnerId} AND currency = 'ETB'")->fetchColumn();
    assert_partner_test(abs($afterPartnerClaim - 90.3) < 0.000001, 'Partner did not receive daily profit plus the 3% own-product bonus.');

    run_partner_route(__DIR__ . '/../my-products.php', ['action' => 'claim_daily_profit', 'purchase_id' => $inviteePurchaseId], $inviteeId, $sessionIds['invitee']);
    $commissionStmt = $db->prepare("
        SELECT rate_percent, amount
        FROM product_commissions
        WHERE beneficiary_user_id = ? AND claim_id = (
            SELECT MAX(id) FROM product_claims WHERE user_id = ?
        )
    ");
    $commissionStmt->execute([$partnerId, $inviteeId]);
    $commission = $commissionStmt->fetch(PDO::FETCH_ASSOC);
    assert_partner_test($commission && (float) $commission['rate_percent'] === 24.0 && abs((float) $commission['amount'] - 2.4) < 0.000001, 'Direct invitee commission did not include the extra 9% Partner rate.');
    $afterCommission = (float) $db->query("SELECT balance FROM wallets WHERE user_id = {$partnerId} AND currency = 'ETB'")->fetchColumn();
    assert_partner_test(abs($afterCommission - 92.7) < 0.000001, 'Direct invitee commission was not credited to the Partner wallet.');

    $couponCode = 'SMOKE' . $token;
    $db->prepare('INSERT INTO marketplace_coupons (code, discount_amount, max_redemptions) VALUES (?, 2.00, 1)')->execute([$couponCode]);
    $couponId = (int) $db->lastInsertId();
    $couponHtml = run_partner_route(__DIR__ . '/../products.php', [
        'action' => 'buy_product',
        'product_id' => $productIds[2],
        'coupon_code' => $couponCode,
    ], $inviteeId, $sessionIds['invitee']);
    $discountedPurchase = $db->prepare('SELECT price FROM marketplace_purchases WHERE user_id = ? AND product_id = ?');
    $discountedPurchase->execute([$inviteeId, $productIds[2]]);
    assert_partner_test((float) $discountedPurchase->fetchColumn() === 8.0, 'Shared coupon did not discount a product purchase.');
    assert_partner_test(str_contains($couponHtml, 'coupon savings'), 'Coupon purchase did not return a success response.');

    $limitHtml = run_partner_route(__DIR__ . '/../products.php', [
        'action' => 'buy_product',
        'product_id' => $productIds[3],
        'coupon_code' => $couponCode,
    ], $partnerId, $sessionIds['partner']);
    assert_partner_test(str_contains($limitHtml, 'reached its redemption limit'), 'A shared coupon exceeded its admin-set user limit.');
    $limitPurchase = $db->prepare('SELECT COUNT(*) FROM marketplace_purchases WHERE user_id = ? AND product_id = ?');
    $limitPurchase->execute([$partnerId, $productIds[3]]);
    assert_partner_test((int) $limitPurchase->fetchColumn() === 0, 'A purchase was created after the coupon use limit was reached.');

    run_partner_route(__DIR__ . '/../vip-club.php', [], $partnerId, $sessionIds['partner']);
    assert_partner_test((int) $db->query("SELECT COUNT(*) FROM marketplace_partner_memberships WHERE user_id = {$partnerId}")->fetchColumn() === 1, 'A repeat upgrade changed the permanent Partner membership.');
    $afterRepeatUpgrade = (float) $db->query("SELECT balance FROM wallets WHERE user_id = {$partnerId} AND currency = 'ETB'")->fetchColumn();
    assert_partner_test(abs($afterRepeatUpgrade - $afterCommission) < 0.000001, 'A repeat VIP upgrade charged the Partner again.');

} finally {
    foreach (array_unique($sessionIds) as $sessionId) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id($sessionId);
        session_start();
        $_SESSION = [];
        session_destroy();
    }

    if ($userIds !== []) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $db->prepare("DELETE FROM marketplace_coupon_redemptions WHERE user_id IN ({$placeholders})")->execute($userIds);
        $db->prepare("DELETE FROM marketplace_daily_streak_claims WHERE user_id IN ({$placeholders})")->execute($userIds);
        $db->prepare("DELETE FROM product_commissions WHERE beneficiary_user_id IN ({$placeholders})")->execute($userIds);
        $db->prepare("DELETE FROM product_claims WHERE user_id IN ({$placeholders})")->execute($userIds);
        $db->prepare("DELETE FROM marketplace_purchases WHERE user_id IN ({$placeholders})")->execute($userIds);
        $db->prepare("DELETE FROM marketplace_partner_memberships WHERE user_id IN ({$placeholders})")->execute($userIds);
        $db->prepare("DELETE FROM marketplace_daily_streaks WHERE user_id IN ({$placeholders})")->execute($userIds);
        $db->prepare("DELETE FROM transactions WHERE user_id IN ({$placeholders})")->execute($userIds);
        $db->prepare("DELETE FROM wallets WHERE user_id IN ({$placeholders})")->execute($userIds);
        $db->prepare("DELETE FROM users WHERE id IN ({$placeholders})")->execute($userIds);
    }
    if ($productIds !== []) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $db->prepare("DELETE FROM products WHERE id IN ({$placeholders})")->execute($productIds);
    }
    if ($couponId !== null) {
        $db->prepare('DELETE FROM marketplace_coupons WHERE id = ?')->execute([$couponId]);
    }
    $restore = $db->prepare('UPDATE marketplace_partner_settings SET upgrade_price = ?, enabled = ?, updated_by_admin_id = ? WHERE id = 1');
    $restore->execute([$settings['upgrade_price'], $settings['enabled'], $settings['updated_by_admin_id']]);
}

echo "Marketplace partner and coupon smoke checks passed.\n";
