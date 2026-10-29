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

        if ($action === 'buy_product') {
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
            $couponInput = $_POST['coupon_code'] ?? '';
            $couponCode = is_string($couponInput) ? strtoupper(trim($couponInput)) : '';

            if (!$productId) {
                $error = 'Invalid product.';
            } elseif ($couponCode !== '' && preg_match('/\A[A-Z0-9][A-Z0-9_-]{2,39}\z/', $couponCode) !== 1) {
                $error = 'Enter a valid coupon code.';
            } else {
                try {
                    $db->beginTransaction();

                    $productStmt = $db->prepare(
                        "SELECT id, name, description, image_path, price, profit, profit_duration_days, quantity, status, published
                         FROM products
                         WHERE id = ?
                         LIMIT 1
                         FOR UPDATE"
                    );
                    $productStmt->execute([$productId]);
                    $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$product) {
                        throw new RuntimeException('This product is no longer available.');
                    }

                    if ((int) $product['published'] !== 1 || $product['status'] !== 'active') {
                        throw new RuntimeException('This product is not available for purchase.');
                    }

                    if ((int) $product['quantity'] <= 0) {
                        throw new RuntimeException('This product is sold out.');
                    }

                    $existingStmt = $db->prepare(
                        'SELECT id FROM marketplace_purchases WHERE user_id = ? AND product_id = ? LIMIT 1'
                    );
                    $existingStmt->execute([$userId, $productId]);
                    if ($existingStmt->fetchColumn() !== false) {
                        throw new RuntimeException('You already own this product.');
                    }

                    $coupon = null;
                    $discount = 0.0;
                    if ($couponCode !== '') {
                        $couponStmt = $db->prepare(
                            "SELECT id, discount_amount, max_redemptions
                             FROM marketplace_coupons
                             WHERE code = ? AND status = 'active'
                               AND (expires_at IS NULL OR expires_at > NOW())
                             LIMIT 1
                             FOR UPDATE"
                        );
                        $couponStmt->execute([$couponCode]);
                        $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$coupon) {
                            throw new RuntimeException('This coupon is invalid, expired, or inactive.');
                        }

                        $userCouponStmt = $db->prepare(
                            'SELECT id FROM marketplace_coupon_redemptions WHERE coupon_id = ? AND user_id = ? LIMIT 1'
                        );
                        $userCouponStmt->execute([(int) $coupon['id'], $userId]);
                        if ($userCouponStmt->fetchColumn() !== false) {
                            throw new RuntimeException('You have already used this coupon.');
                        }

                        if ($coupon['max_redemptions'] !== null) {
                            $redemptionCountStmt = $db->prepare(
                                'SELECT COUNT(*) FROM marketplace_coupon_redemptions WHERE coupon_id = ?'
                            );
                            $redemptionCountStmt->execute([(int) $coupon['id']]);
                            if ((int) $redemptionCountStmt->fetchColumn() >= (int) $coupon['max_redemptions']) {
                                throw new RuntimeException('This coupon has reached its redemption limit.');
                            }
                        }

                        $discount = (float) $coupon['discount_amount'];
                        if ($discount >= (float) $product['price']) {
                            throw new RuntimeException('This coupon discount must be less than the product price.');
                        }
                    }

                    $walletStmt = $db->prepare(
                        'SELECT id, balance, locked_balance, currency
                         FROM wallets
                         WHERE user_id = ? AND currency = "ETB"
                         ORDER BY id ASC
                         LIMIT 1
                         FOR UPDATE'
                    );
                    $walletStmt->execute([$userId]);
                    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$wallet) {
                        throw new RuntimeException('Your wallet is not ready.');
                    }

                    $price = (float) $product['price'] - $discount;
                    $priceAmount = number_format($price, 8, '.', '');
                    $debitAmount = '-' . $priceAmount;
                    $availableBalance = max(0.0, (float) $wallet['balance'] - (float) $wallet['locked_balance']);

                    if ($availableBalance < $price) {
                        throw new RuntimeException('You do not have enough balance to buy this product.');
                    }

                    $balanceBefore = (string) $wallet['balance'];

                    $walletUpdateStmt = $db->prepare(
                        'UPDATE wallets
                         SET balance = balance - ?
                         WHERE id = ? AND user_id = ?'
                    );
                    $walletUpdateStmt->execute([
                        $priceAmount,
                        (int) $wallet['id'],
                        $userId,
                    ]);
                    if ($walletUpdateStmt->rowCount() !== 1) {
                        throw new RuntimeException('Unable to debit your wallet for this purchase.');
                    }

                    $balanceAfterStmt = $db->prepare('SELECT balance FROM wallets WHERE id = ? AND user_id = ?');
                    $balanceAfterStmt->execute([(int) $wallet['id'], $userId]);
                    $balanceAfter = $balanceAfterStmt->fetchColumn();
                    if (!is_string($balanceAfter)) {
                        throw new RuntimeException('Unable to verify your wallet balance after purchase.');
                    }

                    $transactionStmt = $db->prepare(
                        'INSERT INTO transactions
                        (user_id, wallet_id, type, amount, balance_before, balance_after, reference, description, status)
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, "completed")'
                    );

                    $reference = 'PRODUCT-' . strtoupper(bin2hex(random_bytes(8)));
                    $transactionStmt->execute([
                        $userId,
                        (int) $wallet['id'],
                        'BET',
                        $debitAmount,
                        $balanceBefore,
                        $balanceAfter,
                        $reference,
                        'Purchase of ' . (string) $product['name'] . ($coupon !== null ? ' with coupon ' . $couponCode : ''),
                    ]);

                    $purchaseStmt = $db->prepare(
                        'INSERT INTO marketplace_purchases
                        (user_id, product_id, product_name, image_path, price, daily_profit, duration_days, quantity, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, "active")'
                    );
                    $purchaseStmt->execute([
                        $userId,
                        (int) $product['id'],
                        (string) $product['name'],
                        $product['image_path'],
                        $price,
                        (float) $product['profit'],
                        (int) $product['profit_duration_days'],
                    ]);
                    $purchaseId = (int) $db->lastInsertId();

                    if ($coupon !== null) {
                        $redemptionStmt = $db->prepare("
                            INSERT INTO marketplace_coupon_redemptions (coupon_id, user_id, purchase_id, discount_amount)
                            VALUES (?, ?, ?, ?)
                        ");
                        $redemptionStmt->execute([(int) $coupon['id'], $userId, $purchaseId, number_format($discount, 2, '.', '')]);
                    }

                    $productQtyUpdate = $db->prepare(
                        'UPDATE products
                         SET quantity = quantity - 1
                         WHERE id = ? AND quantity > 0'
                    );
                    $productQtyUpdate->execute([$productId]);

                    $db->commit();
                    $message = 'Product purchased successfully' . ($coupon !== null ? ' with ' . number_format($discount, 2) . ' ETB coupon savings' : '') . '. It is now in your My Products tab.';
                    $_SESSION['applied_coupon_code'] = '';
                } catch (Throwable $caught) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    error_log('Product purchase error: ' . $caught->getMessage());
                    $error = $caught instanceof RuntimeException && $caught->getMessage() !== ''
                        ? $caught->getMessage()
                        : 'Unable to complete the purchase. Please try again.';
                }
            }
        }
    }
}

