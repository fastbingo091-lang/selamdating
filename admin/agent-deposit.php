<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_auth.php';

$adminId = require_active_admin();
$db = database();
$error = '';
$success = '';
$userIdInput = '';
$amountInput = '';
$depositNote = '';

if (!isset($_SESSION['agent_deposit_form_token']) || !is_string($_SESSION['agent_deposit_form_token'])) {
    $_SESSION['agent_deposit_form_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $rawUserId = $_POST['user_id'] ?? '';
    $userIdInput = is_string($rawUserId) ? trim($rawUserId) : '';
    $rawAmount = $_POST['amount'] ?? '';
    $amountInput = is_string($rawAmount) ? trim($rawAmount) : '';
    $rawNote = $_POST['deposit_note'] ?? '';
    $depositNote = is_string($rawNote) ? trim($rawNote) : '';
    $formToken = $_POST['agent_deposit_form_token'] ?? '';

    try {
        if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Your form expired. Reload the page and try again.');
        }
        if (
            !is_string($formToken)
            || !isset($_SESSION['agent_deposit_form_token'])
            || !hash_equals($_SESSION['agent_deposit_form_token'], $formToken)
        ) {
            throw new RuntimeException('This agent deposit form has already been used. Reload the page before crediting another deposit.');
        }
        if (!preg_match('/\A[1-9][0-9]{0,19}\z/', $userIdInput)) {
            throw new RuntimeException('Enter the user’s numeric Selam ID.');
        }
        if (!preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', $amountInput) || (float) $amountInput <= 0) {
            throw new RuntimeException('Enter a valid deposit amount greater than zero, with up to two decimal places.');
        }
        if (mb_strlen($depositNote) > 150) {
            throw new RuntimeException('The agent note must be 150 characters or fewer.');
        }

        $amount = number_format((float) $amountInput, 2, '.', '');
        $userId = (int) $userIdInput;
        $db->beginTransaction();

        $userQuery = $db->prepare('SELECT id, username, status FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
        $userQuery->execute([$userId]);
        $user = $userQuery->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['status'] !== 'active') {
            throw new RuntimeException('No active user account was found for that ID.');
        }

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
            } else {
                $createWallet = $db->prepare("INSERT INTO wallets (user_id, currency, balance, locked_balance) VALUES (?, 'ETB', 0, 0)");
                $createWallet->execute([$userId]);
                $wallet = [
                    'id' => (int) $db->lastInsertId(),
                    'balance' => '0.00000000',
                    'locked_balance' => '0.00000000',
                ];
            }
        }

        $before = (string) $wallet['balance'];
        $credit = $db->prepare('UPDATE wallets SET balance = balance + ? WHERE id = ?');
        $credit->execute([$amount, (int) $wallet['id']]);
        $balanceQuery = $db->prepare('SELECT balance FROM wallets WHERE id = ?');
        $balanceQuery->execute([(int) $wallet['id']]);
        $after = (string) $balanceQuery->fetchColumn();
        $reference = 'AGD-' . $userId . '-' . strtoupper(bin2hex(random_bytes(8)));
        $description = 'Agent deposit received by admin #' . $adminId;
        if ($depositNote !== '') {
            $description .= ': ' . $depositNote;
        }

        $transaction = $db->prepare("
            INSERT INTO transactions (
                user_id, wallet_id, type, amount, balance_before, balance_after,
                reference, description, status
            ) VALUES (?, ?, 'DEPOSIT', ?, ?, ?, ?, ?, 'completed')
        ");
        $transaction->execute([$userId, (int) $wallet['id'], $amount, $before, $after, $reference, $description]);

        $log = $db->prepare('INSERT INTO activity_logs (user_id, admin_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
        $log->execute([
            $userId,
            $adminId,
            'agent_deposit_credited',
            'Agent deposit of ' . $amount . ' ETB credited to user #' . $userId . '.',
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
        ]);

        $db->commit();
        $_SESSION['agent_deposit_form_token'] = bin2hex(random_bytes(32));
        $success = 'Credited ' . $amount . ' ETB to ' . (string) $user['username'] . ' (user #' . $userId . ').';
        $userIdInput = '';
        $amountInput = '';
        $depositNote = '';
    } catch (RuntimeException $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Agent deposit credit failed: ' . $exception->getMessage());
        $error = 'We could not credit this deposit. Please retry.';
    }
}

$adminTitle = 'Agent Deposit';
$adminActive = 'agent-deposit';
$adminRoot = './';
require __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= html_escape($success) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
<section class="content-grid">
    <div class="data-panel">
        <div class="data-panel-header"><div><h2>Credit a received deposit</h2><p class="muted mb-0">For an agent who has confirmed the ETB payment was received.</p></div></div>
        <form method="post" class="p-4">
            <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
            <input type="hidden" name="agent_deposit_form_token" value="<?= html_escape($_SESSION['agent_deposit_form_token']) ?>">
            <div class="mb-3"><label class="form-label" for="deposit-user-id">Selam user ID</label><input class="form-control" id="deposit-user-id" name="user_id" inputmode="numeric" pattern="[0-9]+" maxlength="20" value="<?= html_escape($userIdInput) ?>" required></div>
            <div class="mb-3"><label class="form-label" for="deposit-amount">Amount · ETB</label><input class="form-control" id="deposit-amount" name="amount" type="number" min="0.01" step="0.01" value="<?= html_escape($amountInput) ?>" required></div>
            <div class="mb-4"><label class="form-label" for="deposit-note">Agent receipt/reference <span class="muted">(optional)</span></label><input class="form-control" id="deposit-note" name="deposit_note" maxlength="150" value="<?= html_escape($depositNote) ?>"></div>
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-cash-coin" aria-hidden="true"></i> Confirm received and credit wallet</button>
        </form>
    </div>
    <aside class="data-panel">
        <div class="data-panel-header"><h2>Agent checklist</h2></div>
        <div class="p-4"><ol class="mb-0"><li>Confirm the payment has actually arrived in cash or the agent account.</li><li>Verify the user ID with the member.</li><li>Enter the exact amount and a traceable receipt/reference where available.</li></ol><p class="small-note mt-3 mb-0">This action immediately adds ETB to the user’s wallet and is recorded in the agent activity log.</p></div>
    </aside>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
