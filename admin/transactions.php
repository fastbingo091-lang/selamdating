<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminTitle  = 'Transactions';
$adminActive = 'transactions';

$db = database();

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

/*
|--------------------------------------------------------------------------
| Allowed transaction statuses
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'pending',
    'completed',
    'failed',
    'cancelled',
    'reversed',
];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}

/*
|--------------------------------------------------------------------------
| Build query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        t.id,
        t.user_id,
        u.username,
        u.phone,
        t.type,
        t.amount,
        t.description,
        t.status,
        t.created_at
    FROM transactions t
    LEFT JOIN users u
        ON u.id = t.user_id
";

$where = [];
$params = [];

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "
        (
            CAST(t.id AS CHAR) LIKE :search
            OR CAST(t.user_id AS CHAR) LIKE :search
            OR u.username LIKE :search
            OR u.phone LIKE :search
            OR t.type LIKE :search
            OR t.description LIKE :search
        )
    ";

    $params['search'] = '%' . $search . '%';
}

/*
|--------------------------------------------------------------------------
| Status filter
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $where[] = "t.status = :status";

    $params['status'] = $status;
}

/*
|--------------------------------------------------------------------------
| WHERE
|--------------------------------------------------------------------------
*/

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY t.id DESC
    LIMIT 200
";

/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare($sql);
$stmt->execute($params);

$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalTransactions = (int) $db
    ->query("SELECT COUNT(*) FROM transactions")
    ->fetchColumn();

$totalVolume = (float) $db
    ->query("
        SELECT COALESCE(SUM(amount), 0)
        FROM transactions
    ")
    ->fetchColumn();

$pendingTransactions = (int) $db
    ->query("
        SELECT COUNT(*)
        FROM transactions
        WHERE status = 'pending'
    ")
    ->fetchColumn();

require __DIR__ . '/../includes/admin_header.php';

?>

<!-- =========================================================
     TRANSACTION SUMMARY
     ========================================================= -->

<section class="metric-grid mb-4">

    <article class="metric-card">

        <div class="metric-top">

            <span>Total transactions</span>

            <span class="metric-icon">
                <i
                    class="bi bi-arrow-left-right"
                    aria-hidden="true"
                ></i>
            </span>

        </div>

        <div class="metric-value">
            <?= number_format($totalTransactions) ?>
        </div>

        <div class="metric-foot">
            Recorded ledger entries
        </div>

    </article>


    <article class="metric-card">

        <div class="metric-top">

            <span>Transaction volume</span>

            <span class="metric-icon">
                <i
                    class="bi bi-cash-stack"
                    aria-hidden="true"
                ></i>
            </span>

        </div>

        <div class="metric-value">
            <?= number_format($totalVolume, 2) ?>
        </div>

        <div class="metric-foot">
            Total recorded amount
        </div>

    </article>


    <article class="metric-card">

        <div class="metric-top">

            <span>Pending</span>

            <span class="metric-icon">
                <i
                    class="bi bi-clock-history"
                    aria-hidden="true"
                ></i>
            </span>

        </div>

        <div class="metric-value">
            <?= number_format($pendingTransactions) ?>
        </div>

        <div class="metric-foot">
            Transactions awaiting processing
        </div>

    </article>

</section>


<!-- =========================================================
     TRANSACTIONS
     ========================================================= -->

<section class="data-panel">

    <div class="data-panel-header">

        <div>

            <h2>
                Ledger transactions
            </h2>

            <p class="muted mb-0">
                View wallet activity recorded in the database.
            </p>

        </div>

    </div>


    <!-- FILTERS -->

    <form
        method="get"
        class="toolbar p-3 pb-0"
    >

        <label
            class="visually-hidden"
            for="admin-transactions-search"
        >
            Search transactions
        </label>

        <input
            class="form-control search-input"
            id="admin-transactions-search"
            name="search"
            type="search"
            placeholder="Search transactions"
            value="<?= html_escape($search) ?>"
        >


        <select
            class="form-select"
            name="status"
            style="max-width:180px"
            aria-label="Filter transaction status"
        >

            <option value="">
                All statuses
            </option>

            <?php foreach ($allowedStatuses as $transactionStatus): ?>

                <option
                    value="<?= html_escape($transactionStatus) ?>"
                    <?= $status === $transactionStatus
                        ? 'selected'
                        : '' ?>
                >

                    <?= html_escape(
                        ucfirst($transactionStatus)
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>


        <button
            type="submit"
            class="btn btn-primary"
        >

            <i
                class="bi bi-search"
                aria-hidden="true"
            ></i>

            Search

        </button>


        <?php if ($search !== '' || $status !== ''): ?>

            <a
                href="transactions.php"
                class="btn btn-outline-secondary"
            >
                Clear
            </a>

        <?php endif; ?>

    </form>


    <!-- TABLE -->

    <div class="table-responsive mt-3">

        <table class="table align-middle mb-0">

            <thead>

                <tr>

                    <th>
                        Reference
                    </th>

                    <th>
                        User
                    </th>

                    <th>
                        Type
                    </th>

                    <th>
                        Amount
                    </th>

                    <th>
                        Description
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
                        colspan="7"
                        class="table-empty"
                    >

                        <span class="empty-icon">

                            <i
                                class="bi bi-arrow-left-right"
                                aria-hidden="true"
                            ></i>

                        </span>

                        <strong class="d-block mb-1">
                            No transactions found
                        </strong>

                        <span class="small-note">
                            There are no ledger transactions matching your search.
                        </span>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($transactions as $transaction): ?>

                    <?php

                    $transactionStatus =
                        (string) $transaction['status'];

                    $statusClass = match ($transactionStatus) {

                        'completed' =>
                            'success',

                        'pending' =>
                            'warning',

                        'failed',
                        'cancelled',
                        'reversed' =>
                            'danger',

                        default =>
                            'secondary',

                    };

                    ?>

                    <tr>

                        <!-- Reference -->

                        <td>

                            <strong>
                                #<?= (int) $transaction['id'] ?>
                            </strong>

                        </td>


                        <!-- User -->

                        <td>

                            <?php if (
                                !empty($transaction['username'])
                            ): ?>

                                <strong>
                                    <?= html_escape(
                                        (string) $transaction['username']
                                    ) ?>
                                </strong>

                            <?php else: ?>

                                User #<?= (int) $transaction['user_id'] ?>

                            <?php endif; ?>

                            <?php if (
                                !empty($transaction['phone'])
                            ): ?>

                                <div class="small-note">
                                    <?= html_escape(
                                        (string) $transaction['phone']
                                    ) ?>
                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- Type -->

                        <td>

                            <?= html_escape(
                                (string) $transaction['type']
                            ) ?>

                        </td>


                        <!-- Amount -->

                        <td>

                            <strong>
                                <?= number_format(
                                    (float) $transaction['amount'],
                                    2
                                ) ?>
                            </strong>

                        </td>


                        <!-- Description -->

                        <td>

                            <span class="small-note">

                                <?= html_escape(
                                    (string) (
                                        $transaction['description']
                                        ?? ''
                                    )
                                ) ?>

                            </span>

                        </td>


                        <!-- Status -->

                        <td>

                            <span
                                class="status-badge <?= $statusClass ?>"
                            >

                                <?= html_escape(
                                    ucfirst(
                                        $transactionStatus
                                    )
                                ) ?>

                            </span>

                        </td>


                        <!-- Date -->

                        <td>

                            <?= html_escape(
                                (string) $transaction['created_at']
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<?php require __DIR__ . '/../includes/admin_footer.php'; ?>