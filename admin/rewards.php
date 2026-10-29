<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_auth.php';

$adminId = require_active_admin();
$db = database();
$error = '';
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Reload the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_streak_settings') {
            $amountInput = $_POST['reward_amount'] ?? '';
            $amount = is_string($amountInput) ? trim($amountInput) : '';
            $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1' ? 1 : 0;
            if (preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', $amount) !== 1) {
                $error = 'Enter a reward amount with up to two decimal places.';
            } elseif ($enabled === 1 && (float) $amount <= 0) {
                $error = 'Set a reward greater than zero before enabling daily streaks.';
            } else {
                try {
                    $saveSettings = $db->prepare("
                        UPDATE marketplace_daily_streak_settings
                        SET reward_amount = ?, enabled = ?, updated_by_admin_id = ?
                        WHERE id = 1
                    ");
                    $saveSettings->execute([$amount, $enabled, $adminId]);
                    $message = $enabled === 1 ? 'Daily streak reward settings saved.' : 'Daily streak rewards are disabled.';
                } catch (PDOException $exception) {
                    error_log(sprintf('Admin daily streak settings failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
                    $error = 'Unable to save daily streak settings.';
                }
            }
        } elseif ($action === 'save_partner_settings') {
            $priceInput = $_POST['upgrade_price'] ?? '';
            $price = is_string($priceInput) ? trim($priceInput) : '';
            $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1' ? 1 : 0;
            if (preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', $price) !== 1) {
                $error = 'Enter a VIP upgrade price with up to two decimal places.';
            } elseif ($enabled === 1 && (float) $price <= 0) {
                $error = 'Set a VIP upgrade price greater than zero before enabling upgrades.';
            } else {
                try {
                    $savePartnerSettings = $db->prepare("
                        UPDATE marketplace_partner_settings
                        SET upgrade_price = ?, enabled = ?, updated_by_admin_id = ?
                        WHERE id = 1
                    ");
                    $savePartnerSettings->execute([$price, $enabled, $adminId]);
                    $message = $enabled === 1 ? 'VIP Club upgrade settings saved.' : 'VIP Club upgrades are disabled.';
                } catch (PDOException $exception) {
                    error_log(sprintf('Admin VIP settings failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
                    $error = 'Unable to save VIP Club settings.';
                }
            }
        } elseif ($action === 'create_coupon') {
            $codeInput = $_POST['code'] ?? '';
            $discountInput = $_POST['discount_amount'] ?? '';
            $limitInput = $_POST['max_redemptions'] ?? '';
            $expiryInput = $_POST['expires_at'] ?? '';
            $code = is_string($codeInput) ? strtoupper(trim($codeInput)) : '';
            $discount = is_string($discountInput) ? trim($discountInput) : '';
            $limitText = is_string($limitInput) ? trim($limitInput) : '';
            $expiryText = is_string($expiryInput) ? trim($expiryInput) : '';

            if ($code !== '' && preg_match('/\A[A-Z0-9][A-Z0-9_-]{2,39}\z/', $code) !== 1) {
                $error = 'Coupon codes must be 3–40 letters, numbers, dashes, or underscores.';
            } elseif (preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', $discount) !== 1 || (float) $discount <= 0) {
                $error = 'Enter a fixed coupon discount greater than zero.';
            } else {
                $maxRedemptions = null;
                if ($limitText !== '') {
                    $maxRedemptions = filter_var($limitText, FILTER_VALIDATE_INT);
                    if ($maxRedemptions === false || $maxRedemptions < 1) {
                        $error = 'Maximum uses must be a positive whole number, or left blank for unlimited uses.';
                    }
                }

                $expiresAt = null;
                if ($error === '' && $expiryText !== '') {
                    $expiryDate = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $expiryText);
                    if (!$expiryDate || $expiryDate->format('Y-m-d\TH:i') !== $expiryText || $expiryDate <= new DateTimeImmutable()) {
                        $error = 'Choose a valid future coupon expiration date.';
                    } else {
                        $expiresAt = $expiryDate->format('Y-m-d H:i:s');
                    }
                }

                if ($error === '') {
                    try {
                        $createCoupon = $db->prepare("
                            INSERT INTO marketplace_coupons (code, discount_amount, max_redemptions, expires_at, created_by_admin_id)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        for ($attempt = 0; $attempt < 5; $attempt++) {
                            $couponCode = $code !== '' ? $code : 'SELAM-' . strtoupper(bin2hex(random_bytes(5)));
                            try {
                                $createCoupon->execute([$couponCode, $discount, $maxRedemptions, $expiresAt, $adminId]);
                                $message = 'Coupon ' . $couponCode . ' created. You can edit the code below.';
                                break;
                            } catch (PDOException $exception) {
                                if ($exception->getCode() !== '23000' || $code !== '' || $attempt === 4) {
                                    throw $exception;
                                }
                            }
                        }
                    } catch (PDOException $exception) {
                        if ($exception->getCode() === '23000') {
                            $error = 'That coupon code already exists.';
                        } else {
                            error_log(sprintf('Admin coupon creation failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
                            $error = 'Unable to create coupon.';
                        }
                    }
                }
            }
        } elseif ($action === 'update_coupon') {
            $couponId = filter_var($_POST['coupon_id'] ?? null, FILTER_VALIDATE_INT);
            $codeInput = $_POST['code'] ?? '';
            $discountInput = $_POST['discount_amount'] ?? '';
            $limitInput = $_POST['max_redemptions'] ?? '';
            $expiryInput = $_POST['expires_at'] ?? '';
            $code = is_string($codeInput) ? strtoupper(trim($codeInput)) : '';
            $discount = is_string($discountInput) ? trim($discountInput) : '';
            $limitText = is_string($limitInput) ? trim($limitInput) : '';
            $expiryText = is_string($expiryInput) ? trim($expiryInput) : '';

            if ($couponId === false || $couponId === null || $couponId < 1) {
                $error = 'Choose a valid coupon.';
            } elseif (preg_match('/\A[A-Z0-9][A-Z0-9_-]{2,39}\z/', $code) !== 1) {
                $error = 'Coupon codes must be 3–40 letters, numbers, dashes, or underscores.';
            } elseif (preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', $discount) !== 1 || (float) $discount <= 0) {
                $error = 'Enter a fixed coupon discount greater than zero.';
            } else {
                $maxRedemptions = null;
                if ($limitText !== '') {
                    $maxRedemptions = filter_var($limitText, FILTER_VALIDATE_INT);
                    if ($maxRedemptions === false || $maxRedemptions < 1) {
                        $error = 'Maximum uses must be a positive whole number, or left blank for unlimited uses.';
                    }
                }

                $expiresAt = null;
                if ($error === '' && $expiryText !== '') {
                    $expiryDate = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $expiryText);
                    if (!$expiryDate || $expiryDate->format('Y-m-d\TH:i') !== $expiryText) {
                        $error = 'Choose a valid coupon expiration date.';
                    } else {
                        $expiresAt = $expiryDate->format('Y-m-d H:i:s');
                    }
                }

                if ($error === '') {
                    try {
                        $db->beginTransaction();
                        $couponLock = $db->prepare('SELECT id FROM marketplace_coupons WHERE id = ? FOR UPDATE');
                        $couponLock->execute([$couponId]);
                        if ($couponLock->fetchColumn() === false) {
                            throw new RuntimeException('That coupon no longer exists.');
                        }

                        $currentUsesStmt = $db->prepare('SELECT COUNT(*) FROM marketplace_coupon_redemptions WHERE coupon_id = ?');
                        $currentUsesStmt->execute([$couponId]);
                        $currentUses = (int) $currentUsesStmt->fetchColumn();
                        if ($maxRedemptions !== null && $maxRedemptions < $currentUses) {
                            throw new RuntimeException('Maximum uses cannot be less than the number of redemptions already made.');
                        }

                        $updateCoupon = $db->prepare("
                            UPDATE marketplace_coupons
                            SET code = ?, discount_amount = ?, max_redemptions = ?, expires_at = ?
                            WHERE id = ?
                        ");
                        $updateCoupon->execute([$code, $discount, $maxRedemptions, $expiresAt, $couponId]);
                        $db->commit();
                        $message = 'Coupon settings updated.';
                    } catch (RuntimeException $exception) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $error = $exception->getMessage();
                    } catch (PDOException $exception) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        if ($exception->getCode() === '23000') {
                            $error = 'That coupon code already exists.';
                        } else {
                            error_log(sprintf('Admin coupon update failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
                            $error = 'Unable to update coupon.';
                        }
                    }
                }
            }
        } elseif ($action === 'toggle_coupon') {
            $couponId = filter_var($_POST['coupon_id'] ?? null, FILTER_VALIDATE_INT);
            $nextStatus = $_POST['next_status'] ?? '';
            if ($couponId === false || $couponId === null || $couponId < 1 || !in_array($nextStatus, ['active', 'inactive'], true)) {
                $error = 'Choose a valid coupon action.';
            } else {
                try {
                    $toggleCoupon = $db->prepare('UPDATE marketplace_coupons SET status = ? WHERE id = ?');
                    $toggleCoupon->execute([$nextStatus, $couponId]);
                    $message = 'Coupon status updated.';
                } catch (PDOException $exception) {
                    error_log(sprintf('Admin coupon status update failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
                    $error = 'Unable to update coupon status.';
                }
            }
        } else {
            $error = 'That reward action is not available.';
        }
    }
}

$settings = $db->query('SELECT reward_amount, enabled FROM marketplace_daily_streak_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$partnerSettings = $db->query('SELECT upgrade_price, enabled FROM marketplace_partner_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$coupons = $db->query("
    SELECT c.id, c.code, c.discount_amount, c.max_redemptions, c.expires_at, c.status, c.created_at,
           COUNT(r.id) AS redemption_count
    FROM marketplace_coupons c
    LEFT JOIN marketplace_coupon_redemptions r ON r.coupon_id = c.id
    GROUP BY c.id, c.code, c.discount_amount, c.max_redemptions, c.expires_at, c.status, c.created_at
    ORDER BY c.id DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

$adminRoot = '';
$adminTitle = 'Rewards';
$adminActive = 'rewards';
require __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
<?php if ($message !== ''): ?><div class="alert alert-success" role="status"><?= html_escape($message) ?></div><?php endif; ?>

<section class="data-panel mb-4">
    <div class="data-panel-header"><h2>Daily streak reward</h2></div>
    <form method="post" class="p-4" data-loading-form>
        <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_streak_settings">
        <div class="mb-3">
            <label class="form-label" for="streak-reward-amount">Reward per daily claim · ETB</label>
            <input class="form-control" id="streak-reward-amount" name="reward_amount" type="number" min="0" step="0.01" value="<?= html_escape(number_format((float) ($settings['reward_amount'] ?? 0), 2, '.', '')) ?>" required>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" id="streak-enabled" name="enabled" type="checkbox" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="streak-enabled">Enable daily streak claims</label>
        </div>
        <p class="form-text">Users can claim once each calendar day. Consecutive days increase the streak count; missing a day resets it to day 1. The ETB reward is the same each day.</p>
        <button class="btn btn-primary" type="submit" data-loading-button>Save streak settings</button>
    </form>
</section>

<section class="data-panel mb-4">
    <div class="data-panel-header"><h2>VIP Club · Partner upgrade</h2></div>
    <form method="post" class="p-4" data-loading-form>
        <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_partner_settings">
        <div class="mb-3">
            <label class="form-label" for="partner-upgrade-price">One-time upgrade price · ETB</label>
            <input class="form-control" id="partner-upgrade-price" name="upgrade_price" type="number" min="0" step="0.01" value="<?= html_escape(number_format((float) ($partnerSettings['upgrade_price'] ?? 0), 2, '.', '')) ?>" required>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" id="partner-enabled" name="enabled" type="checkbox" value="1" <?= !empty($partnerSettings['enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="partner-enabled">Enable VIP Club upgrades</label>
        </div>
        <p class="form-text">Upgrades are permanent. Partners keep the regular 15% / 5% / 3% team commissions, gain another 9% from direct invitees, and receive a 3% bonus on their own daily product profit.</p>
        <button class="btn btn-primary" type="submit" data-loading-button>Save VIP Club settings</button>
    </form>
</section>

<section class="data-panel mb-4">
    <div class="data-panel-header"><h2>Create a coupon</h2></div>
    <form method="post" class="p-4" data-loading-form>
        <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_coupon">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="coupon-code">Coupon code <span class="muted">(optional; generated if blank)</span></label>
                <input class="form-control" id="coupon-code" name="code" maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9_-]{2,39}" placeholder="Generate a shared code">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="coupon-discount">Fixed discount · ETB</label>
                <input class="form-control" id="coupon-discount" name="discount_amount" type="number" min="0.01" step="0.01" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="coupon-limit">Maximum redemptions <span class="muted">(optional)</span></label>
                <input class="form-control" id="coupon-limit" name="max_redemptions" type="number" min="1" step="1" placeholder="Unlimited">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="coupon-expiry">Expires at <span class="muted">(optional)</span></label>
                <input class="form-control" id="coupon-expiry" name="expires_at" type="datetime-local">
            </div>
        </div>
        <p class="form-text mt-3">Each user can redeem a coupon once on one product. A coupon discount must be less than the product price.</p>
        <button class="btn btn-primary" type="submit" data-loading-button>Create coupon</button>
    </form>
</section>

<section class="data-panel">
    <div class="data-panel-header"><h2>Coupons</h2></div>
    <?php if ($coupons === []): ?>
        <div class="p-4 muted">No coupons have been created.</div>
    <?php else: ?>
        <div class="p-3 d-grid gap-3">
            <?php foreach ($coupons as $coupon): ?>
                <article class="coupon-admin-card">
                    <form method="post" class="row g-2 align-items-end" data-loading-form>
                        <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_coupon">
                        <input type="hidden" name="coupon_id" value="<?= (int) $coupon['id'] ?>">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label" for="coupon-code-<?= (int) $coupon['id'] ?>">Code · all users</label>
                            <input class="form-control" id="coupon-code-<?= (int) $coupon['id'] ?>" name="code" maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9_-]{2,39}" value="<?= html_escape((string) $coupon['code']) ?>" required>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="coupon-discount-<?= (int) $coupon['id'] ?>">Discount · ETB</label>
                            <input class="form-control" id="coupon-discount-<?= (int) $coupon['id'] ?>" name="discount_amount" type="number" min="0.01" step="0.01" value="<?= html_escape(number_format((float) $coupon['discount_amount'], 2, '.', '')) ?>" required>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="coupon-limit-<?= (int) $coupon['id'] ?>">Max uses</label>
                            <input class="form-control" id="coupon-limit-<?= (int) $coupon['id'] ?>" name="max_redemptions" type="number" min="<?= max(1, (int) $coupon['redemption_count']) ?>" step="1" value="<?= $coupon['max_redemptions'] === null ? '' : (int) $coupon['max_redemptions'] ?>" placeholder="Unlimited">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label" for="coupon-expiry-<?= (int) $coupon['id'] ?>">Expires</label>
                            <input class="form-control" id="coupon-expiry-<?= (int) $coupon['id'] ?>" name="expires_at" type="datetime-local" value="<?= !empty($coupon['expires_at']) ? html_escape(date('Y-m-d\TH:i', strtotime((string) $coupon['expires_at']))) : '' ?>">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2 d-grid">
                            <button class="btn btn-outline-light" type="submit" data-loading-button>Save coupon</button>
                        </div>
                    </form>
                    <div class="coupon-admin-meta">
                        <span><?= (int) $coupon['redemption_count'] ?> used<?= $coupon['max_redemptions'] === null ? ' · unlimited' : ' of ' . (int) $coupon['max_redemptions'] ?></span>
                        <span>Status: <?= html_escape(ucfirst((string) $coupon['status'])) ?></span>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                            <input type="hidden" name="action" value="toggle_coupon">
                            <input type="hidden" name="coupon_id" value="<?= (int) $coupon['id'] ?>">
                            <input type="hidden" name="next_status" value="<?= $coupon['status'] === 'active' ? 'inactive' : 'active' ?>">
                            <button class="btn btn-sm btn-outline-light" type="submit"><?= $coupon['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
