<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_auth.php';

$adminId = require_active_admin();
$db = database();
$message = '';
$error = '';
$search = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $actionInput = $_POST['action'] ?? '';
    $userInput = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $targetUserId = $userInput === false || $userInput === null ? 0 : (int) $userInput;
    try {
        if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Your form expired. Reload the page and try again.');
        }
        if ($actionInput !== 'toggle_status' || $targetUserId <= 0) {
            throw new RuntimeException('Choose a valid account action.');
        }

        $db->beginTransaction();
        $userQuery = $db->prepare('SELECT username, status FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
        $userQuery->execute([$targetUserId]);
        $targetUser = $userQuery->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser) {
            throw new RuntimeException('User account not found.');
        }
        $newStatus = $targetUser['status'] === 'active' ? 'disabled' : 'active';
        $updateUser = $db->prepare('UPDATE users SET status = ? WHERE id = ?');
        $updateUser->execute([$newStatus, $targetUserId]);
        $log = $db->prepare('INSERT INTO activity_logs (user_id, admin_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
        $log->execute([
            $targetUserId,
            $adminId,
            $newStatus === 'active' ? 'user_enabled' : 'user_disabled',
            'User #' . $targetUserId . ' (' . (string) $targetUser['username'] . ') status changed to ' . $newStatus . '.',
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
        ]);
        $db->commit();
        $message = 'User #' . $targetUserId . ' is now ' . $newStatus . '.';
    } catch (RuntimeException $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Admin user status update failed: ' . $exception->getMessage());
        $error = 'We could not update the user account. Please retry.';
    }
}

$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$userQuery = "
    SELECT u.id, u.username, u.phone, u.status, u.created_at,
        w.balance, w.currency
    FROM users u
    LEFT JOIN wallets w ON w.user_id = u.id AND w.currency = 'ETB'
";
if ($search !== '') {
    $searchTerm = '%' . $search . '%';
    $userQuery .= ' WHERE u.username LIKE ? OR u.phone LIKE ? OR CAST(u.id AS CHAR) = ?';
    $statement = $db->prepare($userQuery . ' ORDER BY u.id DESC LIMIT 100');
    $statement->execute([$searchTerm, $searchTerm, $search]);
} else {
    $statement = $db->query($userQuery . ' ORDER BY u.id DESC LIMIT 100');
}
$users = $statement->fetchAll(PDO::FETCH_ASSOC);

$adminTitle = 'Users';
$adminActive = 'users';
$adminRoot = './';
require __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($message !== ''): ?><div class="alert alert-success" role="status"><?= html_escape($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
<section class="data-panel">
    <div class="data-panel-header"><div><h2>User directory</h2><p class="muted mb-0">Find users by numeric ID, username, or phone and manage account access.</p></div><span class="status-badge"><?= number_format(count($users)) ?> shown</span></div>
    <form method="get" class="toolbar p-3 pb-0">
        <label class="visually-hidden" for="users-search">Search users</label>
        <input class="form-control search-input" id="users-search" name="search" type="search" placeholder="Search ID, username, or phone" value="<?= html_escape($search) ?>">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
        <?php if ($search !== ''): ?><a class="btn btn-outline-secondary" href="users.php">Clear</a><?php endif; ?>
    </form>
    <div class="table-responsive mt-3"><table class="table align-middle">
        <thead><tr><th>Selam ID</th><th>Username</th><th>Phone</th><th>Wallet</th><th>Status</th><th>Joined</th><th>Account access</th></tr></thead>
        <tbody>
            <?php if ($users === []): ?>
                <tr><td colspan="7" class="table-empty">No matching user accounts.</td></tr>
            <?php else: foreach ($users as $user): ?>
                <tr>
                    <td>#<?= (int) $user['id'] ?></td>
                    <td><?= html_escape((string) $user['username']) ?></td>
                    <td><?= html_escape((string) $user['phone']) ?></td>
                    <td><?= $user['balance'] === null ? '—' : number_format((float) $user['balance'], 2) . ' ' . html_escape((string) $user['currency']) ?></td>
                    <td><span class="status-badge <?= $user['status'] === 'active' ? 'success' : 'warning' ?>"><?= html_escape(ucfirst((string) $user['status'])) ?></span></td>
                    <td><?= html_escape((string) $user['created_at']) ?></td>
                    <td><form method="post">
                        <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                        <input type="hidden" name="action" value="toggle_status">
                        <button class="btn btn-sm <?= $user['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit"><?= $user['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                    </form></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
