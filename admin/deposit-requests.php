<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_auth.php';

$adminId = require_active_admin();
$db = database();

$pageTitle = 'Deposit Requests';
$adminActive = 'deposits';
$adminRoot = './';

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Process deposit actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (!csrf_token_is_valid($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException(
                'Invalid security token. Please refresh the page.'
            );
        }

        $action = $_POST['action'] ?? '';

        $depositId = filter_var(
            $_POST['deposit_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$depositId) {
            throw new RuntimeException(
                'Invalid deposit request.'
            );
        }

        $db->beginTransaction();

        /*
         * Lock deposit request.
         */
        $depositStmt = $db->prepare("
            SELECT
                id,
                user_id,
                payment_account_id,
                provider,
                amount,
                currency,
                receipt_url,
                transaction_reference,
                status,
                rejection_reason
            FROM deposit_requests
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $depositStmt->execute([$depositId]);

        $deposit = $depositStmt->fetch(PDO::FETCH_ASSOC);

        if (!$deposit) {
            throw new RuntimeException(
                'Deposit request not found.'
            );
        }

        if ($deposit['status'] !== 'pending') {
            throw new RuntimeException(
                'This deposit request has already been processed.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Reject
        |--------------------------------------------------------------------------
        */

        if ($action === 'reject') {

            $reason = trim(
                (string)($_POST['rejection_reason'] ?? '')
            );

            if ($reason === '') {
                $reason = 'Payment could not be verified.';
            }

            if (mb_strlen($reason) > 500) {
                $reason = mb_substr($reason, 0, 500);
            }

            $rejectStmt = $db->prepare("
                UPDATE deposit_requests
                SET
                    status = 'rejected',
                    rejection_reason = ?
                WHERE id = ?
                  AND status = 'pending'
            ");

            $rejectStmt->execute([
                $reason,
                $depositId
            ]);

            $logStmt = $db->prepare("
                INSERT INTO activity_logs (
                    user_id, admin_id, action, description, ip_address, user_agent, created_at
                )
                VALUES (?, ?, 'deposit_rejected', ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                (int) $deposit['user_id'],
                $adminId,
                'Deposit #' . $depositId . ' rejected: ' . $reason,
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            ]);

            $db->commit();

            $message = 'Deposit request rejected.';
        }

        /*
        |--------------------------------------------------------------------------
        | Approve
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'approve') {

            $userId = (int)$deposit['user_id'];

            /*
             * Keep the amount as a decimal string instead of
             * relying on floating-point arithmetic.
             */
            $amount = number_format(
                (float)$deposit['amount'],
                8,
                '.',
                ''
            );

            $currency = strtoupper(
                trim((string)$deposit['currency'])
            );

            if ((float)$amount <= 0) {
                throw new RuntimeException(
                    'Invalid deposit amount.'
                );
            }

            /*
             * Find and lock user's wallet.
             */
            $walletStmt = $db->prepare("
                SELECT
                    id,
                    user_id,
                    currency,
                    balance,
                    locked_balance
                FROM wallets
                WHERE user_id = ?
                  AND currency = ?
                LIMIT 1
                FOR UPDATE
            ");

            $walletStmt->execute([
                $userId,
                $currency
            ]);

            $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

            /*
             * Existing wallet fallback.
             */
            if (!$wallet && $currency === 'ETB') {

                $walletStmt = $db->prepare("
                    SELECT
                        id,
                        user_id,
                        currency,
                        balance,
                        locked_balance
                    FROM wallets
                    WHERE user_id = ?
                    ORDER BY id ASC
                    LIMIT 1
                    FOR UPDATE
                ");

                $walletStmt->execute([
                    $userId
                ]);

                $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

                if ($wallet) {

                    $walletCurrency = strtoupper(
                        trim((string)$wallet['currency'])
                    );

                    if ($walletCurrency !== 'ETB') {

                        $changeCurrency = $db->prepare("
                            UPDATE wallets
                            SET currency = 'ETB'
                            WHERE id = ?
                        ");

                        $changeCurrency->execute([
                            $wallet['id']
                        ]);

                        $wallet['currency'] = 'ETB';
                    }
                }
            }

            if (!$wallet) {
                throw new RuntimeException(
                    'The user wallet was not found.'
                );
            }

            $walletId = (int)$wallet['id'];

            /*
             * Ledger balances.
             */
            $balanceBefore = number_format(
                (float)$wallet['balance'],
                8,
                '.',
                ''
            );

            $balanceAfter = number_format(
                (float)$balanceBefore + (float)$amount,
                8,
                '.',
                ''
            );

            if ((float)$balanceAfter < (float)$balanceBefore) {
                throw new RuntimeException(
                    'Invalid wallet balance calculation.'
                );
            }

            /*
             * Unique transaction reference.
             */
            do {

                $reference =
                    'DEP-' .
                    $depositId .
                    '-' .
                    strtoupper(
                        bin2hex(random_bytes(6))
                    );

                $referenceCheck = $db->prepare("
                    SELECT id
                    FROM transactions
                    WHERE reference = ?
                    LIMIT 1
                ");

                $referenceCheck->execute([
                    $reference
                ]);

            } while ($referenceCheck->fetchColumn());

            /*
             * Update wallet.
             */
            $walletUpdate = $db->prepare("
                UPDATE wallets
                SET balance = ?
                WHERE id = ?
            ");

            $walletUpdate->execute([
                $balanceAfter,
                $walletId
            ]);

            /*
             * Create ledger transaction.
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
                )
                VALUES (
                    ?,
                    ?,
                    'DEPOSIT',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'completed'
                )
            ");

            $transactionStmt->execute([
                $userId,
                $walletId,
                $amount,
                $balanceBefore,
                $balanceAfter,
                $reference,
                'Deposit approved by admin'
            ]);

            /*
             * Mark deposit verified.
             */
            $verifyStmt = $db->prepare("
                UPDATE deposit_requests
                SET
                    status = 'verified',
                    verified_at = NOW(),
                    rejection_reason = NULL
                WHERE id = ?
                  AND status = 'pending'
            ");

            $verifyStmt->execute([
                $depositId
            ]);

            if ($verifyStmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Unable to update the deposit status.'
                );
            }

            /*
             * Activity log.
             */
            $logStmt = $db->prepare("
                INSERT INTO activity_logs (
                    user_id, admin_id, action, description, ip_address, user_agent, created_at
                )
                VALUES (?, ?, 'deposit_approved', ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $userId,
                $adminId,
                'Deposit #' . $depositId . ' approved for ' . number_format((float) $amount, 2) . ' ' . $currency,
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            ]);

            $db->commit();

            $message =
                'Deposit approved successfully. ' .
                number_format(
                    (float)$amount,
                    2
                ) .
                ' ' .
                $currency .
                ' has been added to the user wallet.';
        }

        else {

            throw new RuntimeException(
                'Invalid deposit action.'
            );
        }

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $error = $e->getMessage();

        error_log(
            'Deposit approval error: ' .
            $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| Load deposit requests
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        d.id,
        d.user_id,
        d.payment_account_id,
        d.provider,
        d.amount,
        d.currency,
        d.receipt_url,
        d.transaction_reference,
        d.status,
        d.rejection_reason,
        d.verified_at,
        d.created_at,
        u.username,
        u.phone
    FROM deposit_requests d
    INNER JOIN users u
        ON u.id = d.user_id
    ORDER BY
        CASE
            WHEN d.status = 'pending' THEN 0
            WHEN d.status = 'verified' THEN 1
            ELSE 2
        END,
        d.id DESC
");

$deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalRequests = count($deposits);

$pendingCount = 0;
$verifiedCount = 0;
$rejectedCount = 0;
$pendingAmount = 0;

foreach ($deposits as $item) {

    $itemStatus = strtolower(
        (string)$item['status']
    );

    if ($itemStatus === 'pending') {
        $pendingCount++;
        $pendingAmount += (float)$item['amount'];
    }

    if ($itemStatus === 'verified') {
        $verifiedCount++;
    }

    if ($itemStatus === 'rejected') {
        $rejectedCount++;
    }
}

require __DIR__ . '/../includes/admin_header.php';
?>

<style>

/* =========================================================
   GOFERBET DEPOSIT ADMIN
   ========================================================= */

.deposit-page {
    color: #f8fafc;
}

.deposit-header {
    margin-bottom: 28px;
}

.deposit-title {
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -0.5px;
    margin-bottom: 6px;
}

.deposit-subtitle {
    color: #94a3b8;
    margin: 0;
}


/* =========================================================
   STAT CARDS
   ========================================================= */

.deposit-stat {
    background:
        linear-gradient(
            145deg,
            #172033 0%,
            #111827 100%
        );
    border: 1px solid #263449;
    border-radius: 18px;
    padding: 20px;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.deposit-stat::after {
    content: "";
    position: absolute;
    width: 90px;
    height: 90px;
    right: -30px;
    top: -30px;
    border-radius: 50%;
    background: rgba(34, 197, 94, 0.07);
}

.deposit-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(34, 197, 94, 0.12);
    color: #22c55e;
    font-size: 20px;
    margin-bottom: 15px;
}

.deposit-stat-value {
    font-size: 25px;
    line-height: 1;
    font-weight: 800;
    color: #f8fafc;
}

.deposit-stat-label {
    margin-top: 8px;
    color: #94a3b8;
    font-size: 13px;
}


/* =========================================================
   ALERTS
   ========================================================= */

.deposit-alert {
    border-radius: 14px;
    border: 1px solid;
    padding: 15px 18px;
    margin-bottom: 22px;
}

.deposit-alert-success {
    background: rgba(34, 197, 94, 0.09);
    border-color: rgba(34, 197, 94, 0.25);
    color: #86efac;
}

.deposit-alert-danger {
    background: rgba(239, 68, 68, 0.09);
    border-color: rgba(239, 68, 68, 0.25);
    color: #fca5a5;
}


/* =========================================================
   REQUEST AREA
   ========================================================= */

.deposit-panel {
    background: #111827;
    border: 1px solid #263449;
    border-radius: 20px;
    overflow: hidden;
}

.deposit-panel-header {
    padding: 20px 22px;
    border-bottom: 1px solid #263449;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.deposit-panel-title {
    font-size: 17px;
    font-weight: 750;
    margin: 0;
}

.deposit-panel-count {
    background: #1d2939;
    color: #94a3b8;
    border-radius: 20px;
    padding: 5px 11px;
    font-size: 12px;
}


/* =========================================================
   REQUEST CARD
   ========================================================= */

.deposit-list {
    padding: 16px;
}

.deposit-card {
    background: #172033;
    border: 1px solid #263449;
    border-radius: 18px;
    padding: 20px;
    margin-bottom: 14px;
    transition: border-color .2s ease,
                transform .2s ease;
}

.deposit-card:last-child {
    margin-bottom: 0;
}

.deposit-card:hover {
    border-color: rgba(34, 197, 94, 0.35);
    transform: translateY(-1px);
}


/* =========================================================
   CARD TOP
   ========================================================= */

.deposit-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 20px;
}

.deposit-user {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.deposit-avatar {
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    border-radius: 14px;
    background:
        linear-gradient(
            135deg,
            #22c55e,
            #16a34a
        );
    color: #06120a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 17px;
}

.deposit-user-name {
    font-weight: 750;
    color: #f8fafc;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.deposit-user-phone {
    color: #64748b;
    font-size: 12px;
    margin-top: 3px;
}


/* =========================================================
   STATUS
   ========================================================= */

.deposit-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 30px;
    padding: 6px 10px;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}

.deposit-status-pending {
    background: rgba(245, 158, 11, .12);
    color: #fbbf24;
}

.deposit-status-verified {
    background: rgba(34, 197, 94, .12);
    color: #4ade80;
}

.deposit-status-rejected {
    background: rgba(239, 68, 68, .12);
    color: #f87171;
}


/* =========================================================
   AMOUNT
   ========================================================= */

.deposit-amount-box {
    background: #0b1220;
    border: 1px solid #263449;
    border-radius: 15px;
    padding: 17px;
    margin-bottom: 15px;
}

.deposit-amount-label {
    color: #64748b;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .6px;
    margin-bottom: 5px;
}

.deposit-amount {
    color: #22c55e;
    font-size: 26px;
    font-weight: 850;
}

.deposit-currency {
    color: #94a3b8;
    font-size: 13px;
    margin-left: 5px;
}


/* =========================================================
   INFORMATION GRID
   ========================================================= */

.deposit-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.deposit-info {
    background: #111827;
    border: 1px solid #263449;
    border-radius: 13px;
    padding: 12px;
    min-width: 0;
}

.deposit-info-label {
    color: #64748b;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 5px;
}

.deposit-info-value {
    color: #e2e8f0;
    font-size: 13px;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.deposit-reference {
    color: #38bdf8;
    font-family: monospace;
    font-size: 12px;
}


/* =========================================================
   CARD ACTIONS
   ========================================================= */

.deposit-actions {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
    margin-top: 17px;
    padding-top: 16px;
    border-top: 1px solid #263449;
}

.deposit-btn {
    min-height: 42px;
    border-radius: 11px;
    font-size: 13px;
    font-weight: 700;
    padding: 9px 14px;
}

.deposit-btn-receipt {
    background: #1d2939;
    border: 1px solid #334155;
    color: #cbd5e1;
}

.deposit-btn-receipt:hover {
    background: #263449;
    color: #fff;
}

.deposit-btn-approve {
    background: #22c55e;
    border: 1px solid #22c55e;
    color: #041108;
}

.deposit-btn-approve:hover {
    background: #16a34a;
    border-color: #16a34a;
}

.deposit-btn-reject {
    background: rgba(239, 68, 68, .10);
    border: 1px solid rgba(239, 68, 68, .30);
    color: #f87171;
}

.deposit-btn-reject:hover {
    background: rgba(239, 68, 68, .18);
    color: #fca5a5;
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.deposit-empty {
    text-align: center;
    padding: 70px 25px;
    color: #64748b;
}

.deposit-empty-icon {
    width: 65px;
    height: 65px;
    margin: 0 auto 18px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #172033;
    border: 1px solid #263449;
    font-size: 26px;
    color: #475569;
}

.deposit-empty-title {
    color: #cbd5e1;
    font-weight: 700;
    margin-bottom: 6px;
}


/* =========================================================
   MODAL
   ========================================================= */

.deposit-modal .modal-content {
    background: #111827;
    color: #f8fafc;
    border: 1px solid #263449;
    border-radius: 18px;
}

.deposit-modal .modal-header {
    border-bottom-color: #263449;
}

.deposit-modal .modal-footer {
    border-top-color: #263449;
}

.deposit-modal .form-control {
    background: #0b1220;
    border-color: #263449;
    color: #f8fafc;
    border-radius: 12px;
}

.deposit-modal .form-control:focus {
    background: #0b1220;
    color: #f8fafc;
    border-color: #22c55e;
    box-shadow: 0 0 0 .2rem rgba(34,197,94,.10);
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 575.98px) {

    .deposit-title {
        font-size: 23px;
    }

    .deposit-page {
        padding-left: 5px;
        padding-right: 5px;
    }

    .deposit-stat {
        padding: 16px;
    }

    .deposit-stat-value {
        font-size: 21px;
    }

    .deposit-panel-header {
        padding: 17px;
    }

    .deposit-list {
        padding: 10px;
    }

    .deposit-card {
        padding: 15px;
        border-radius: 16px;
    }

    .deposit-card-top {
        align-items: flex-start;
    }

    .deposit-status {
        font-size: 10px;
    }

    .deposit-info-grid {
        grid-template-columns: 1fr;
    }

    .deposit-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .deposit-actions .deposit-btn-receipt {
        grid-column: 1 / -1;
    }

    .deposit-btn {
        width: 100%;
    }

}


/* =========================================================
   VERY SMALL SCREENS
   ========================================================= */

@media (max-width: 360px) {

    .deposit-card-top {
        flex-direction: column;
    }

    .deposit-status {
        align-self: flex-start;
    }

    .deposit-actions {
        grid-template-columns: 1fr;
    }

    .deposit-actions .deposit-btn-receipt {
        grid-column: auto;
    }

}

</style>


<div class="container-fluid py-4 deposit-page">

    <!-- HEADER -->

    <div class="deposit-header">

        <h1 class="deposit-title">
            Deposit Requests
        </h1>

        <p class="deposit-subtitle">
            Review user deposits and manage wallet credits.
        </p>

    </div>


    <!-- ALERTS -->

    <?php if ($message !== ''): ?>

        <div class="deposit-alert deposit-alert-success">
            <i class="bi bi-check-circle-fill me-2"></i>

            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="deposit-alert deposit-alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>


    <!-- STATISTICS -->

    <div class="row g-3 mb-4">

        <div class="col-6 col-xl-3">

            <div class="deposit-stat">

                <div class="deposit-stat-icon">
                    <i class="bi bi-wallet2"></i>
                </div>

                <div class="deposit-stat-value">
                    <?= number_format($totalRequests) ?>
                </div>

                <div class="deposit-stat-label">
                    Total requests
                </div>

            </div>

        </div>


        <div class="col-6 col-xl-3">

            <div class="deposit-stat">

                <div class="deposit-stat-icon">
                    <i class="bi bi-hourglass-split"></i>
                </div>

                <div class="deposit-stat-value">
                    <?= number_format($pendingCount) ?>
                </div>

                <div class="deposit-stat-label">
                    Pending review
                </div>

            </div>

        </div>


        <div class="col-6 col-xl-3">

            <div class="deposit-stat">

                <div class="deposit-stat-icon">
                    <i class="bi bi-check2-circle"></i>
                </div>

                <div class="deposit-stat-value">
                    <?= number_format($verifiedCount) ?>
                </div>

                <div class="deposit-stat-label">
                    Verified deposits
                </div>

            </div>

        </div>


        <div class="col-6 col-xl-3">

            <div class="deposit-stat">

                <div class="deposit-stat-icon">
                    <i class="bi bi-cash-stack"></i>
                </div>

                <div class="deposit-stat-value">
                    <?= number_format(
                        $pendingAmount,
                        2
                    ) ?>
                </div>

                <div class="deposit-stat-label">
                    Pending amount
                </div>

            </div>

        </div>

    </div>


    <!-- REQUEST PANEL -->

    <div class="deposit-panel">

        <div class="deposit-panel-header">

            <div>

                <h2 class="deposit-panel-title">
                    Deposit Activity
                </h2>

            </div>

            <span class="deposit-panel-count">
                <?= number_format($totalRequests) ?>
                requests
            </span>

        </div>


        <div class="deposit-list">

            <?php if (!$deposits): ?>

                <div class="deposit-empty">

                    <div class="deposit-empty-icon">
                        <i class="bi bi-inbox"></i>
                    </div>

                    <div class="deposit-empty-title">
                        No deposit requests
                    </div>

                    <div>
                        New deposit requests will appear here.
                    </div>

                </div>

            <?php else: ?>

                <?php foreach ($deposits as $deposit): ?>

                    <?php

                    $status = strtolower(
                        (string)$deposit['status']
                    );

                    $statusClass = match ($status) {
                        'pending' =>
                            'deposit-status-pending',

                        'verified' =>
                            'deposit-status-verified',

                        'rejected' =>
                            'deposit-status-rejected',

                        default =>
                            'deposit-status-pending'
                    };

                    $statusIcon = match ($status) {
                        'pending' =>
                            'bi-hourglass-split',

                        'verified' =>
                            'bi-check-circle-fill',

                        'rejected' =>
                            'bi-x-circle-fill',

                        default =>
                            'bi-circle'
                    };

                    $username = trim(
                        (string)$deposit['username']
                    );

                    $avatarLetter =
                        strtoupper(
                            mb_substr(
                                $username !== ''
                                    ? $username
                                    : 'U',
                                0,
                                1
                            )
                        );

                    ?>

                    <div class="deposit-card">

                        <!-- CARD HEADER -->

                        <div class="deposit-card-top">

                            <div class="deposit-user">

                                <div class="deposit-avatar">
                                    <?= htmlspecialchars(
                                        $avatarLetter,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>

                                <div class="min-w-0">

                                    <div class="deposit-user-name">
                                        <?= htmlspecialchars(
                                            $username,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <?php if (
                                        !empty($deposit['phone'])
                                    ): ?>

                                        <div class="deposit-user-phone">
                                            <?= htmlspecialchars(
                                                (string)$deposit['phone'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>

                                    <?php else: ?>

                                        <div class="deposit-user-phone">
                                            User #<?= (int)$deposit['user_id'] ?>
                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>


                            <span
                                class="deposit-status <?= $statusClass ?>"
                            >

                                <i class="bi <?= $statusIcon ?>"></i>

                                <?= htmlspecialchars(
                                    ucfirst($status),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </div>


                        <!-- AMOUNT -->

                        <div class="deposit-amount-box">

                            <div class="deposit-amount-label">
                                Deposit amount
                            </div>

                            <div>

                                <span class="deposit-amount">
                                    <?= number_format(
                                        (float)$deposit['amount'],
                                        2
                                    ) ?>
                                </span>

                                <span class="deposit-currency">
                                    <?= htmlspecialchars(
                                        (string)$deposit['currency'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </div>

                        </div>


                        <!-- INFORMATION -->

                        <div class="deposit-info-grid">

                            <div class="deposit-info">

                                <div class="deposit-info-label">
                                    Provider
                                </div>

                                <div class="deposit-info-value">

                                    <i class="bi bi-bank me-1"></i>

                                    <?= htmlspecialchars(
                                        (string)$deposit['provider'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                            </div>


                            <div class="deposit-info">

                                <div class="deposit-info-label">
                                    Request ID
                                </div>

                                <div class="deposit-info-value">
                                    #<?= (int)$deposit['id'] ?>
                                </div>

                            </div>


                            <div class="deposit-info">

                                <div class="deposit-info-label">
                                    Transaction reference
                                </div>

                                <div class="deposit-info-value">

                                    <?php if (
                                        !empty(
                                            $deposit[
                                                'transaction_reference'
                                            ]
                                        )
                                    ): ?>

                                        <span class="deposit-reference">
                                            <?= htmlspecialchars(
                                                (string)$deposit[
                                                    'transaction_reference'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="text-secondary">
                                            Not provided
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>


                            <div class="deposit-info">

                                <div class="deposit-info-label">
                                    Submitted
                                </div>

                                <div class="deposit-info-value">

                                    <i class="bi bi-clock me-1"></i>

                                    <?= htmlspecialchars(
                                        (string)$deposit['created_at'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                            </div>

                        </div>


                        <!-- REJECTION REASON -->

                        <?php if (
                            $status === 'rejected' &&
                            !empty($deposit['rejection_reason'])
                        ): ?>

                            <div class="deposit-info mt-3">

                                <div class="deposit-info-label">
                                    Rejection reason
                                </div>

                                <div class="deposit-info-value text-danger">
                                    <?= htmlspecialchars(
                                        (string)$deposit[
                                            'rejection_reason'
                                        ],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>

                            </div>

                        <?php endif; ?>


                        <!-- ACTIONS -->

                        <div class="deposit-actions">

                            <?php if (
                                !empty($deposit['receipt_url'])
                            ): ?>

                                <a
                                    href="<?= htmlspecialchars(
                                        (string)$deposit['receipt_url'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn deposit-btn deposit-btn-receipt"
                                >
                                    <i class="bi bi-receipt me-1"></i>
                                    View Receipt
                                </a>

                            <?php endif; ?>


                            <?php if ($status === 'pending'): ?>

                                <!-- APPROVE -->

                                <form
                                    method="POST"
                                    class="m-0"
                                    onsubmit="return confirm('Approve this deposit and add the amount to the user wallet?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            csrf_token(),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="approve"
                                    >

                                    <input
                                        type="hidden"
                                        name="deposit_id"
                                        value="<?= (int)$deposit['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn deposit-btn deposit-btn-approve w-100"
                                    >
                                        <i class="bi bi-check-lg me-1"></i>
                                        Approve
                                    </button>

                                </form>


                                <!-- REJECT -->

                                <button
                                    type="button"
                                    class="btn deposit-btn deposit-btn-reject"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rejectModal<?= (int)$deposit['id'] ?>"
                                >
                                    <i class="bi bi-x-lg me-1"></i>
                                    Reject
                                </button>


                                <!-- REJECT MODAL -->

                                <div
                                    class="modal fade deposit-modal"
                                    id="rejectModal<?= (int)$deposit['id'] ?>"
                                    tabindex="-1"
                                    aria-hidden="true"
                                >

                                    <div class="modal-dialog modal-dialog-centered">

                                        <div class="modal-content">

                                            <div class="modal-header">

                                                <div>

                                                    <h5 class="modal-title">
                                                        Reject Deposit
                                                    </h5>

                                                    <small class="text-secondary">
                                                        Request #<?= (int)$deposit['id'] ?>
                                                    </small>

                                                </div>

                                                <button
                                                    type="button"
                                                    class="btn-close btn-close-white"
                                                    data-bs-dismiss="modal"
                                                    aria-label="Close"
                                                ></button>

                                            </div>


                                            <form method="POST">

                                                <div class="modal-body">

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= htmlspecialchars(
                                                            csrf_token(),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="reject"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="deposit_id"
                                                        value="<?= (int)$deposit['id'] ?>"
                                                    >


                                                    <div class="mb-3">

                                                        <label
                                                            class="form-label"
                                                            for="reason<?= (int)$deposit['id'] ?>"
                                                        >
                                                            Rejection reason
                                                        </label>

                                                        <textarea
                                                            class="form-control"
                                                            id="reason<?= (int)$deposit['id'] ?>"
                                                            name="rejection_reason"
                                                            rows="4"
                                                            maxlength="500"
                                                            required
                                                            placeholder="Enter why this deposit is being rejected."
                                                        ></textarea>

                                                    </div>

                                                </div>


                                                <div class="modal-footer">

                                                    <button
                                                        type="button"
                                                        class="btn btn-secondary"
                                                        data-bs-dismiss="modal"
                                                    >
                                                        Cancel
                                                    </button>

                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger"
                                                    >
                                                        <i class="bi bi-x-lg me-1"></i>
                                                        Reject Deposit
                                                    </button>

                                                </div>

                                            </form>

                                        </div>

                                    </div>

                                </div>

                            <?php elseif ($status === 'verified'): ?>

                                <div class="text-success small fw-semibold">

                                    <i class="bi bi-check-circle-fill me-1"></i>

                                    Wallet credited successfully

                                </div>

                            <?php elseif ($status === 'rejected'): ?>

                                <div class="text-danger small fw-semibold">

                                    <i class="bi bi-x-circle-fill me-1"></i>

                                    Deposit rejected

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>


<?php require __DIR__ . '/../includes/admin_footer.php'; ?>