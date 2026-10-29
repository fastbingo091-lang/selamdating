<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_auth.php';

require_active_admin();
$db = database();

$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$dateInput = $_GET['date'] ?? '';
$claimDate = is_string($dateInput) ? trim($dateInput) : '';

if ($claimDate !== '') {
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $claimDate);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $claimDate) {
        $claimDate = '';
    }
}

$claims = [];
$stats = null;
$loadError = '';

try {
    $stats = $db->query("
        SELECT
            COUNT(*) AS total_claims,
            COALESCE(SUM(amount), 0) AS total_amount,
            SUM(CASE WHEN claimed_at >= CURDATE() THEN 1 ELSE 0 END) AS claims_today,
            COALESCE(SUM(CASE WHEN claimed_at >= CURDATE() THEN amount ELSE 0 END), 0) AS amount_today,
            (SELECT COALESCE(SUM(amount), 0) FROM product_commissions) AS total_commissions
        FROM product_claims
    ")->fetch(PDO::FETCH_ASSOC);

    $sql = "
        SELECT
            pc.id,
            pc.user_id,
            u.username,
            up.product_name,
            pc.amount,
            pc.claimed_at,
            pc.purchase_id,
            COALESCE((SELECT SUM(c.amount) FROM product_commissions c WHERE c.claim_id = pc.id), 0) AS team_commissions
        FROM product_claims pc
        INNER JOIN users u ON u.id = pc.user_id
        INNER JOIN marketplace_purchases up ON up.id = pc.purchase_id
    ";
    $conditions = [];
    $parameters = [];

    if ($search !== '') {
        $conditions[] = "(u.username LIKE :username_search OR CAST(pc.user_id AS CHAR) LIKE :user_id_search OR up.product_name LIKE :product_search)";
        $likeSearch = '%' . $search . '%';
        $parameters['username_search'] = $likeSearch;
        $parameters['user_id_search'] = $likeSearch;
        $parameters['product_search'] = $likeSearch;
    }

    if ($claimDate !== '') {
        $conditions[] = 'DATE(pc.claimed_at) = :claim_date';
        $parameters['claim_date'] = $claimDate;
    }

    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY pc.id DESC LIMIT 200';
    $claimsQuery = $db->prepare($sql);
    $claimsQuery->execute($parameters);
    $claims = $claimsQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Admin product profit claims query failed: ' . $e->getMessage());
    $loadError = 'Unable to load product profit claims. Confirm the product marketplace migration has been applied.';
}

$adminTitle = 'Profit Claims';
$adminActive = 'profit-claims';
$adminRoot = './';
require __DIR__ . '/../includes/admin_header.php';
?>

<section class="metric-grid mb-4">
    <article class="metric-card">
        <div class="metric-top">
            <span>Total claims</span>
            <span class="metric-icon"><i class="bi bi-receipt" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value"><?= number_format((int) ($stats['total_claims'] ?? 0)) ?></div>
        <div class="metric-foot">Claims recorded in the ledger</div>
    </article>
    <article class="metric-card">
        <div class="metric-top">
            <span>Total wallet credits</span>
            <span class="metric-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value"><?= number_format((float) ($stats['total_amount'] ?? 0), 2) ?></div>
        <div class="metric-foot">ETB credited for product claims</div>
    </article>
    <article class="metric-card">
        <div class="metric-top">
            <span>Claims today</span>
            <span class="metric-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value"><?= number_format((int) ($stats['claims_today'] ?? 0)) ?></div>
        <div class="metric-foot"><?= number_format((float) ($stats['amount_today'] ?? 0), 2) ?> ETB credited today</div>
    </article>
    <article class="metric-card">
        <div class="metric-top">
            <span>Team commissions paid</span>
            <span class="metric-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value"><?= number_format((float) ($stats['total_commissions'] ?? 0), 2) ?></div>
        <div class="metric-foot">ETB credited across levels 1–3</div>
    </article>
</section>

<?php if ($loadError !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= html_escape($loadError) ?></div>
<?php endif; ?>

<section class="data-panel">
    <div class="data-panel-header">
        <div>
            <h2>Daily product profit claims</h2>
            <p class="muted mb-0">Each listed claim is credited to the user’s in-app wallet when submitted.</p>
        </div>
        <span class="status-badge success">Wallet ledger</span>
    </div>

    <form method="get" class="row g-2 p-3 border-bottom">
        <div class="col-md-6">
            <label for="claim-search" class="visually-hidden">Search claims</label>
            <input id="claim-search" class="form-control" type="search" name="search" value="<?= html_escape($search) ?>" placeholder="Search username, user ID, or product">
        </div>
        <div class="col-md-3">
            <label for="claim-date" class="visually-hidden">Filter by claim date</label>
            <input id="claim-date" class="form-control" type="date" name="date" value="<?= html_escape($claimDate) ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1" aria-hidden="true"></i>Filter</button>
            <a href="profit-claims.php" class="btn btn-outline-secondary">Clear</a>
        </div>
    </form>

    <?php if ($claims === []): ?>
        <div class="p-5 text-center">
            <span class="empty-icon"><i class="bi bi-cash-stack" aria-hidden="true"></i></span>
            <strong class="d-block mb-1">No claims found</strong>
            <span class="small-note">Product profit claims will appear here after users claim them.</span>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Claim</th>
                        <th>User</th>
                        <th>Product</th>
                        <th>User profit</th>
                        <th>Team commissions</th>
                        <th>Claimed at</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($claims as $claim): ?>
                        <tr>
                            <td>#<?= (int) $claim['id'] ?></td>
                            <td>
                                <?= html_escape((string) $claim['username']) ?>
                                <small class="d-block muted">User #<?= (int) $claim['user_id'] ?></small>
                            </td>
                            <td>
                                <?= html_escape((string) $claim['product_name']) ?>
                                <small class="d-block muted">Purchase #<?= (int) $claim['purchase_id'] ?></small>
                            </td>
                            <td class="text-success fw-semibold">+<?= number_format((float) $claim['amount'], 2) ?> ETB</td>
                            <td><?= number_format((float) $claim['team_commissions'], 2) ?> ETB</td>
                            <td><?= html_escape((string) $claim['claimed_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small-note px-3 py-2 mb-0">Showing up to 200 most recent matching claims.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
