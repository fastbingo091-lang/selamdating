<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';

start_app_session();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$db = database();
$error = '';
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Reload the page and try again.';
    } else {
        try {
            $db->beginTransaction();

            $settingsQuery = $db->query('SELECT reward_amount, enabled FROM marketplace_daily_streak_settings WHERE id = 1');
            $settings = $settingsQuery->fetch(PDO::FETCH_ASSOC);
            if (!$settings || (int) $settings['enabled'] !== 1 || (float) $settings['reward_amount'] <= 0) {
                throw new RuntimeException('Daily streak rewards are not available right now.');
            }

            $db->prepare('INSERT IGNORE INTO marketplace_daily_streaks (user_id) VALUES (?)')->execute([$userId]);
            $streakQuery = $db->prepare('SELECT current_streak, last_claim_date FROM marketplace_daily_streaks WHERE user_id = ? FOR UPDATE');
            $streakQuery->execute([$userId]);
            $streak = $streakQuery->fetch(PDO::FETCH_ASSOC);
            if (!$streak) {
                throw new RuntimeException('Your daily streak could not be loaded.');
            }

            $dateQuery = $db->prepare('SELECT CURDATE() AS claim_date, DATEDIFF(CURDATE(), ?) AS days_since');
            $dateQuery->execute([$streak['last_claim_date']]);
            $claimDate = $dateQuery->fetch(PDO::FETCH_ASSOC);
            if (!$claimDate || $claimDate['claim_date'] === null) {
                throw new RuntimeException('Unable to verify today’s date. Please try again.');
            }
            if ($streak['last_claim_date'] !== null && (string) $streak['last_claim_date'] === (string) $claimDate['claim_date']) {
                throw new RuntimeException('You have already claimed today’s streak reward.');
            }

            $streakDay = (int) $claimDate['days_since'] === 1 ? (int) $streak['current_streak'] + 1 : 1;
            $amount = number_format((float) $settings['reward_amount'], 2, '.', '');

            $walletQuery = $db->prepare("
                SELECT id, balance
                FROM wallets
                WHERE user_id = ? AND currency = 'ETB'
                ORDER BY id
                LIMIT 1
                FOR UPDATE
            ");
            $walletQuery->execute([$userId]);
            $wallet = $walletQuery->fetch(PDO::FETCH_ASSOC);
            if (!$wallet) {
                throw new RuntimeException('Your ETB wallet is not ready for rewards.');
            }

            $balanceBefore = (string) $wallet['balance'];
            $credit = $db->prepare('UPDATE wallets SET balance = balance + ? WHERE id = ? AND user_id = ?');
            $credit->execute([$amount, (int) $wallet['id'], $userId]);
            if ($credit->rowCount() !== 1) {
                throw new RuntimeException('Unable to credit today’s streak reward.');
            }

            $balanceAfterQuery = $db->prepare('SELECT balance FROM wallets WHERE id = ?');
            $balanceAfterQuery->execute([(int) $wallet['id']]);
            $balanceAfter = $balanceAfterQuery->fetchColumn();
            if (!is_string($balanceAfter)) {
                throw new RuntimeException('Unable to verify the reward balance.');
            }

            $reference = 'STREAK-' . strtoupper(bin2hex(random_bytes(8)));
            $transactionInsert = $db->prepare("
                INSERT INTO transactions (user_id, wallet_id, type, amount, balance_before, balance_after, reference, description, status)
                VALUES (?, ?, 'WIN', ?, ?, ?, ?, ?, 'completed')
            ");
            $transactionInsert->execute([
                $userId,
                (int) $wallet['id'],
                $amount,
                $balanceBefore,
                $balanceAfter,
                $reference,
                'Daily streak reward · day ' . $streakDay,
            ]);
            $transactionId = (int) $db->lastInsertId();

            $claimInsert = $db->prepare("
                INSERT INTO marketplace_daily_streak_claims (user_id, claim_date, streak_day, amount, transaction_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $claimInsert->execute([$userId, $claimDate['claim_date'], $streakDay, $amount, $transactionId]);

            $streakUpdate = $db->prepare("
                UPDATE marketplace_daily_streaks
                SET current_streak = ?, last_claim_date = ?, total_claims = total_claims + 1
                WHERE user_id = ?
            ");
            $streakUpdate->execute([$streakDay, $claimDate['claim_date'], $userId]);

            $db->commit();
            $message = 'You received ' . number_format((float) $amount, 2) . ' ETB for day ' . $streakDay . ' of your streak.';
        } catch (RuntimeException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log(sprintf('Daily streak claim failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
            $error = 'Unable to claim your daily reward. Please try again.';
        }
    }
}

$settingsQuery = $db->query('SELECT reward_amount, enabled FROM marketplace_daily_streak_settings WHERE id = 1');
$settings = $settingsQuery->fetch(PDO::FETCH_ASSOC);
$streakQuery = $db->prepare('SELECT current_streak, last_claim_date, total_claims FROM marketplace_daily_streaks WHERE user_id = ? LIMIT 1');
$streakQuery->execute([$userId]);
$streak = $streakQuery->fetch(PDO::FETCH_ASSOC) ?: [
    'current_streak' => 0,
    'last_claim_date' => null,
    'total_claims' => 0,
];
$today = (string) $db->query('SELECT CURDATE()')->fetchColumn();
$claimedToday = $streak['last_claim_date'] !== null && (string) $streak['last_claim_date'] === $today;
$streakActive = $settings && (int) $settings['enabled'] === 1 && (float) $settings['reward_amount'] > 0;
$displayStreak = $streak['last_claim_date'] !== null && (string) $streak['last_claim_date'] === (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d')
    ? (int) $streak['current_streak']
    : ($claimedToday ? (int) $streak['current_streak'] : 0);

$appRoot = '';
$pageTitle = 'Daily streak';
$pageDescription = 'Claim a daily reward and keep your consecutive-day streak going.';
$activeNav = 'rewards';
require __DIR__ . '/includes/app_header.php';
?>
<section class="daily-streak-card data-panel">
    <div class="daily-streak-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></div>
    <div class="daily-streak-content">
        <span class="eyebrow">Daily reward</span>
        <h2><?= number_format($displayStreak) ?> day<?= $displayStreak === 1 ? '' : 's' ?> in a row</h2>
        <p class="muted">Claim once each day. Missing a day resets your streak to day 1.</p>
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
        <?php if ($message !== ''): ?><div class="alert alert-success" role="status"><?= html_escape($message) ?></div><?php endif; ?>
        <?php if (!$streakActive): ?>
            <div class="alert alert-info" role="status">Daily streak rewards are not enabled yet.</div>
        <?php else: ?>
            <div class="daily-streak-reward">+<?= number_format((float) $settings['reward_amount'], 2) ?> ETB</div>
            <form method="post" class="mt-3" data-loading-form>
                <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                <button class="btn btn-primary" type="submit" data-loading-button <?= $claimedToday ? 'disabled' : '' ?>>
                    <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>
                    <?= $claimedToday ? 'Claimed today' : 'Claim today’s reward' ?>
                </button>
            </form>
        <?php endif; ?>
        <p class="small-note mt-3 mb-0">Total daily claims: <?= number_format((int) $streak['total_claims']) ?></p>
    </div>
</section>
<?php require __DIR__ . '/includes/app_footer.php'; ?>
