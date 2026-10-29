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

$partnerQuery = $db->prepare('SELECT 1 FROM marketplace_partner_memberships WHERE user_id = ? LIMIT 1');
$partnerQuery->execute([$userId]);
$isPartner = $partnerQuery->fetchColumn() !== false;

$accountStmt = $db->prepare('SELECT referral_code FROM users WHERE id = ? LIMIT 1');
$accountStmt->execute([$userId]);
$referralCode = $accountStmt->fetchColumn();
if (!is_string($referralCode) || $referralCode === '') {
    http_response_code(503);
    exit('Referral tools are unavailable. Apply the marketplace referrals migration and try again.');
}

$teamQuery = $db->prepare("
    SELECT 1 AS team_level, u.id, u.username, u.created_at
    FROM users u
    WHERE u.referred_by_user_id = :level_one_user
    UNION ALL
    SELECT 2 AS team_level, u.id, u.username, u.created_at
    FROM users u
    INNER JOIN users parent ON parent.id = u.referred_by_user_id
    WHERE parent.referred_by_user_id = :level_two_user
    UNION ALL
    SELECT 3 AS team_level, u.id, u.username, u.created_at
    FROM users u
    INNER JOIN users parent ON parent.id = u.referred_by_user_id
    INNER JOIN users grandparent ON grandparent.id = parent.referred_by_user_id
    WHERE grandparent.referred_by_user_id = :level_three_user
    ORDER BY team_level, id DESC
    LIMIT 200
");
$teamQuery->execute([
    'level_one_user' => $userId,
    'level_two_user' => $userId,
    'level_three_user' => $userId,
]);
$teamMembers = $teamQuery->fetchAll(PDO::FETCH_ASSOC);

$teamCounts = [1 => 0, 2 => 0, 3 => 0];
$countQuery = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM users WHERE referred_by_user_id = :level_one_user) AS level_one,
        (SELECT COUNT(*) FROM users direct
            INNER JOIN users u ON u.referred_by_user_id = direct.id
            WHERE direct.referred_by_user_id = :level_two_user) AS level_two,
        (SELECT COUNT(*) FROM users third
            INNER JOIN users second ON second.id = third.referred_by_user_id
            INNER JOIN users direct ON direct.id = second.referred_by_user_id
            WHERE direct.referred_by_user_id = :level_three_user) AS level_three
");
$countQuery->execute([
    'level_one_user' => $userId,
    'level_two_user' => $userId,
    'level_three_user' => $userId,
]);
$counts = $countQuery->fetch(PDO::FETCH_ASSOC);
$teamCounts = [
    1 => (int) ($counts['level_one'] ?? 0),
    2 => (int) ($counts['level_two'] ?? 0),
    3 => (int) ($counts['level_three'] ?? 0),
];

$commissionQuery = $db->prepare("
    SELECT level, SUM(amount) AS total_amount
    FROM product_commissions
    WHERE beneficiary_user_id = ?
    GROUP BY level
");
$commissionQuery->execute([$userId]);
$commissions = [1 => 0.0, 2 => 0.0, 3 => 0.0];
foreach ($commissionQuery->fetchAll(PDO::FETCH_ASSOC) as $commission) {
    $level = (int) $commission['level'];
    if (isset($commissions[$level])) {
        $commissions[$level] = (float) $commission['total_amount'];
    }
}

$pageTitle = 'My Team';
$pageDescription = 'Invite team members and view referral commissions earned from their daily product-profit claims.';
$activeNav = 'team';
require __DIR__ . '/includes/app_header.php';

$referralUrl = 'register.php?ref=' . rawurlencode($referralCode);
?>

<div class="container-fluid py-4">
    <section class="data-panel p-4 mb-4">
        <span class="eyebrow">Referral team</span>
        <h2 class="h4 mt-2">Invite and grow your team</h2>
        <p class="text-secondary">Earn a commission when a team member in levels 1–3 claims daily product profit. Partners also earn an extra 9% from direct invitees. Commissions are credited to your ETB wallet.</p>
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label for="referral-code" class="form-label">Your referral code</label>
                <input id="referral-code" class="form-control" value="<?= html_escape($referralCode) ?>" readonly>
            </div>
            <div class="col-lg-6">
                <label for="referral-link" class="form-label">Your invite link</label>
                <input id="referral-link" class="form-control" value="<?= html_escape($referralUrl) ?>" readonly>
            </div>
            <div class="col-lg-2 d-grid">
                <button class="btn btn-primary" type="button" id="copy-referral-link">
                    <i class="bi bi-copy me-1" aria-hidden="true"></i>Copy link
                </button>
            </div>
        </div>
        <div class="small-note mt-2" id="copy-referral-status" role="status"></div>
    </section>

    <section class="metric-grid mb-4" aria-label="Referral team totals">
        <?php foreach ([1 => ($isPartner ? 24 : 15), 2 => 5, 3 => 3] as $level => $rate): ?>
            <article class="metric-card">
                <div class="metric-top">
                    <span>Level <?= $level ?> commission</span>
                    <span class="metric-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                </div>
                <div class="metric-value"><?= $rate ?>%</div>
                <div class="metric-foot"><?= number_format($teamCounts[$level]) ?> team members · <?= number_format($commissions[$level], 2) ?> ETB earned</div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="data-panel">
        <div class="data-panel-header">
            <div>
                <h2>Your team</h2>
                <p class="muted mb-0">Commissions are calculated from each eligible daily profit claim.</p>
            </div>
            <span class="status-badge success"><?= number_format(array_sum($teamCounts)) ?> members</span>
        </div>
        <?php if ($teamMembers === []): ?>
            <div class="p-5 text-center">
                <span class="empty-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                <strong class="d-block mb-1">Your team is ready to grow</strong>
                <span class="small-note">Share your invitation link to start building your three-level team.</span>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Member</th><th>Level</th><th>Joined</th></tr></thead>
                    <tbody>
                    <?php foreach ($teamMembers as $member): ?>
                        <tr>
                            <td><?= html_escape((string) $member['username']) ?> <small class="d-block muted">#<?= (int) $member['id'] ?></small></td>
                            <td>Level <?= (int) $member['team_level'] ?> (<?= [1 => 15, 2 => 5, 3 => 3][(int) $member['team_level']] ?>%)</td>
                            <td><?= html_escape((string) $member['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (array_sum($teamCounts) > count($teamMembers)): ?>
                <p class="small-note px-3 py-2 mb-0">Showing the latest 200 team members.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<script>
    document.getElementById('copy-referral-link').addEventListener('click', async () => {
        const link = document.getElementById('referral-link');
        const status = document.getElementById('copy-referral-status');
        const inviteUrl = new URL(link.value, window.location.href).href;
        let copied = false;
        link.value = inviteUrl;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(inviteUrl);
                copied = true;
            }
        } catch {
            copied = false;
        }

        if (!copied) {
            link.focus();
            link.select();
            try {
                copied = document.execCommand('copy');
            } catch {
                copied = false;
            }
        }

        status.textContent = copied
            ? 'Invite link copied.'
            : 'Copy was blocked. Select and copy the invite link.';
    });
</script>
<?php require __DIR__ . '/includes/app_footer.php'; ?>
