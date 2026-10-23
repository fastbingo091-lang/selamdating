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
$recipientId = '';
$amount = '';
$wallet = null;

if (!isset($_SESSION['transfer_form_token']) || !is_string($_SESSION['transfer_form_token'])) {
    $_SESSION['transfer_form_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $recipientInput = $_POST['recipient_id'] ?? '';
    $recipientId = is_string($recipientInput) ? trim($recipientInput) : '';
    $amountInput = $_POST['amount'] ?? '';
    $amount = is_string($amountInput) ? trim($amountInput) : '';
    $formToken = $_POST['transfer_form_token'] ?? '';

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Reload the page and try again.';
    } elseif (
        !is_string($formToken)
        || !isset($_SESSION['transfer_form_token'])
        || !hash_equals($_SESSION['transfer_form_token'], $formToken)
    ) {
        $error = 'This transfer form has already been used. Reload the page before sending again.';
    } elseif (!preg_match('/\A[1-9][0-9]{0,19}\z/', $recipientId)) {
        $error = 'Enter the recipient’s numeric Selam user ID.';
    } elseif (!preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', $amount) || (float) $amount <= 0) {
        $error = 'Enter a valid amount greater than zero, with up to two decimal places.';
    } elseif ((int) $recipientId === $userId) {
        $error = 'You cannot transfer money to your own account.';
    } else {
        $amount = number_format((float) $amount, 2, '.', '');
        try {
            $db->beginTransaction();

            $accountQuery = $db->prepare("
                SELECT id, status
                FROM users
                WHERE id IN (?, ?)
                ORDER BY id
                FOR UPDATE
            ");
            $accountQuery->execute([$userId, (int) $recipientId]);
            $accounts = [];
            foreach ($accountQuery->fetchAll(PDO::FETCH_ASSOC) as $account) {
                $accounts[(int) $account['id']] = (string) $account['status'];
            }

            if (($accounts[$userId] ?? null) !== 'active') {
                throw new RuntimeException('Your account is not active for transfers.');
            }
            if (($accounts[(int) $recipientId] ?? null) !== 'active') {
                throw new RuntimeException('No active account was found for that user ID.');
            }

            $walletForUser = static function (PDO $connection, int $accountId): array {
                $walletQuery = $connection->prepare("
                    SELECT id, currency, balance, locked_balance
                    FROM wallets
                    WHERE user_id = ? AND currency = 'ETB'
                    LIMIT 1
                    FOR UPDATE
                ");
                $walletQuery->execute([$accountId]);
                $result = $walletQuery->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    return $result;
                }

                $legacyQuery = $connection->prepare("
                    SELECT id, currency, balance, locked_balance
                    FROM wallets
                    WHERE user_id = ?
                    ORDER BY id
                    LIMIT 1
                    FOR UPDATE
                ");
                $legacyQuery->execute([$accountId]);
                $result = $legacyQuery->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $convert = $connection->prepare("UPDATE wallets SET currency = 'ETB' WHERE id = ?");
                    $convert->execute([(int) $result['id']]);
                    $result['currency'] = 'ETB';
                    return $result;
                }

                $create = $connection->prepare("
                    INSERT INTO wallets (user_id, currency, balance, locked_balance)
                    VALUES (?, 'ETB', 0.00000000, 0.00000000)
                ");
                $create->execute([$accountId]);
                return [
                    'id' => (int) $connection->lastInsertId(),
                    'currency' => 'ETB',
                    'balance' => '0.00000000',
                    'locked_balance' => '0.00000000',
                ];
            };

            $senderWallet = $walletForUser($db, $userId);
            $recipientWallet = $walletForUser($db, (int) $recipientId);

            $debit = $db->prepare("
                UPDATE wallets
                SET balance = balance - ?
                WHERE id = ? AND balance - locked_balance >= ?
            ");
            $debit->execute([$amount, (int) $senderWallet['id'], $amount]);
            if ($debit->rowCount() !== 1) {
                throw new RuntimeException('Your available balance is not enough for this transfer.');
            }

            $credit = $db->prepare('UPDATE wallets SET balance = balance + ? WHERE id = ?');
            $credit->execute([$amount, (int) $recipientWallet['id']]);

            $senderAfterQuery = $db->prepare('SELECT balance FROM wallets WHERE id = ?');
            $senderAfterQuery->execute([(int) $senderWallet['id']]);
            $senderAfter = (string) $senderAfterQuery->fetchColumn();
            $recipientAfterQuery = $db->prepare('SELECT balance FROM wallets WHERE id = ?');
            $recipientAfterQuery->execute([(int) $recipientWallet['id']]);
            $recipientAfter = (string) $recipientAfterQuery->fetchColumn();

            $reference = 'TRF-' . strtoupper(bin2hex(random_bytes(10)));
            $insertTransaction = $db->prepare("
                INSERT INTO transactions (
                    user_id, wallet_id, type, amount, balance_before, balance_after,
                    reference, description, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')
            ");
            $insertTransaction->execute([
                $userId,
                (int) $senderWallet['id'],
                'TRANSFER_OUT',
                '-' . $amount,
                (string) $senderWallet['balance'],
                $senderAfter,
                $reference . '-OUT',
                'Transfer to Selam user #' . (int) $recipientId,
            ]);
            $insertTransaction->execute([
                (int) $recipientId,
                (int) $recipientWallet['id'],
                'TRANSFER_IN',
                $amount,
                (string) $recipientWallet['balance'],
                $recipientAfter,
                $reference . '-IN',
                'Transfer received from Selam user #' . $userId,
            ]);

            $db->commit();
            $_SESSION['transfer_form_token'] = bin2hex(random_bytes(32));
            $recipientNameQuery = $db->prepare('SELECT username FROM users WHERE id = ?');
            $recipientNameQuery->execute([(int) $recipientId]);
            $success = 'Sent ' . $amount . ' ETB to user #' . (int) $recipientId . ' (' . (string) $recipientNameQuery->fetchColumn() . ').';
            $recipientId = '';
            $amount = '';
        } catch (RuntimeException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Selam wallet transfer failed: ' . $exception->getMessage());
            $error = 'We could not complete this transfer. Check your balance and try again.';
        }
    }
}

$walletQuery = $db->prepare("
    SELECT id, balance, locked_balance
    FROM wallets
    WHERE user_id = ? AND currency = 'ETB'
    ORDER BY id
    LIMIT 1
");
$walletQuery->execute([$userId]);
$wallet = $walletQuery->fetch(PDO::FETCH_ASSOC);
$availableBalance = $wallet
    ? max(0, (float) $wallet['balance'] - (float) $wallet['locked_balance'])
    : 0.0;

$pageTitle = 'Transfer';
$pageDescription = 'Send ETB to another Selam wallet using their numeric user ID.';
$activeNav = 'transfer';
$appRoot = '../';
require __DIR__ . '/../includes/app_header.php';
?>
<section class="content-grid">
    <div class="data-panel">
        <div class="data-panel-header"><h2>Send to a Selam user</h2><span class="status-badge success">Internal transfer</span></div>
        <form method="post" class="p-4">
            <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
            <input type="hidden" name="transfer_form_token" value="<?= html_escape($_SESSION['transfer_form_token']) ?>">
            <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
            <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= html_escape($success) ?></div><?php endif; ?>
            <p class="muted">Your Selam user ID is <strong>#<?= $userId ?></strong>. Share it with people who need to send you money.</p>
            <div class="mb-3">
                <label class="form-label" for="recipient-id">Recipient user ID</label>
                <input class="form-control" id="recipient-id" name="recipient_id" type="text" inputmode="numeric" pattern="[0-9]+" maxlength="20" placeholder="For example, 125" value="<?= html_escape($recipientId) ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label" for="transfer-amount">Amount</label>
                <div class="input-group"><input class="form-control" id="transfer-amount" name="amount" type="number" min="0.01" step="0.01" max="<?= html_escape(number_format($availableBalance, 2, '.', '')) ?>" placeholder="0.00" value="<?= html_escape($amount) ?>" required><span class="input-group-text">ETB</span></div>
                <div class="form-text">Available balance: <?= number_format($availableBalance, 2) ?> ETB</div>
            </div>
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Transfer</button>
        </form>
    </div>
    <aside class="data-panel">
        <div class="data-panel-header"><h2>Transfer safely</h2></div>
        <div class="p-4">
            <p>Confirm the recipient’s numeric Selam user ID with them before sending. Transfers are immediate and can’t be reversed from this page.</p>
            <p class="small-note mb-0">This moves ETB between Selam wallet balances only. Deposits are reviewed by Selam agents; withdrawals to a bank or Telebirr account require agent processing.</p>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/../includes/app_footer.php'; ?>
