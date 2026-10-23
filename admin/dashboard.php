<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_auth.php';

$adminId = require_active_admin();

$adminTitle  = 'Dashboard';
$adminActive = 'dashboard';

$db = database();
$presence = $db->prepare("
    INSERT INTO admin_presence (admin_id, last_seen_at)
    VALUES (?, NOW())
    ON DUPLICATE KEY UPDATE last_seen_at = NOW()
");
$presence->execute([$adminId]);

/*
|--------------------------------------------------------------------------
| Dashboard statistics
|--------------------------------------------------------------------------
*/

$totalUsers = (int) $db
    ->query("SELECT COUNT(*) FROM users")
    ->fetchColumn();

$activeUsers = (int) $db
    ->query("SELECT COUNT(*) FROM users WHERE status = 'active'")
    ->fetchColumn();

$onlineAgents = $db->query("
    SELECT a.id, a.username, p.last_seen_at
    FROM admin_presence p
    INNER JOIN admins a ON a.id = p.admin_id AND a.status = 'active'
    WHERE p.last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY p.last_seen_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$totalWalletBalance = (float) $db
    ->query("SELECT COALESCE(SUM(balance), 0) FROM wallets WHERE currency = 'ETB'")
    ->fetchColumn();

$totalTransactions = (int) $db
    ->query("SELECT COUNT(*) FROM transactions")
    ->fetchColumn();

/*
|--------------------------------------------------------------------------
| Recent transactions
|--------------------------------------------------------------------------
*/

$recentTransactions = [];

try {
    $stmt = $db->query("
        SELECT
            id,
            user_id,
            type,
            amount,
            description,
            status,
            created_at
        FROM transactions
        ORDER BY id DESC
        LIMIT 8
    ");

    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Admin dashboard transaction query failed: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Load admin layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../includes/admin_header.php';
?>

<section class="metric-grid mb-4">

    <!-- Total users -->
    <article class="metric-card">

        <div class="metric-top">
            <span>Total users</span>

            <span class="metric-icon">
                <i class="bi bi-people" aria-hidden="true"></i>
            </span>
        </div>

        <div class="metric-value">
            <?= number_format($totalUsers) ?>
        </div>

        <div class="metric-foot">
            Registered accounts
        </div>

    </article>


    <!-- Active users -->
    <article class="metric-card">

        <div class="metric-top">
            <span>Active users</span>

            <span class="metric-icon">
                <i class="bi bi-person-check" aria-hidden="true"></i>
            </span>
        </div>

        <div class="metric-value">
            <?= number_format($activeUsers) ?>
        </div>

        <div class="metric-foot">
            Enabled accounts
        </div>

    </article>


    <!-- Wallet balance -->
    <article class="metric-card">

        <div class="metric-top">
            <span>Total wallet balance</span>

            <span class="metric-icon">
                <i class="bi bi-wallet2" aria-hidden="true"></i>
            </span>
        </div>

        <div class="metric-value">
            <?= number_format($totalWalletBalance, 2) ?>
        </div>

        <div class="metric-foot">
            Combined wallet balances
        </div>

    </article>


    <!-- Transactions -->
    <article class="metric-card">

        <div class="metric-top">
            <span>Transactions</span>

            <span class="metric-icon">
                <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
            </span>
        </div>

        <div class="metric-value">
            <?= number_format($totalTransactions) ?>
        </div>

        <div class="metric-foot">
            Recorded transactions
        </div>

    </article>

</section>

<section class="data-panel mb-4">
    <div class="data-panel-header">
        <div>
            <h2>Online agents</h2>
            <p class="muted mb-0">Admins active in the operations panel during the last five minutes.</p>
        </div>
        <span class="status-badge <?= $onlineAgents !== [] ? 'success' : 'warning' ?>"><?= number_format(count($onlineAgents)) ?> online</span>
    </div>
    <?php if ($onlineAgents === []): ?>
        <div class="p-4 muted">No agents are currently online. Open the admin panel to appear here.</div>
    <?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Agent</th><th>Presence</th><th>Last activity</th></tr></thead>
            <tbody><?php foreach ($onlineAgents as $agent): ?>
                <tr>
                    <td><i class="bi bi-circle-fill text-success me-2" aria-hidden="true"></i><?= html_escape((string) $agent['username']) ?></td>
                    <td><span class="status-badge success">Online</span></td>
                    <td><?= html_escape((string) $agent['last_seen_at']) ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>


<!-- Recent transactions -->

<section class="data-panel">

    <div class="data-panel-header">

        <div>
            <h2>Recent transactions</h2>

            <p class="muted mb-0">
                Latest wallet activity from the database.
            </p>
        </div>

        <a href="transactions.php">
            Transactions
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>

    </div>


    <?php if (!$recentTransactions): ?>

        <div class="p-5 text-center">

            <span class="empty-icon">
                <i class="bi bi-journal-text" aria-hidden="true"></i>
            </span>

            <strong class="d-block mb-1">
                No transactions yet
            </strong>

            <span class="small-note">
                Transactions will appear here when users perform wallet activity.
            </span>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="table align-middle mb-0">

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($recentTransactions as $transaction): ?>

                    <tr>

                        <td>
                            #<?= (int) $transaction['id'] ?>
                        </td>

                        <td>
                            User #<?= (int) $transaction['user_id'] ?>
                        </td>

                        <td>
                            <?= html_escape((string) $transaction['type']) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (float) $transaction['amount'],
                                2
                            ) ?>
                        </td>

                        <td>
                            <?= html_escape((string) $transaction['status']) ?>
                        </td>

                        <td>
                            <?= html_escape((string) $transaction['created_at']) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<?php require __DIR__ . '/../includes/admin_footer.php'; ?>