<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$db = database();
$error = '';
$success = '';
$amount = '';
$provider = '';
$destinationName = '';
$destinationAccount = '';

if (!isset($_SESSION['withdraw_form_token']) || !is_string($_SESSION['withdraw_form_token'])) {
    $_SESSION['withdraw_form_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $amountInput = $_POST['amount'] ?? '';
    $amount = is_string($amountInput) ? trim($amountInput) : '';
    $providerInput = $_POST['provider'] ?? '';
    $provider = is_string($providerInput) ? trim($providerInput) : '';
    $nameInput = $_POST['destination_name'] ?? '';
    $destinationName = is_string($nameInput) ? trim($nameInput) : '';
    $accountInput = $_POST['destination_account'] ?? '';
    $destinationAccount = is_string($accountInput) ? trim($accountInput) : '';
    $formToken = $_POST['withdraw_form_token'] ?? '';

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Reload the page and try again.';
    } elseif (
        !is_string($formToken)
        || !isset($_SESSION['withdraw_form_token'])
        || !hash_equals($_SESSION['withdraw_form_token'], $formToken)
    ) {
        $error = 'This withdrawal form has already been used. Reload the page before requesting another payout.';
    } elseif (!preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', $amount) || (float) $amount <= 0) {
        $error = 'Enter a valid amount greater than zero, with up to two decimal places.';
    } elseif (!in_array($provider, ['Bank', 'Telebirr'], true)) {
        $error = 'Choose Bank or Telebirr as the payout method.';
    } elseif (mb_strlen($destinationName) < 2 || mb_strlen($destinationName) > 120) {
        $error = 'Enter the account holder’s name (2–120 characters).';
    } elseif (
        mb_strlen($destinationAccount) < 4
        || mb_strlen($destinationAccount) > 100
        || preg_match('/\A[A-Za-z0-9+ .-]+\z/', $destinationAccount) !== 1
    ) {
        $error = 'Enter a valid bank account number or Telebirr phone number.';
    } else {
        $amount = number_format((float) $amount, 2, '.', '');
        try {
            $db->beginTransaction();
            $walletQuery = $db->prepare("
                SELECT id, currency, balance, locked_balance
                FROM wallets
                WHERE user_id = ? AND currency = 'ETB'
                LIMIT 1
                FOR UPDATE
            ");
            $walletQuery->execute([$userId]);
            $wallet = $walletQuery->fetch(PDO::FETCH_ASSOC);

            if (!$wallet) {
                $legacyQuery = $db->prepare('SELECT id, currency, balance, locked_balance FROM wallets WHERE user_id = ? ORDER BY id LIMIT 1 FOR UPDATE');
                $legacyQuery->execute([$userId]);
                $wallet = $legacyQuery->fetch(PDO::FETCH_ASSOC);
                if ($wallet) {
                    $convert = $db->prepare("UPDATE wallets SET currency = 'ETB' WHERE id = ?");
                    $convert->execute([(int) $wallet['id']]);
                }
            }

            if (!$wallet) {
                throw new RuntimeException('Your wallet is not ready for withdrawals. Contact Selam support.');
            }

            $reserve = $db->prepare("
                UPDATE wallets
                SET locked_balance = locked_balance + ?
                WHERE id = ? AND balance - locked_balance >= ?
            ");
            $reserve->execute([$amount, (int) $wallet['id'], $amount]);
            if ($reserve->rowCount() !== 1) {
                throw new RuntimeException('Your available balance is not enough for this withdrawal.');
            }

            $createRequest = $db->prepare("
                INSERT INTO withdrawal_requests (
                    user_id, wallet_id, amount, currency, destination_provider,
                    destination_name, destination_account
                ) VALUES (?, ?, ?, 'ETB', ?, ?, ?)
            ");
            $createRequest->execute([
                $userId,
                (int) $wallet['id'],
                $amount,
                $provider,
                $destinationName,
                $destinationAccount,
            ]);
            $db->commit();
            $_SESSION['withdraw_form_token'] = bin2hex(random_bytes(32));
            $success = 'Your withdrawal request was sent to the Selam agents. The amount is reserved until the request is completed or rejected.';
            $amount = '';
            $destinationName = '';
            $destinationAccount = '';
        } catch (RuntimeException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Selam withdrawal request failed: ' . $exception->getMessage());
            $error = 'We could not submit your withdrawal request. Please try again.';
        }
    }
}

$walletQuery = $db->prepare("SELECT balance, locked_balance FROM wallets WHERE user_id = ? AND currency = 'ETB' ORDER BY id LIMIT 1");
$walletQuery->execute([$userId]);
$wallet = $walletQuery->fetch(PDO::FETCH_ASSOC);
$availableBalance = $wallet ? max(0, (float) $wallet['balance'] - (float) $wallet['locked_balance']) : 0.0;

$requestsQuery = $db->prepare("
    SELECT id, amount, currency, destination_provider, destination_name, destination_account,
        status, payout_reference, rejection_reason, created_at, reviewed_at
    FROM withdrawal_requests
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 10
");
$requestsQuery->execute([$userId]);
$requests = $requestsQuery->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Withdraw';
$pageDescription = 'Request an agent-processed payout to your bank or Telebirr account.';
$activeNav = 'wallet';
$appRoot = '../';
require __DIR__ . '/../includes/app_header.php';
?>
<section class="content-grid">
    <div class="data-panel">
        <div class="data-panel-header"><h2>Request a withdrawal</h2><span class="status-badge warning">Agent processed</span></div>
        <form method="post" class="p-4">
            <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
            <input type="hidden" name="withdraw_form_token" value="<?= html_escape($_SESSION['withdraw_form_token']) ?>">
            <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
            <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= html_escape($success) ?></div><?php endif; ?>
            <p class="muted">Available to withdraw: <strong><?= number_format($availableBalance, 2) ?> ETB</strong></p>
            <div class="mb-3">
                <label class="form-label" for="withdraw-amount">Amount · ETB</label>
                <input class="form-control" id="withdraw-amount" name="amount" type="number" min="0.01" step="0.01" max="<?= number_format($availableBalance, 2, '.', '') ?>" value="<?= html_escape($amount) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="withdraw-provider">Payout method</label>
                <select class="form-select" id="withdraw-provider" name="provider" required>
                    <option value="">Choose a method</option>
                    <option value="Bank" <?= $provider === 'Bank' ? 'selected' : '' ?>>Bank</option>
                    <option value="Telebirr" <?= $provider === 'Telebirr' ? 'selected' : '' ?>>Telebirr</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="destination-name">Account holder’s name</label>
                <input class="form-control" id="destination-name" name="destination_name" type="text" maxlength="120" value="<?= html_escape($destinationName) ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label" for="destination-account">Bank account number or Telebirr phone</label>
                <input class="form-control" id="destination-account" name="destination_account" type="text" maxlength="100" autocomplete="off" value="<?= html_escape($destinationAccount) ?>" required>
                <div class="form-text">Your details are visible only to authorized Selam agents processing the payout.</div>
            </div>
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Request withdrawal</button>
        </form>
    </div>
    <aside class="data-panel">
        <div class="data-panel-header"><h2>How payouts work</h2></div>
        <div class="p-4">
            <p>Submitting a request reserves the amount so it cannot be spent twice. A Selam agent sends the payout outside the app, then records the payout reference.</p>
            <p class="small-note mb-0">Do not submit a request until your wallet has been credited. A rejected request releases the reserved amount back to your available balance.</p>
        </div>
    </aside>
</section>

<section class="data-panel mt-4">
    <div class="data-panel-header"><h2>Recent withdrawal requests</h2></div>
    <?php if ($requests === []): ?>
        <div class="p-4 muted">You do not have any withdrawal requests yet.</div>
    <?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Request</th><th>Amount</th><th>Method</th><th>Destination</th><th>Status</th><th>Date</th></tr></thead>
            <tbody><?php foreach ($requests as $request): ?>
                <tr>
                    <td>#<?= (int) $request['id'] ?></td>
                    <td><?= number_format((float) $request['amount'], 2) ?> <?= html_escape((string) $request['currency']) ?></td>
                    <td><?= html_escape((string) $request['destination_provider']) ?></td>
                    <td><?= html_escape((string) $request['destination_name']) ?> · <?= html_escape((string) $request['destination_account']) ?></td>
                    <td><?= html_escape(ucfirst((string) $request['status'])) ?><?php if (!empty($request['rejection_reason'])): ?><br><small><?= html_escape((string) $request['rejection_reason']) ?></small><?php endif; ?></td>
                    <td><?= html_escape((string) $request['created_at']) ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/app_footer.php'; ?>
