<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$appRoot = '../';

$userId = (int) $_SESSION['user_id'];

$pageTitle = 'Wallet';
$pageDescription = 'Manage your wallet balance and transactions.';
$activeNav = 'wallet';

$db = database();

/*
|--------------------------------------------------------------------------
| Load wallet
|--------------------------------------------------------------------------
*/

$walletStmt = $db->prepare("
    SELECT
        id,
        currency,
        balance,
        locked_balance
    FROM wallets
    WHERE user_id = ?
    ORDER BY CASE WHEN currency = 'ETB' THEN 0 ELSE 1 END, id ASC
    LIMIT 1
");

$walletStmt->execute([$userId]);

$wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

$balance = 0.00;
$lockedBalance = 0.00;
$currency = 'ETB';
$walletId = null;

if ($wallet) {
    $walletId = (int) $wallet['id'];

    $balance = (float) ($wallet['balance'] ?? 0);
    $lockedBalance = (float) ($wallet['locked_balance'] ?? 0);

    /*
     * The application uses ETB.
     * Do not display old DEMO/USDT values.
     */
    $currency = 'ETB';
}


/*
|--------------------------------------------------------------------------
| Available balance
|--------------------------------------------------------------------------
*/

$availableBalance = max(
    0,
    $balance - $lockedBalance
);


/*
|--------------------------------------------------------------------------
| Recent transactions
|--------------------------------------------------------------------------
*/

$recentStmt = $db->prepare("
    SELECT
        id,
        type,
        amount,
        balance_after,
        description,
        status,
        created_at
    FROM transactions
    WHERE user_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 5
");

$recentStmt->execute([$userId]);

$recentTransactions = $recentStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Transaction helpers
|--------------------------------------------------------------------------
*/

function walletTransactionLabel(string $type): string
{
    return match ($type) {

        'DEPOSIT' => 'Deposit',

        'WITHDRAW' => 'Withdrawal',

        'TRANSFER_IN' => 'Transfer received',

        'TRANSFER_OUT' => 'Transfer sent',

        'ADMIN_ADJUSTMENT' => 'Balance adjustment',

        'BET' => 'Purchase',

        'WIN' => 'Profit',

        'REFUND' => 'Refund',

        default => ucwords(
            strtolower(
                str_replace('_', ' ', $type)
            )
        )
    };
}


function walletTransactionIcon(string $type): string
{
    return match ($type) {

        'DEPOSIT' => 'bi-arrow-down-left',

        'WITHDRAW' => 'bi-arrow-up-right',

        'TRANSFER_IN' => 'bi-arrow-down-left',

        'TRANSFER_OUT' => 'bi-arrow-up-right',

        'ADMIN_ADJUSTMENT' => 'bi-sliders',

        'BET' => 'bi-box-seam',

        'WIN' => 'bi-graph-up-arrow',

        'REFUND' => 'bi-arrow-counterclockwise',

        default => 'bi-arrow-left-right'
    };
}


function walletTransactionClass(string $type): string
{
    return match ($type) {

        'DEPOSIT',
        'TRANSFER_IN',
        'WIN',
        'REFUND' => 'wallet-amount-positive',

        'WITHDRAW',
        'TRANSFER_OUT',
        'BET' => 'wallet-amount-negative',

        default => ''
    };
}


function walletTransactionPrefix(string $type): string
{
    return match ($type) {

        'DEPOSIT',
        'TRANSFER_IN',
        'WIN',
        'REFUND' => '+',

        'WITHDRAW',
        'TRANSFER_OUT',
        'BET' => '-',

        default => ''
    };
}


require __DIR__ . '/../includes/app_header.php';
?>


<div class="wallet-screen">


    <!-- =================================================
         BALANCE
         ================================================= -->

    <section
        class="balance-card wallet-balance-card mb-4"
        aria-labelledby="wallet-balance-title"
    >

        <div class="wallet-balance-top">

            <div class="wallet-balance-label">

                <span class="wallet-balance-icon">

                    <i
                        class="bi bi-wallet2"
                        aria-hidden="true"
                    ></i>

                </span>


                <span>

                    <span class="balance-label">
                        Available balance
                    </span>

                    <span class="wallet-balance-caption">
                        Your current wallet balance
                    </span>

                </span>

            </div>


            <span class="status-badge success">
                Active
            </span>

        </div>


        <h2
            class="wallet-balance-value"
            id="wallet-balance-title"
        >

            <?= number_format($availableBalance, 2) ?>

            <span>
                <?= html_escape($currency) ?>
            </span>

        </h2>


        <p class="wallet-balance-note">

            Available funds in your wallet.

        </p>


        <?php if ($lockedBalance > 0): ?>

            <div class="wallet-locked-balance">

                <span>
                    Locked balance
                </span>

                <strong>
                    <?= number_format($lockedBalance, 2) ?>
                    <?= html_escape($currency) ?>
                </strong>

            </div>

        <?php endif; ?>

    </section>


    <!-- =================================================
         WALLET ACTIONS
         ================================================= -->

    <section
        class="wallet-shortcuts mb-4"
        aria-label="Wallet actions"
    >


        <a
            class="wallet-shortcut"
            href="deposit.php"
        >

            <span class="wallet-shortcut-icon">

                <i
                    class="bi bi-arrow-down-left"
                    aria-hidden="true"
                ></i>

            </span>

            <span>
                Deposit
            </span>

            <small>
                Add funds
            </small>

        </a>


        <a
            class="wallet-shortcut"
            href="withdraw.php"
        >

            <span class="wallet-shortcut-icon">

                <i
                    class="bi bi-arrow-up-right"
                    aria-hidden="true"
                ></i>

            </span>

            <span>
                Withdraw
            </span>

            <small>
                Request withdrawal
            </small>

        </a>


        <a
            class="wallet-shortcut"
            href="transfer.php"
        >

            <span class="wallet-shortcut-icon">

                <i
                    class="bi bi-send"
                    aria-hidden="true"
                ></i>

            </span>

            <span>
                Transfer
            </span>

            <small>
                Send funds
            </small>

        </a>


        <a
            class="wallet-shortcut"
            href="transactions.php"
        >

            <span class="wallet-shortcut-icon">

                <i
                    class="bi bi-receipt"
                    aria-hidden="true"
                ></i>

            </span>

            <span>
                History
            </span>

            <small>
                View transactions
            </small>

        </a>

    </section>


    <!-- =================================================
         DETAILS
         ================================================= -->

    <section class="wallet-details-grid">


        <!-- Recent Activity -->

        <div class="data-panel wallet-activity-panel">

            <div class="data-panel-header">

                <div>

                    <h2>
                        Recent activity
                    </h2>

                    <span class="small-note">
                        Latest wallet transactions
                    </span>

                </div>


                <a href="transactions.php">

                    View all

                    <i
                        class="bi bi-arrow-right"
                        aria-hidden="true"
                    ></i>

                </a>

            </div>


            <?php if (!$recentTransactions): ?>

                <div class="wallet-empty-state">

                    <span class="empty-icon">

                        <i
                            class="bi bi-arrow-left-right"
                            aria-hidden="true"
                        ></i>

                    </span>


                    <strong class="d-block mb-1">
                        No transactions yet
                    </strong>


                    <span class="small-note">
                        Your wallet activity will appear here.
                    </span>

                </div>

            <?php else: ?>


                <div class="wallet-recent-list">


                    <?php foreach ($recentTransactions as $transaction): ?>

                        <?php

                        $type = (string) $transaction['type'];

                        $amount = (float) $transaction['amount'];

                        $prefix = walletTransactionPrefix($type);

                        $amountClass = walletTransactionClass($type);

                        $transactionStatus = strtolower(
                            (string) $transaction['status']
                        );

                        ?>

                        <div class="wallet-recent-item">


                            <div class="wallet-recent-icon">

                                <i
                                    class="bi <?= html_escape(
                                        walletTransactionIcon($type)
                                    ) ?>"
                                    aria-hidden="true"
                                ></i>

                            </div>


                            <div class="wallet-recent-content">

                                <strong>

                                    <?= html_escape(
                                        walletTransactionLabel($type)
                                    ) ?>

                                </strong>


                                <span>

                                    <?= html_escape(
                                        (string) (
                                            $transaction['description']
                                            ?: 'Wallet transaction'
                                        )
                                    ) ?>

                                </span>


                                <small>

                                    <?= html_escape(
                                        date(
                                            'M d, Y H:i',
                                            strtotime(
                                                (string) $transaction['created_at']
                                            )
                                        )
                                    ) ?>

                                </small>

                            </div>


                            <div class="wallet-recent-amount">

                                <strong
                                    class="<?= html_escape($amountClass) ?>"
                                >

                                    <?= html_escape($prefix) ?>

                                    <?= number_format(
                                        $amount,
                                        2
                                    ) ?>

                                    <?= html_escape($currency) ?>

                                </strong>


                                <span
                                    class="wallet-mini-status wallet-status-<?= html_escape(
                                        $transactionStatus
                                    ) ?>"
                                >

                                    <?= html_escape(
                                        ucfirst($transactionStatus)
                                    ) ?>

                                </span>

                            </div>

                        </div>

                    <?php endforeach; ?>


                </div>

            <?php endif; ?>

        </div>


        <!-- Wallet Details -->

        <aside class="data-panel wallet-info-panel">

            <div class="data-panel-header">

                <h2>
                    Wallet details
                </h2>

            </div>


            <div class="wallet-info-list">


                <div>

                    <span class="muted">
                        Currency
                    </span>

                    <strong>
                        <?= html_escape($currency) ?>
                    </strong>

                </div>


                <div>

                    <span class="muted">
                        Balance
                    </span>

                    <strong>
                        <?= number_format($balance, 2) ?>
                        <?= html_escape($currency) ?>
                    </strong>

                </div>


                <div>

                    <span class="muted">
                        Available
                    </span>

                    <strong>
                        <?= number_format($availableBalance, 2) ?>
                        <?= html_escape($currency) ?>
                    </strong>

                </div>


                <div>

                    <span class="muted">
                        Locked
                    </span>

                    <strong>
                        <?= number_format($lockedBalance, 2) ?>
                        <?= html_escape($currency) ?>
                    </strong>

                </div>


                <div>

                    <span class="muted">
                        Wallet status
                    </span>

                    <span class="status-badge success">
                        Active
                    </span>

                </div>


            </div>

        </aside>


    </section>


