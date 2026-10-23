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

$pageTitle = 'Transactions';
$pageDescription = 'Your wallet transaction history.';
$activeNav = 'transactions';

$db = database();

/*
|--------------------------------------------------------------------------
| Wallet currency
|--------------------------------------------------------------------------
*/

$walletStmt = $db->prepare("
    SELECT currency
    FROM wallets
    WHERE user_id = ?
    ORDER BY id ASC
    LIMIT 1
");

$walletStmt->execute([$userId]);

$wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

$currency = 'ETB';

if ($wallet && !empty($wallet['currency'])) {
    $currency = (string) $wallet['currency'];
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

/*
|--------------------------------------------------------------------------
| Transactions
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        type,
        amount,
        balance_before,
        balance_after,
        reference,
        description,
        status,
        created_at
    FROM transactions
    WHERE user_id = :user_id
";

$params = [
    ':user_id' => $userId
];

if ($search !== '') {
    $sql .= "
        AND (
            type LIKE :search
            OR description LIKE :search
            OR reference LIKE :search
        )
    ";

    $params[':search'] = '%' . $search . '%';
}

$allowedStatuses = [
    'pending',
    'completed',
    'failed',
    'reversed'
];

if (in_array($status, $allowedStatuses, true)) {
    $sql .= " AND status = :status ";
    $params[':status'] = $status;
}

$sql .= "
    ORDER BY created_at DESC, id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);

$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function transactionTypeLabel(string $type): string
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

function transactionTypeIcon(string $type): string
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

function transactionAmountPrefix(string $type): string
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

function transactionAmountClass(string $type): string
{
    return match ($type) {
        'DEPOSIT',
        'TRANSFER_IN',
        'WIN',
        'REFUND' => 'amount-positive',

        'WITHDRAW',
        'TRANSFER_OUT',
        'BET' => 'amount-negative',

        default => ''
    };
}

require __DIR__ . '/../includes/app_header.php';
?>

<div class="transactions-page">

    <section class="transactions-heading">

        <div>

            <span class="eyebrow">
                Wallet
            </span>

            <h1>
                Transaction history
            </h1>

            <p>
                A complete record of your wallet activity.
            </p>

        </div>

        <a
            class="btn btn-secondary"
            href="index.php"
        >
            <i class="bi bi-wallet2" aria-hidden="true"></i>
            Wallet
        </a>

    </section>


    <section class="currency-card">

        <div class="currency-icon">

            <i
                class="bi bi-cash-stack"
                aria-hidden="true"
            ></i>

        </div>

        <div>

            <span>
                Wallet currency
            </span>

            <strong>
                <?= html_escape($currency) ?>
            </strong>

        </div>

    </section>


    <section class="data-panel transaction-panel">

        <div class="transaction-toolbar">

            <form
                method="GET"
                action=""
                class="transaction-filter-form"
            >

                <div class="search-wrapper">

                    <i
                        class="bi bi-search"
                        aria-hidden="true"
                    ></i>

                    <input
                        type="search"
                        name="search"
                        value="<?= html_escape($search) ?>"
                        placeholder="Search transactions..."
                        aria-label="Search transactions"
                    >

                </div>


                <select
                    name="status"
                    class="status-select"
                    aria-label="Filter transactions by status"
                >

                    <option value="">
                        All statuses
                    </option>

                    <option
                        value="pending"
                        <?= $status === 'pending' ? 'selected' : '' ?>
                    >
                        Pending
                    </option>

                    <option
                        value="completed"
                        <?= $status === 'completed' ? 'selected' : '' ?>
                    >
                        Completed
                    </option>

                    <option
                        value="failed"
                        <?= $status === 'failed' ? 'selected' : '' ?>
                    >
                        Failed
                    </option>

                    <option
                        value="reversed"
                        <?= $status === 'reversed' ? 'selected' : '' ?>
                    >
                        Reversed
                    </option>

                </select>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    Filter
                </button>


                <?php if ($search !== '' || $status !== ''): ?>

                    <a
                        href="transactions.php"
                        class="btn btn-secondary"
                    >
                        Clear
                    </a>

                <?php endif; ?>

            </form>

        </div>


        <div class="table-responsive">

            <table class="table align-middle transactions-table">

                <thead>

                    <tr>

                        <th>
                            Type
                        </th>

                        <th>
                            Description
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Balance
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Date
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$transactions): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="table-empty"
                        >

                            <span class="empty-icon">

                                <i
                                    class="bi bi-receipt"
                                    aria-hidden="true"
                                ></i>

                            </span>

                            <strong>
                                No transactions found
                            </strong>

                            <span>
                                Your wallet activity will appear here.
                            </span>

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($transactions as $transaction): ?>

                        <?php

                        $type = (string) $transaction['type'];

                        $amount = (float) $transaction['amount'];

                        $balanceAfter = (float) $transaction['balance_after'];

                        $prefix = transactionAmountPrefix($type);

                        $amountClass = transactionAmountClass($type);

                        $statusClass = match (
                            strtolower((string) $transaction['status'])
                        ) {
                            'completed' => 'status-completed',
                            'pending' => 'status-pending',
                            'failed' => 'status-failed',
                            'reversed' => 'status-reversed',
                            default => 'status-pending'
                        };

                        ?>

                        <tr>

                            <td>

                                <div class="transaction-type">

                                    <span class="transaction-icon">

                                        <i
                                            class="bi <?= html_escape(transactionTypeIcon($type)) ?>"
                                            aria-hidden="true"
                                        ></i>

                                    </span>

                                    <strong>

                                        <?= html_escape(
                                            transactionTypeLabel($type)
                                        ) ?>

                                    </strong>

                                </div>

                            </td>


                            <td>

                                <div class="transaction-description">

                                    <?= html_escape(
                                        (string) (
                                            $transaction['description']
                                            ?: 'Wallet transaction'
                                        )
                                    ) ?>

                                    <small>

                                        Ref:
                                        <?= html_escape(
                                            (string) $transaction['reference']
                                        ) ?>

                                    </small>

                                </div>

                            </td>


                            <td>

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

                            </td>


                            <td>

                                <span class="balance-after">

                                    <?= number_format(
                                        $balanceAfter,
                                        2
                                    ) ?>

                                    <?= html_escape($currency) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="transaction-status <?= html_escape($statusClass) ?>"
                                >

                                    <?= html_escape(
                                        ucfirst(
                                            strtolower(
                                                (string) $transaction['status']
                                            )
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <div class="transaction-date">

                                    <?= html_escape(
                                        date(
                                            'M d, Y',
                                            strtotime(
                                                (string) $transaction['created_at']
                                            )
                                        )
                                    ) ?>

                                    <small>

                                        <?= html_escape(
                                            date(
                                                'H:i',
                                                strtotime(
                                                    (string) $transaction['created_at']
                                                )
                                            )
                                        ) ?>

                                    </small>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>


<style>

.transactions-page {
    width: 100%;
}

.transactions-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.transactions-heading h1 {
    margin: 0;
    color: var(--text-primary);
    font-size: 25px;
    font-weight: 700;
}

.transactions-heading p {
    margin: 6px 0 0;
    color: var(--text-secondary);
    font-size: 13px;
}

.currency-card {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding: 13px 15px;
    border: 1px solid rgba(34,197,94,.15);
    border-radius: 14px;
    background: rgba(34,197,94,.05);
}

.currency-icon {
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    border-radius: 11px;
    background: rgba(34,197,94,.10);
    color: var(--primary);
}

.currency-card span {
    display: block;
    color: var(--text-secondary);
    font-size: 10px;
}

.currency-card strong {
    display: block;
    margin-top: 2px;
    color: var(--text-primary);
    font-size: 14px;
}

.transaction-toolbar {
    padding: 16px;
    border-bottom: 1px solid var(--border);
}

.transaction-filter-form {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
}

.search-wrapper {
    position: relative;
    flex: 1;
    min-width: 190px;
}

.search-wrapper i {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
}

.search-wrapper input {
    width: 100%;
    min-height: 42px;
    padding: 9px 12px 9px 38px;
    border: 1px solid var(--border);
    border-radius: 10px;
    outline: none;
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.search-wrapper input:focus {
    border-color: var(--primary);
}

.status-select {
    min-height: 42px;
    min-width: 150px;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 8px 12px;
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.transactions-table {
    margin: 0;
}

.transactions-table th {
    color: var(--text-secondary);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}

.transactions-table td {
    color: var(--text-primary);
    font-size: 12px;
    vertical-align: middle;
}

.transaction-type {
    display: flex;
    align-items: center;
    gap: 9px;
    white-space: nowrap;
}

.transaction-icon {
    display: grid;
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    place-items: center;
    border-radius: 10px;
    background: rgba(56,189,248,.08);
    color: var(--accent);
}

.transaction-description {
    min-width: 150px;
    max-width: 260px;
}

.transaction-description small {
    display: block;
    margin-top: 3px;
    color: var(--text-secondary);
    font-size: 9px;
    word-break: break-all;
}

.amount-positive {
    color: var(--primary);
}

.amount-negative {
    color: #f87171;
}

.balance-after {
    color: var(--text-secondary);
    white-space: nowrap;
}

.transaction-status {
    display: inline-flex;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 700;
}

.status-completed {
    color: #4ade80;
    background: rgba(34,197,94,.10);
}

.status-pending {
    color: #facc15;
    background: rgba(250,204,21,.10);
}

.status-failed,
.status-reversed {
    color: #f87171;
    background: rgba(248,113,113,.10);
}

.transaction-date {
    white-space: nowrap;
}

.transaction-date small {
    display: block;
    margin-top: 2px;
    color: var(--text-secondary);
    font-size: 9px;
}

.table-empty {
    padding: 55px 20px !important;
    text-align: center;
    color: var(--text-secondary);
}

.table-empty .empty-icon {
    display: grid;
    width: 48px;
    height: 48px;
    margin: 0 auto 12px;
    place-items: center;
    border-radius: 14px;
    background: rgba(148,163,184,.08);
    font-size: 20px;
}

.table-empty strong,
.table-empty span:last-child {
    display: block;
}

.table-empty strong {
    color: var(--text-primary);
    font-size: 14px;
}

.table-empty span:last-child {
    margin-top: 4px;
    font-size: 11px;
}

@media (max-width: 760px) {

    .transactions-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .transactions-heading h1 {
        font-size: 22px;
    }

    .transaction-filter-form {
        align-items: stretch;
        flex-direction: column;
    }

    .search-wrapper,
    .status-select,
    .transaction-filter-form .btn {
        width: 100%;
        min-width: 100%;
    }

    .transactions-table {
        min-width: 850px;
    }

}

</style>


<?php
require __DIR__ . '/../includes/app_footer.php';
?>