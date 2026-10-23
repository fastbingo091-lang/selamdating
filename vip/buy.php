<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$packageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$packageId || $packageId <= 0) {
    header('Location: ../products.php?error=invalid_package');
    exit;
}

$db = database();

try {

    /*
    |--------------------------------------------------------------------------
    | Load VIP package
    |--------------------------------------------------------------------------
    */
    $packageStmt = $db->prepare("
        SELECT
            id,
            name,
            price,
            profit,
            product_count,
            status
        FROM vip_packages
        WHERE id = ?
        LIMIT 1
    ");

    $packageStmt->execute([$packageId]);
    $package = $packageStmt->fetch(PDO::FETCH_ASSOC);

    if (!$package) {
        header('Location: ../products.php?error=package_not_found');
        exit;
    }

    if ($package['status'] !== 'active') {
        header('Location: ../products.php?error=package_inactive');
        exit;
    }

    $price = (float) $package['price'];

    /*
    |--------------------------------------------------------------------------
    | Load user's wallet
    |--------------------------------------------------------------------------
    */
    $walletStmt = $db->prepare("
        SELECT
            id,
            balance,
            locked_balance,
            currency
        FROM wallets
        WHERE user_id = ?
        ORDER BY id ASC
        LIMIT 1
        FOR UPDATE
    ");

    /*
     * Transaction is started before FOR UPDATE.
     */
    $db->beginTransaction();

    $walletStmt->execute([$userId]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet) {
        throw new RuntimeException('Wallet not found.');
    }

    $walletId = (int) $wallet['id'];

    $balance = (float) ($wallet['balance'] ?? 0);
    $lockedBalance = (float) ($wallet['locked_balance'] ?? 0);

    $availableBalance = $balance - $lockedBalance;

    /*
    |--------------------------------------------------------------------------
    | Check balance
    |--------------------------------------------------------------------------
    */
    if ($availableBalance < $price) {
        $db->rollBack();

        header('Location: ../products.php?error=insufficient_balance');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Load package products
    |--------------------------------------------------------------------------
    */
    $productsStmt = $db->prepare("
        SELECT
            p.id,
            p.name,
            p.description,
            p.price,
            p.profit,
            p.status,
            vpp.quantity,
            vpp.sort_order
        FROM vip_package_products vpp
        INNER JOIN products p
            ON p.id = vpp.product_id
        WHERE
            vpp.vip_package_id = ?
            AND p.status = 'active'
        ORDER BY vpp.sort_order ASC, vpp.id ASC
    ");

    $productsStmt->execute([$packageId]);
    $products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

    $requiredProductCount = (int) $package['product_count'];

    /*
    |--------------------------------------------------------------------------
    | Make sure package has enough products
    |--------------------------------------------------------------------------
    */
    if ($requiredProductCount > 0 && count($products) < $requiredProductCount) {
        $db->rollBack();

        header('Location: ../products.php?error=package_products_missing');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate new wallet balance
    |--------------------------------------------------------------------------
    */
    $balanceBefore = $balance;
    $balanceAfter = $balance - $price;

    /*
    |--------------------------------------------------------------------------
    | Generate unique transaction reference
    |--------------------------------------------------------------------------
    */
    $reference = 'VIP-' . strtoupper(bin2hex(random_bytes(8)));

    /*
    |--------------------------------------------------------------------------
    | Create wallet transaction
    |--------------------------------------------------------------------------
    */
    $transactionStmt = $db->prepare("
        INSERT INTO transactions (
            user_id,
            wallet_id,
            type,
            amount,
            balance_before,
            balance_after,
            reference,
            description,
            status
        ) VALUES (
            ?,
            ?,
            'BET',
            ?,
            ?,
            ?,
            ?,
            ?,
            'completed'
        )
    ");

    $transactionDescription =
        'Purchase of ' . $package['name'];

    $transactionStmt->execute([
        $userId,
        $walletId,
        $price,
        number_format($balanceBefore, 8, '.', ''),
        number_format($balanceAfter, 8, '.', ''),
        $reference,
        $transactionDescription
    ]);

    /*
    |--------------------------------------------------------------------------
    | Update wallet balance
    |--------------------------------------------------------------------------
    */
    $walletUpdateStmt = $db->prepare("
        UPDATE wallets
        SET balance = ?
        WHERE id = ?
          AND user_id = ?
    ");

    $walletUpdateStmt->execute([
        number_format($balanceAfter, 8, '.', ''),
        $walletId,
        $userId
    ]);

    if ($walletUpdateStmt->rowCount() !== 1) {
        throw new RuntimeException('Unable to update wallet balance.');
    }

    /*
    |--------------------------------------------------------------------------
    | Create VIP purchase
    |--------------------------------------------------------------------------
    */
    $purchaseStmt = $db->prepare("
        INSERT INTO vip_purchases (
            user_id,
            vip_package_id,
            purchase_price,
            profit,
            status
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            'active'
        )
    ");

    $purchaseStmt->execute([
        $userId,
        $packageId,
        $price,
        (float) $package['profit']
    ]);

    $purchaseId = (int) $db->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Assign package products to user
    |--------------------------------------------------------------------------
    */
    $userProductStmt = $db->prepare("
        INSERT INTO user_products (
            user_id,
            vip_purchase_id,
            product_id,
            price,
            profit,
            quantity,
            status
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'pending'
        )
    ");

    foreach ($products as $product) {

        $quantity = max(1, (int) $product['quantity']);

        $userProductStmt->execute([
            $userId,
            $purchaseId,
            (int) $product['id'],
            (float) $product['price'],
            (float) $product['profit'],
            $quantity
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Commit everything
    |--------------------------------------------------------------------------
    */
    $db->commit();

    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */
    header(
        'Location: ../wallet/products.php?purchase=success&purchase_id='
        . $purchaseId
    );
    exit;

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback if transaction is still active
    |--------------------------------------------------------------------------
    */
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log(
        'VIP purchase error for user '
        . $userId
        . ': '
        . $e->getMessage()
    );

    header(
        'Location: ../products.php?error=purchase_failed'
    );
    exit;
}