</div>


<style>

.wallet-screen {
    width: 100%;
}


/* =====================================================
   BALANCE
   ===================================================== */

.wallet-balance-card {
    overflow: hidden;
}

.wallet-balance-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.wallet-balance-label {
    display: flex;
    align-items: center;
    gap: 11px;
}

.wallet-balance-icon {
    display: grid;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    place-items: center;
    border-radius: 13px;
    background: rgba(34,197,94,.12);
    color: var(--primary);
    font-size: 18px;
}

.wallet-balance-label > span:last-child {
    display: flex;
    flex-direction: column;
}

.balance-label {
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 600;
}

.wallet-balance-caption {
    margin-top: 2px;
    color: var(--text-secondary);
    font-size: 10px;
}

.wallet-balance-value {
    margin: 20px 0 5px;
    color: var(--text-primary);
    font-size: 34px;
    font-weight: 800;
    letter-spacing: -.035em;
    line-height: 1.1;
}

.wallet-balance-value span {
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0;
}

.wallet-balance-note {
    margin: 0;
    color: var(--text-secondary);
    font-size: 11px;
}

.wallet-locked-balance {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid rgba(255,255,255,.07);
    color: var(--text-secondary);
    font-size: 10px;
}

.wallet-locked-balance strong {
    color: var(--text-primary);
    font-size: 11px;
}