$stmt = $db->query(
    "SELECT id, name, description, image_path, price, profit, profit_duration_days, quantity, status, published, created_at
     FROM products
     WHERE status = 'active' AND published = 1
     ORDER BY created_at DESC"
);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Products';
$pageDescription = 'Buy trusted products and grow a daily profit from each item you own.';
$activeNav = 'products';

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="container-fluid py-4">
    <section class="market-action-shortcuts mb-4" aria-label="Rewards and membership">
        <button class="market-action-shortcut" type="button" data-bs-toggle="modal" data-bs-target="#couponModal">
            <img src="assets/images/copoun.png" alt="" class="market-action-shortcut-image">
            <span>Coupon</span>
            <small>Save on a purchase</small>
        </button>
        <a class="market-action-shortcut vip-club-shortcut" href="vip-club.php">
            <img src="assets/images/vipclub.png" alt="" class="market-action-shortcut-image">
            <span>VIP Club</span>
            <small>Unlock Partner rewards</small>
        </a>
        <a class="market-action-shortcut" href="daily-streak.php">
            <span class="market-action-shortcut-icon streak-shortcut-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
            <span>Daily streak</span>
            <small>Claim your daily reward</small>
        </a>
    </section>

    <div class="coupon-applied-note" id="coupon-applied-note" role="status" hidden></div>

    <div class="mb-4">
        <span class="eyebrow">Selam Market</span>
        <h1 class="h3 mb-2">Buy products</h1>
        <p class="text-secondary mb-0">
            Choose a product, pay once, and collect daily profit from the items in your account.
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

    <?php if (empty($products)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-bag fs-1 text-secondary"></i>
                <h4 class="mt-3">No products available</h4>
                <p class="text-secondary mb-0">New products from the admin will appear here soon.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-2 g-md-3 market-product-grid">
            <?php foreach ($products as $product): ?>
                <div class="col">
                    <div class="card market-product-card h-100">
                        <div class="card-body d-flex flex-column p-2 p-md-3">
                            <div class="market-product-stock mb-2">
                                <span class="badge bg-success-subtle text-success">
                                    <?= (int) $product['quantity'] < 1 ? 'Sold out' : 'In stock' ?>
                                </span>
                            </div>

                            <?php if (!empty($product['image_path'])): ?>
                                <img
                                    src="<?= htmlspecialchars((string) $product['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    class="market-product-image mb-2"
                                >
                            <?php else: ?>
                                <div class="market-product-placeholder mb-2" aria-hidden="true"><i class="bi bi-bag"></i></div>
                            <?php endif; ?>

                            <h2 class="market-product-name"><?= htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8') ?></h2>

                            <?php if (!empty($product['description'])): ?>
                                <p class="market-product-description"><?= htmlspecialchars((string) $product['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>

                            <div class="market-product-price">
                                <?= number_format((float) $product['price'], 2) ?>
                                <small>ETB</small>
                            </div>
                            <div class="market-product-profit">
                                +<?= number_format((float) $product['profit'], 2) ?> ETB daily
                                <span>· <?= (int) $product['profit_duration_days'] ?> days</span>
                            </div>

                            <form method="post" class="market-product-buy mt-2">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="buy_product">
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <input type="hidden" name="coupon_code" value="" data-coupon-input>
                                <button type="submit" class="btn btn-primary w-100" <?= (int) $product['quantity'] < 1 ? 'disabled' : '' ?>>
                                    <i class="bi bi-bag-check me-1" aria-hidden="true"></i><?= (int) $product['quantity'] < 1 ? 'Sold out' : 'Buy' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="couponModal" tabindex="-1" aria-labelledby="couponModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="couponModalTitle">Use a coupon</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                <label class="form-label" for="coupon-code-entry">Coupon code</label>
                <input class="form-control" id="coupon-code-entry" maxlength="40" autocomplete="off" placeholder="Enter your coupon code">
                <p class="form-text mb-0 mt-2">Check your shared coupon code, then apply its ETB discount to an eligible product. Each coupon can be used once per user.</p>
                <div class="coupon-win-message mt-3" id="coupon-win-message" role="status" hidden></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-light" type="button" id="clear-coupon-code">Clear</button>
                <button class="btn btn-primary" type="button" id="apply-coupon-code">Apply coupon</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const codeInput = document.getElementById('coupon-code-entry');
    const applyButton = document.getElementById('apply-coupon-code');
    const clearButton = document.getElementById('clear-coupon-code');
    const appliedNote = document.getElementById('coupon-applied-note');
    const winMessage = document.getElementById('coupon-win-message');
    const csrfInput = document.querySelector('#couponModal input[name="csrf_token"]');
    const couponFields = document.querySelectorAll('[data-coupon-input]');
    if (!codeInput || !applyButton || !clearButton || !appliedNote || !winMessage || !csrfInput) return;

    const setCoupon = (value, discountAmount = '') => {
        const code = value.trim().toUpperCase();
        codeInput.value = code;
        couponFields.forEach((field) => { field.value = code; });
        appliedNote.hidden = code === '';
        appliedNote.textContent = code === ''
            ? ''
            : discountAmount === ''
                ? `Coupon ${code} saved. It will be checked when you purchase.`
                : `Coupon ${code} applied · ETB ${discountAmount} discount on your next eligible purchase.`;
        try {
            if (code === '') {
                sessionStorage.removeItem('selam_market_coupon');
                sessionStorage.removeItem('selam_market_coupon_amount');
            } else {
                sessionStorage.setItem('selam_market_coupon', code);
                sessionStorage.setItem('selam_market_coupon_amount', discountAmount);
            }
        } catch (error) {
            console.error('Unable to save the applied coupon for this session.', error);
        }
    };

    try {
        setCoupon(
            sessionStorage.getItem('selam_market_coupon') || '',
            sessionStorage.getItem('selam_market_coupon_amount') || ''
        );
    } catch (error) {
        console.error('Unable to read the applied coupon for this session.', error);
    }

    const dropConfetti = () => {
        const colors = ['#20e68a', '#f4c542', '#ff6b6b', '#64b5f6', '#c084fc'];
        const pieces = [];
        for (let index = 0; index < 70; index++) {
            const piece = document.createElement('span');
            piece.className = 'coupon-confetti-piece';
            piece.style.left = `${Math.random() * 100}%`;
            piece.style.backgroundColor = colors[index % colors.length];
            piece.style.setProperty('--confetti-drift', `${Math.round(Math.random() * 240 - 120)}px`);
            piece.style.setProperty('--confetti-duration', `${1.2 + Math.random() * 1.4}s`);
            piece.style.setProperty('--confetti-delay', `${Math.random() * 0.35}s`);
            document.body.append(piece);
            pieces.push(piece);
        }
        window.setTimeout(() => pieces.forEach((piece) => piece.remove()), 2200);
    };

    applyButton.addEventListener('click', async () => {
        const code = codeInput.value.trim().toUpperCase();
        if (!/^[A-Z0-9][A-Z0-9_-]{2,39}$/.test(code)) {
            winMessage.hidden = false;
            winMessage.classList.remove('is-success');
            winMessage.textContent = 'Enter a valid coupon code.';
            return;
        }

        applyButton.disabled = true;
        applyButton.setAttribute('aria-busy', 'true');
        winMessage.hidden = true;
        try {
            const response = await fetch('api/coupon-preview.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: new URLSearchParams({ code, csrf_token: csrfInput.value }),
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const result = await response.json();
            if (!response.ok || result.success !== true || typeof result.code !== 'string' || typeof result.discount_amount !== 'string') {
                throw new Error(typeof result.message === 'string' ? result.message : 'This coupon could not be applied.');
            }

            setCoupon(result.code, result.discount_amount);
            winMessage.textContent = `You unlocked an ETB ${result.discount_amount} product discount! It will be deducted from an eligible purchase.`;
            winMessage.classList.add('is-success');
            winMessage.hidden = false;
            dropConfetti();
            window.setTimeout(() => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('couponModal')).hide();
            }, 1800);
        } catch (error) {
            winMessage.textContent = error instanceof Error ? error.message : 'Unable to check this coupon right now. Please try again.';
            winMessage.classList.remove('is-success');
            winMessage.hidden = false;
        } finally {
            applyButton.disabled = false;
            applyButton.removeAttribute('aria-busy');
        }
    });

    clearButton.addEventListener('click', () => {
        setCoupon('');
        winMessage.hidden = true;
        winMessage.textContent = '';
    });
});
</script>

<?php
if (file_exists(__DIR__ . '/includes/app_footer.php')) {
    require_once __DIR__ . '/includes/app_footer.php';
}
?>