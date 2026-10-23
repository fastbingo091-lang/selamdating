<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_auth.php';

$adminId = require_active_admin();
$db = database();
$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $requestInput = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
    $requestId = $requestInput === false || $requestInput === null ? 0 : (int) $requestInput;
    $actionInput = $_POST['action'] ?? '';
    $action = is_string($actionInput) ? $actionInput : '';
    $referenceInput = $_POST['payout_reference'] ?? '';
    $payoutReference = is_string($referenceInput) ? trim($referenceInput) : '';
    $reasonInput = $_POST['rejection_reason'] ?? '';
    $rejectionReason = is_string($reasonInput) ? trim($reasonInput) : '';

    try {
        if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Your form expired. Reload the page and try again.');
        }
        if ($requestId <= 0 || !in_array($action, ['complete', 'reject'], true)) {
            throw new RuntimeException('Choose a valid withdrawal request and action.');
        }
        if ($action === 'complete' && ($payoutReference === '' || mb_strlen($payoutReference) > 150)) {
            throw new RuntimeException('Enter the payout reference after the bank or Telebirr transfer has been sent.');
        }
        if ($action === 'reject' && ($rejectionReason === '' || mb_strlen($rejectionReason) > 500)) {
            throw new RuntimeException('Enter a rejection reason between 1 and 500 characters.');
        }

        $db->beginTransaction();
        $requestQuery = $db->prepare("
            SELECT id, user_id, wallet_id, amount, currency, status
            FROM withdrawal_requests
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $requestQuery->execute([$requestId]);
        $request = $requestQuery->fetch(PDO::FETCH_ASSOC);
        if (!$request || $request['status'] !== 'pending') {
            throw new RuntimeException('This withdrawal request is missing or already processed.');
        }

        $walletQuery = $db->prepare('SELECT id, balance, locked_balance FROM wallets WHERE id = ? FOR UPDATE');
        $walletQuery->execute([(int) $request['wallet_id']]);
        $wallet = $walletQuery->fetch(PDO::FETCH_ASSOC);
        if (!$wallet || (float) $wallet['locked_balance'] < (float) $request['amount']) {
            throw new RuntimeException('The wallet does not contain the reserved withdrawal amount.');
        }

        if ($action === 'complete') {
            $debit = $db->prepare("
                UPDATE wallets
                SET balance = balance - ?, locked_balance = locked_balance - ?
                WHERE id = ? AND balance >= ? AND locked_balance >= ?
            ");
            $debit->execute([
                $request['amount'],
                $request['amount'],
                (int) $wallet['id'],
                $request['amount'],
                $request['amount'],
            ]);
            if ($debit->rowCount() !== 1) {
                throw new RuntimeException('Unable to debit the reserved withdrawal amount.');
            }

            $balanceAfterQuery = $db->prepare('SELECT balance FROM wallets WHERE id = ?');
            $balanceAfterQuery->execute([(int) $wallet['id']]);
            $balanceAfter = (string) $balanceAfterQuery->fetchColumn();
            $reference = 'WDR-' . $requestId . '-' . strtoupper(bin2hex(random_bytes(6)));
            $transactionInsert = $db->prepare("
                INSERT INTO transactions (
                    user_id, wallet_id, type, amount, balance_before, balance_after,
                    reference, description, status
                ) VALUES (?, ?, 'WITHDRAW', ?, ?, ?, ?, ?, 'completed')
            ");
            $transactionInsert->execute([
                (int) $request['user_id'],
                (int) $wallet['id'],
                '-' . number_format((float) $request['amount'], 8, '.', ''),
                (string) $wallet['balance'],
                $balanceAfter,
                $reference,
                'Agent payout for withdrawal #' . $requestId,
            ]);

            $updateRequest = $db->prepare("
                UPDATE withdrawal_requests
                SET status = 'completed', payout_reference = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");
            $updateRequest->execute([$payoutReference, $adminId, $requestId]);
            $message = 'Withdrawal marked as paid and debited from the user wallet.';
            $logAction = 'withdrawal_paid';
            $logDescription = 'Withdrawal #' . $requestId . ' paid for ' . number_format((float) $request['amount'], 2) . ' ETB.';
        } else {
            $release = $db->prepare('UPDATE wallets SET locked_balance = locked_balance - ? WHERE id = ? AND locked_balance >= ?');
            $release->execute([$request['amount'], (int) $wallet['id'], $request['amount']]);
            if ($release->rowCount() !== 1) {
                throw new RuntimeException('Unable to release the reserved withdrawal amount.');
            }
            $updateRequest = $db->prepare("
                UPDATE withdrawal_requests
                SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");
            $updateRequest->execute([$rejectionReason, $adminId, $requestId]);
            $message = 'Withdrawal rejected and the reserved funds were released.';
            $logAction = 'withdrawal_rejected';
            $logDescription = 'Withdrawal #' . $requestId . ' rejected: ' . $rejectionReason;
        }

        $log = $db->prepare('INSERT INTO activity_logs (user_id, admin_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
        $log->execute([
            (int) $request['user_id'],
            $adminId,
            $logAction,
            $logDescription,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
        ]);
        $db->commit();
    } catch (RuntimeException $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Admin withdrawal processing failed: ' . $exception->getMessage());
        $error = 'We could not process this request. Please retry.';
    }
}

$requests = $db->query("
    SELECT r.id, r.user_id, r.amount, r.currency, r.destination_provider, r.destination_name,
        r.destination_account, r.status, r.payout_reference, r.rejection_reason, r.created_at,
        u.username
    FROM withdrawal_requests r
    INNER JOIN users u ON u.id = r.user_id
    ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, r.id DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
$pendingCount = count(array_filter($requests, static fn (array $request): bool => $request['status'] === 'pending'));

$adminTitle = 'Withdrawal Requests';
$adminActive = 'withdrawals';
$adminRoot = './';
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="metric-grid mb-4">
    <article class="metric-card"><div class="metric-top"><span>Pending withdrawals</span><span class="metric-icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span></div><div class="metric-value"><?= number_format($pendingCount) ?></div><div class="metric-foot">Awaiting agent payout or review</div></article>
</section>
<?php if ($message !== ''): ?><div class="alert alert-success" role="status"><?= html_escape($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
<section class="data-panel">
    <div class="data-panel-header"><div><h2>Agent payout queue</h2><p class="muted mb-0">Confirm external bank/Telebirr payouts before marking a request complete.</p></div></div>
    <?php if ($requests === []): ?>
        <div class="p-5 text-center muted">No withdrawal requests yet.</div>
    <?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Request</th><th>User</th><th>Amount</th><th>Payout destination</th><th>Status</th><th>Agent action</th></tr></thead>
            <tbody><?php foreach ($requests as $request): ?>
                <tr>
                    <td>#<?= (int) $request['id'] ?><br><small><?= html_escape((string) $request['created_at']) ?></small></td>
                    <td><?= html_escape((string) $request['username']) ?><br><small>User #<?= (int) $request['user_id'] ?></small></td>
                    <td><?= number_format((float) $request['amount'], 2) ?> <?= html_escape((string) $request['currency']) ?></td>
                    <td><strong><?= html_escape((string) $request['destination_provider']) ?></strong><br><?= html_escape((string) $request['destination_name']) ?><br><?= html_escape((string) $request['destination_account']) ?></td>
                    <td><?= html_escape(ucfirst((string) $request['status'])) ?><?php if ($request['rejection_reason']): ?><br><small><?= html_escape((string) $request['rejection_reason']) ?></small><?php endif; ?></td>
                    <td>
                        <?php if ($request['status'] === 'pending'): ?>
                            <form method="post" class="mb-2">
                                <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="action" value="complete">
                                <label class="visually-hidden" for="payout-ref-<?= (int) $request['id'] ?>">Payout reference</label>
                                <input class="form-control form-control-sm mb-2" id="payout-ref-<?= (int) $request['id'] ?>" name="payout_reference" maxlength="150" placeholder="Bank/Telebirr reference" required>
                                <button class="btn btn-sm btn-success" type="submit">Mark paid</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="action" value="reject">
                                <label class="visually-hidden" for="reject-reason-<?= (int) $request['id'] ?>">Reason for rejection</label>
                                <input class="form-control form-control-sm mb-2" id="reject-reason-<?= (int) $request['id'] ?>" name="rejection_reason" maxlength="500" placeholder="Reason for rejection" required>
                                <button class="btn btn-sm btn-outline-danger" type="submit">Reject and release</button>
                            </form>
                        <?php else: ?>
                            <?= $request['status'] === 'completed' ? 'Payout: ' . html_escape((string) $request['payout_reference']) : 'Funds released' ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