/* =====================================================
   SHORTCUTS
   ===================================================== */

.wallet-shortcuts {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}

.wallet-shortcut {
    display: flex;
    min-width: 0;
    align-items: center;
    flex-direction: column;
    justify-content: center;
    gap: 6px;
    min-height: 100px;
    padding: 12px 8px;
    border: 1px solid var(--border);
    border-radius: 15px;
    background: var(--bg-card);
    color: var(--text-primary);
    text-align: center;
    text-decoration: none;
    transition: .2s ease;
}

.wallet-shortcut:hover {
    border-color: rgba(34,197,94,.35);
    color: var(--text-primary);
    transform: translateY(-1px);
}

.wallet-shortcut-icon {
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: 11px;
    background: rgba(34,197,94,.09);
    color: var(--primary);
    font-size: 17px;
}

.wallet-shortcut > span:not(.wallet-shortcut-icon) {
    font-size: 12px;
    font-weight: 700;
}

.wallet-shortcut small {
    color: var(--text-secondary);
    font-size: 9px;
}


/* =====================================================
   DETAILS
   ===================================================== */

.wallet-details-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(260px, .8fr);
    gap: 14px;
}

.wallet-activity-panel,
.wallet-info-panel {
    min-width: 0;
}

.wallet-empty-state {
    padding: 42px 18px;
    text-align: center;
}

