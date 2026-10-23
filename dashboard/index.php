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
$username = (string) ($_SESSION['username'] ?? 'Member');
$userId = (int) $_SESSION['user_id'];

try {
    $profileCheck = database()->prepare('SELECT id FROM dating_profiles WHERE user_id = ? LIMIT 1');
    $profileCheck->execute([$userId]);
    if ($profileCheck->fetchColumn() === false) {
        header('Location: ../dating/onboarding.php');
        exit;
    }
} catch (PDOException $exception) {
    error_log(sprintf('Selam dashboard onboarding check failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
    http_response_code(503);
    exit('Dating profile setup is not ready. Apply database/migrations/20261023_dating_profiles.sql and try again.');
}
$currency = 'ETB';

$db = database();

$walletStmt = $db->prepare("
    SELECT balance, locked_balance
    FROM wallets
    WHERE user_id = ?
    ORDER BY id ASC
    LIMIT 1
");
$walletStmt->execute([$userId]);
$wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

$balance = $wallet
    ? max(0, (float) $wallet['balance'] - (float) $wallet['locked_balance'])
    : 0.00;

$activityStmt = $db->prepare("
    SELECT type, amount, description, status, created_at
    FROM transactions
    WHERE user_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 5
");
$activityStmt->execute([$userId]);
$recentActivity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

$hour = (int) date('G');
if ($hour >= 5 && $hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour >= 12 && $hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$pageTitle = 'Your Selam';
$pageDescription = 'Discover mutual matches and continue your conversations.';
$activeNav = 'home';

require __DIR__ . '/../includes/app_header.php';

$activityLabels = [
    'DEPOSIT' => 'Deposit',
    'WITHDRAW' => 'Withdrawal',
    'TRANSFER_IN' => 'Transfer received',
    'TRANSFER_OUT' => 'Transfer sent',
    'ADMIN_ADJUSTMENT' => 'Balance adjustment',
    'BET' => 'Purchase',
    'WIN' => 'Profit',
    'REFUND' => 'Refund',
];

$activityIcons = [
    'DEPOSIT' => 'bi-arrow-down-left',
    'WITHDRAW' => 'bi-arrow-up-right',
    'TRANSFER_IN' => 'bi-arrow-down-left',
    'TRANSFER_OUT' => 'bi-arrow-up-right',
    'ADMIN_ADJUSTMENT' => 'bi-sliders',
    'BET' => 'bi-box-seam',
    'WIN' => 'bi-graph-up-arrow',
    'REFUND' => 'bi-arrow-counterclockwise',
];
?>

<div class="dashboard-page">
    <section class="dashboard-welcome" aria-labelledby="welcome-title">
        <div>
            <span class="eyebrow">Your account</span>
            <h2 id="welcome-title"><?= html_escape($greeting) ?>, <?= html_escape($username) ?></h2>
            <p>Here’s what’s happening with your account today.</p>
        </div>
        <div class="dashboard-date">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <time datetime="<?= html_escape(date('Y-m-d')) ?>"><?= html_escape(date('F j, Y')) ?></time>
        </div>
    </section>

    <section class="dashboard-connection-card" aria-label="Discover people on Selam">
        <span class="dashboard-connection-icon"><i class="bi bi-heart-fill" aria-hidden="true"></i></span>
        <div>
            <span class="eyebrow">MEET WITH MUTUAL INTEREST</span>
            <h2>Find people who are looking for you, too.</h2>
            <p>Browse reciprocal matches, send a free hello, and continue conversations with a Selam membership.</p>
        </div>
        <span class="status-badge success">Discover</span>
        <a href="../dating/discover.php" aria-label="Browse mutual matches"><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </section>

    <div class="dashboard-overview">
        <section class="balance-card dashboard-balance" aria-labelledby="balance-title">
            <div class="balance-label" id="balance-title">
                <i class="bi bi-wallet2" aria-hidden="true"></i>
                Available balance
            </div>
            <div class="balance-value">
                <?= number_format($balance, 2) ?>
                <span class="balance-currency"><?= html_escape($currency) ?></span>
            </div>
            <p class="small-note">Your available wallet balance.</p>
            <div class="balance-actions">
                <a href="../wallet/index.php" class="btn btn-primary">
                    <i class="bi bi-wallet2" aria-hidden="true"></i>
                    Open wallet
                </a>
                <a href="../wallet/transactions.php" class="btn btn-secondary">
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    Transactions
                </a>
            </div>
        </section>

        <section class="dashboard-account-card" aria-labelledby="account-title">
            <div class="dashboard-account-top">
                <span class="dashboard-account-icon" aria-hidden="true">
                    <i class="bi bi-person-check"></i>
                </span>
                <span class="status-badge success">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    Signed in
                </span>
            </div>
            <div>
                <span class="dashboard-account-label">Account</span>
                <h2 id="account-title"><?= html_escape($username) ?></h2>
                <p>Your account overview and personal details are ready.</p>
            </div>
            <a href="../profile.php" class="dashboard-profile-link">
                Manage profile
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </section>
    </div>

    <section class="dashboard-section" aria-labelledby="actions-title">
        <div class="section-heading">
            <div>
                <h2 id="actions-title">Your account tools</h2>
                <p class="dashboard-section-note">Your existing Selam account features are here when you need them.</p>
            </div>
        </div>

        <div class="quick-grid dashboard-actions">
            <a href="../wallet/index.php" class="action-card">
                <span class="metric-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
                <span class="dashboard-action-copy">
                    <span class="action-title">Wallet</span>
                    <span class="action-caption">Balance and details</span>
                </span>
            </a>
            <a href="../wallet/transactions.php" class="action-card">
                <span class="metric-icon"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></span>
                <span class="dashboard-action-copy">
                    <span class="action-title">Transactions</span>
                    <span class="action-caption">Review your activity</span>
                </span>
            </a>
            <a href="../wallet/transfer.php" class="action-card">
                <span class="metric-icon"><i class="bi bi-send" aria-hidden="true"></i></span>
                <span class="dashboard-action-copy">
                    <span class="action-title">Transfer</span>
                    <span class="action-caption">Send by user ID</span>
                </span>
            </a>
            <a href="../products.php" class="action-card">
                <span class="metric-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
                <span class="dashboard-action-copy">
                    <span class="action-title">Products</span>
                    <span class="action-caption">Browse packages</span>
                </span>
            </a>
            <a href="../profile.php" class="action-card">
                <span class="metric-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                <span class="dashboard-action-copy">
                    <span class="action-title">Profile</span>
                    <span class="action-caption">Account settings</span>
                </span>
            </a>
        </div>
    </section>

    <section class="data-panel dashboard-activity" aria-labelledby="activity-title">
        <div class="data-panel-header">
            <div>
                <h2 id="activity-title">Recent activity</h2>
                <p>Your latest wallet transactions.</p>
            </div>
            <a href="../wallet/transactions.php">
                View all
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <?php if ($recentActivity): ?>
            <ul class="dashboard-activity-list">
                <?php foreach ($recentActivity as $activity): ?>
                    <?php
                    $type = (string) $activity['type'];
                    $amount = (float) $activity['amount'];
                    $status = (string) $activity['status'];
                    $statusClass = match ($status) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed', 'reversed' => 'danger',
                        default => '',
                    };
                    ?>
                    <li class="dashboard-activity-item">
                        <span class="dashboard-activity-icon">
                            <i class="bi <?= html_escape($activityIcons[$type] ?? 'bi-arrow-left-right') ?>" aria-hidden="true"></i>
                        </span>
                        <span class="dashboard-activity-details">
                            <strong><?= html_escape($activityLabels[$type] ?? ucwords(strtolower(str_replace('_', ' ', $type)))) ?></strong>
                            <span><?= html_escape((string) ($activity['description'] ?: date('M j, Y, g:i A', strtotime((string) $activity['created_at'])))) ?></span>
                        </span>
                        <span class="dashboard-activity-result">
                            <strong class="<?= $amount >= 0 ? 'amount-positive' : 'amount-negative' ?>">
                                <?= $amount > 0 ? '+' : '' ?><?= number_format($amount, 2) ?> <?= html_escape($currency) ?>
                            </strong>
                            <span class="status-badge <?= html_escape($statusClass) ?>"><?= html_escape(ucfirst($status)) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="dashboard-empty">
                <span class="empty-icon"><i class="bi bi-receipt" aria-hidden="true"></i></span>
                <strong>No activity yet</strong>
                <p>When you make a wallet transaction, it will appear here.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<style>
.dashboard-page {
    display: grid;
    gap: 24px;
    width: 100%;
}

.dashboard-welcome {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    padding: 2px 2px 0;
}

.dashboard-welcome h2 {
    margin: 4px 0 0;
    color: var(--text-primary);
    font-size: clamp(21px, 3vw, 27px);
    font-weight: 700;
    letter-spacing: -.025em;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

.dashboard-welcome p,
.dashboard-account-card p,
.dashboard-section-note,
.data-panel-header p {
    margin: 6px 0 0;
    color: var(--text-secondary);
    font-size: 13px;
}

.dashboard-connection-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    border: 1px solid #efd8e0;
    border-radius: 17px;
    background: linear-gradient(110deg, #fff0f3, #fff 78%);
}

.dashboard-connection-icon {
    display: grid;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    place-items: center;
    border-radius: 14px;
    background: #fff;
    color: #c45170;
    box-shadow: 0 4px 12px rgba(116, 49, 70, .08);
}

.dashboard-connection-card > div {
    min-width: 0;
    flex: 1;
}

.dashboard-connection-card .eyebrow {
    margin: 0;
    font-size: 9px;
}

.dashboard-connection-card h2 {
    margin: 3px 0 0;
    color: #392832;
    font-size: 14px;
    font-weight: 700;
}

.dashboard-connection-card p {
    margin: 3px 0 0;
    color: #7d7078;
    font-size: 11px;
}

.dashboard-connection-card > a {
    display: grid;
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    place-items: center;
    border-radius: 11px;
    background: #fff;
    color: #b64e6d;
    text-decoration: none;
}

.dashboard-date {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 auto;
    padding: 9px 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--bg-card);
    color: var(--text-secondary);
    font-size: 12px;
}

.dashboard-overview {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(250px, .85fr);
    gap: 16px;
}

.dashboard-balance {
    min-height: 0;
    border-radius: 18px;
}

.dashboard-balance .balance-value {
    font-size: clamp(30px, 5vw, 42px);
}

.dashboard-balance .small-note {
    margin: 0;
}

.dashboard-balance .balance-actions {
    margin-top: 20px;
}

.dashboard-account-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    min-width: 0;
    margin: 0;
    padding: 22px;
    border: 1px solid var(--border);
    border-radius: 18px;
    background:
        radial-gradient(circle at 100% 0, rgba(196, 81, 112, .09), transparent 40%),
        var(--bg-card);
}

.dashboard-account-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
}

.dashboard-account-icon {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border-radius: 13px;
    background: rgba(196, 81, 112, .12);
    color: var(--primary);
    font-size: 19px;
}

.dashboard-account-label {
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.dashboard-account-card h2 {
    margin: 4px 0 0;
    font-size: 18px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.dashboard-account-card p {
    line-height: 1.5;
}

.dashboard-profile-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--primary);
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.dashboard-profile-link:hover {
    color: var(--text-primary);
}

.dashboard-section {
    margin: 0;
}

.dashboard-section .section-heading {
    margin-bottom: 14px;
}

.dashboard-section .section-heading h2 {
    font-size: 19px;
    font-weight: 700;
}

.dashboard-section-note {
    font-size: 12px;
}

.dashboard-actions {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.dashboard-actions .action-card {
    min-height: 82px;
    gap: 12px;
    padding: 14px;
}

.dashboard-action-copy {
    display: grid;
    min-width: 0;
    gap: 3px;
}

.dashboard-actions .action-caption {
    overflow-wrap: anywhere;
}

.dashboard-activity {
    margin: 0;
    border-radius: 16px;
}

.dashboard-activity .data-panel-header {
    min-height: 70px;
}

.dashboard-activity .data-panel-header p {
    font-size: 12px;
}

.dashboard-activity-list {
    margin: 0;
    padding: 0 18px;
    list-style: none;
}

.dashboard-activity-item {
    display: flex;
    align-items: center;
    gap: 13px;
    min-width: 0;
    padding: 14px 0;
}

.dashboard-activity-item + .dashboard-activity-item {
    border-top: 1px solid var(--border);
}

.dashboard-activity-icon {
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    place-items: center;
    border-radius: 12px;
    background: var(--bg-elevated);
    color: var(--accent);
}

.dashboard-activity-details,
.dashboard-activity-result {
    display: grid;
    min-width: 0;
    gap: 3px;
}

.dashboard-activity-details {
    flex: 1;
}

.dashboard-activity-details strong,
.dashboard-activity-result strong {
    font-size: 13px;
    font-weight: 600;
}

.dashboard-activity-details > span {
    overflow: hidden;
    color: var(--text-secondary);
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dashboard-activity-result {
    justify-items: end;
    flex: 0 0 auto;
    text-align: right;
}

.dashboard-activity-result .status-badge {
    font-size: 9px;
}

.dashboard-empty {
    padding: 32px 18px;
    text-align: center;
}

.dashboard-empty .empty-icon {
    margin: 0 auto 10px;
}

.dashboard-empty strong {
    display: block;
    margin-bottom: 4px;
    font-size: 14px;
}

.dashboard-empty p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 12px;
}

@media (max-width: 760px) {
    .dashboard-page {
        gap: 20px;
    }

    .dashboard-overview {
        grid-template-columns: 1fr;
    }

    .dashboard-connection-card {
        flex-wrap: wrap;
        gap: 10px;
        padding: 14px;
    }

    .dashboard-connection-card > .status-badge {
        margin-left: 52px;
    }

    .dashboard-account-card {
        gap: 14px;
        padding: 18px;
    }

    .dashboard-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .dashboard-activity-list {
        padding-right: 14px;
        padding-left: 14px;
    }
}

@media (max-width: 480px) {
    .dashboard-welcome {
        align-items: flex-start;
        flex-direction: column;
        gap: 10px;
    }

    .dashboard-date {
        padding: 7px 10px;
    }

    .dashboard-balance {
        padding: 20px;
    }

    .dashboard-balance .balance-actions .btn {
        flex: 1 1 auto;
    }

    .dashboard-actions .action-card {
        min-height: 72px;
        padding: 10px;
    }

    .dashboard-activity-item {
        gap: 9px;
    }

    .dashboard-activity-icon {
        width: 34px;
        height: 34px;
        flex-basis: 34px;
    }

    .dashboard-activity-details strong,
    .dashboard-activity-result strong {
        font-size: 11px;
    }
}
</style>

<?php require __DIR__ . '/../includes/app_footer.php'; ?>
