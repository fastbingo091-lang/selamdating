<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminTitle = 'Wallets';
$adminActive = 'wallets';

$db = database();

/*
|--------------------------------------------------------------------------
| Load wallets
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        w.id,
        w.user_id,
        u.username,
        u.phone,
        w.currency,
        w.balance,
        w.locked_balance
    FROM wallets w
    INNER JOIN users u
        ON u.id = w.user_id
    ORDER BY w.id DESC
");

$wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalWallets = count($wallets);

$totalAvailable = 0.00;
$totalLocked = 0.00;

foreach ($wallets as $wallet) {
    $totalAvailable += (float) $wallet['balance'];
    $totalLocked += (float) $wallet['locked_balance'];
}

require __DIR__ . '/../includes/admin_header.php';

?>

<!-- =========================================================
     WALLET SUMMARY
     ========================================================= -->

<section class="metric-grid mb-4">

    <article class="metric-card">

        <div class="metric-top">

            <span>Total wallets</span>

            <span class="metric-icon">
                <i
                    class="bi bi-wallet2"
                    aria-hidden="true"
                ></i>
            </span>

        </div>

        <div class="metric-value">
            <?= number_format($totalWallets) ?>
        </div>

        <div class="metric-foot">
            User wallets
        </div>

    </article>


    <article class="metric-card">

        <div class="metric-top">

            <span>Available balance</span>

            <span class="metric-icon">
                <i
                    class="bi bi-cash-stack"
                    aria-hidden="true"
                ></i>
            </span>

        </div>

        <div class="metric-value">
            <?= number_format($totalAvailable, 2) ?>
        </div>

        <div class="metric-foot">
            Combined available balance
        </div>

    </article>


    <article class="metric-card">

        <div class="metric-top">

            <span>Locked balance</span>

            <span class="metric-icon">
                <i
                    class="bi bi-lock"
                    aria-hidden="true"
                ></i>
            </span>

        </div>

        <div class="metric-value">
            <?= number_format($totalLocked, 2) ?>
        </div>

        <div class="metric-foot">
            Combined locked balance
        </div>

    </article>

</section>


<!-- =========================================================
     WALLET TABLE
     ========================================================= -->

<section class="data-panel">

    <div class="data-panel-header">

        <div>

            <h2>
                User wallets
            </h2>

            <p class="muted mb-0">
                View balances for registered users.
            </p>

        </div>

    </div>


    <?php if (!$wallets): ?>

        <div class="p-5 text-center">

            <span class="empty-icon">

                <i
                    class="bi bi-wallet2"
                    aria-hidden="true"
                ></i>

            </span>

            <strong class="d-block mb-1">
                No wallets found
            </strong>

            <span class="small-note">
                User wallets will appear here after accounts are created.
            </span>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="table align-middle mb-0">

                <thead>

                    <tr>

                        <th>
                            User
                        </th>

                        <th>
                            Currency
                        </th>

                        <th>
                            Available
                        </th>

                        <th>
                            Locked
                        </th>

                        <th>
                            Total
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($wallets as $wallet): ?>

                    <?php

                    $balance = (float) $wallet['balance'];

                    $locked = (float) $wallet['locked_balance'];

                    $total = $balance + $locked;

                    ?>

                    <tr>

                        <td>

                            <strong>
                                <?= html_escape(
                                    (string) $wallet['username']
                                ) ?>
                            </strong>

                            <?php if (!empty($wallet['phone'])): ?>

                                <div class="small-note">
                                    <?= html_escape(
                                        (string) $wallet['phone']
                                    ) ?>
                                </div>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= html_escape(
                                (string) $wallet['currency']
                            ) ?>

                        </td>


                        <td>

                            <strong>
                                <?= number_format(
                                    $balance,
                                    2
                                ) ?>
                            </strong>

                        </td>


                        <td>

                            <?= number_format(
                                $locked,
                                2
                            ) ?>

                        </td>


                        <td>

                            <?= number_format(
                                $total,
                                2
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<?php require __DIR__ . '/../includes/admin_footer.php'; ?>