.wallet-empty-state .empty-icon {
    display: grid;
    width: 46px;
    height: 46px;
    margin: 0 auto 12px;
    place-items: center;
    border-radius: 13px;
    background: rgba(148,163,184,.08);
    color: var(--text-secondary);
    font-size: 19px;
}


/* =====================================================
   RECENT TRANSACTIONS
   ===================================================== */

.wallet-recent-list {
    width: 100%;
}

.wallet-recent-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 13px 16px;
    border-top: 1px solid rgba(255,255,255,.05);
}

.wallet-recent-icon {
    display: grid;
    width: 37px;
    height: 37px;
    flex: 0 0 37px;
    place-items: center;
    border-radius: 11px;
    background: rgba(56,189,248,.08);
    color: var(--accent);
}

.wallet-recent-content {
    min-width: 0;
    flex: 1;
}

.wallet-recent-content strong {
    display: block;
    color: var(--text-primary);
    font-size: 12px;
}

.wallet-recent-content span {
    display: block;
    overflow: hidden;
    margin-top: 2px;
    color: var(--text-secondary);
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.wallet-recent-content small {
    display: block;
    margin-top: 3px;
    color: var(--text-secondary);
    font-size: 9px;
}

.wallet-recent-amount {
    flex: 0 0 auto;
    text-align: right;
}

.wallet-recent-amount strong {
    display: block;
    font-size: 11px;
    white-space: nowrap;
}

.wallet-amount-positive {
    color: var(--primary);
}

.wallet-amount-negative {
    color: #f87171;
}

.wallet-mini-status {
    display: inline-block;
    margin-top: 3px;
    font-size: 8px;
    font-weight: 700;
}

.wallet-status-completed {
    color: var(--primary);
}

.wallet-status-pending {
    color: #facc15;
}

.wallet-status-failed,
.wallet-status-reversed {
    color: #f87171;
}


/* =====================================================
   WALLET DETAILS
   ===================================================== */

.wallet-info-list {
    display: flex;
    flex-direction: column;
}

.wallet-info-list > div {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 13px 16px;
    border-top: 1px solid rgba(255,255,255,.05);
}

.wallet-info-list .muted {
    color: var(--text-secondary);
    font-size: 10px;
}

.wallet-info-list strong,
.wallet-info-list > div > span:not(.muted) {
    color: var(--text-primary);
    font-size: 11px;
    text-align: right;
}


/* =====================================================
   MOBILE
   ===================================================== */

@media (max-width: 760px) {

    .wallet-balance-value {
        font-size: 30px;
    }

    .wallet-shortcuts {
        grid-template-columns: repeat(2, 1fr);
    }

    .wallet-details-grid {
        grid-template-columns: 1fr;
    }

}


/* =====================================================
   SMALL PHONES
   ===================================================== */

@media (max-width: 380px) {

    .wallet-balance-value {
        font-size: 27px;
    }

    .wallet-balance-icon {
        width: 38px;
        height: 38px;
        flex-basis: 38px;
    }

    .wallet-recent-item {
        padding: 12px;
    }

    .wallet-recent-content span {
        max-width: 130px;
    }

}

</style>


<?php
require __DIR__ . '/../includes/app_footer.php';
?>