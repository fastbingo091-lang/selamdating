<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_auth.php';

require_active_admin();

$logs = database()->query("
    SELECT l.id, l.action, l.description, l.ip_address, l.created_at,
        a.username AS admin_username, u.username AS user_username, l.user_id
    FROM activity_logs l
    LEFT JOIN admins a ON a.id = l.admin_id
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.id DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$adminTitle = 'Activity logs';
$adminActive = 'logs';
$adminRoot = './';
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="data-panel">
    <div class="data-panel-header"><div><h2>Administrative audit trail</h2><p class="muted mb-0">Recent account and wallet operations performed by users and agents.</p></div><span class="status-badge"><?= number_format(count($logs)) ?> recent</span></div>
    <?php if ($logs === []): ?>
        <div class="p-5 text-center muted">No audit entries have been recorded yet.</div>
    <?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Action</th><th>Agent</th><th>User</th><th>Description</th><th>IP address</th><th>Date</th></tr></thead>
            <tbody><?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= html_escape((string) $log['action']) ?></td>
                    <td><?= html_escape((string) ($log['admin_username'] ?? '—')) ?></td>
                    <td><?= $log['user_id'] === null ? '—' : html_escape((string) ($log['user_username'] ?? 'User #' . $log['user_id'])) ?></td>
                    <td><?= html_escape((string) ($log['description'] ?? '')) ?></td>
                    <td><?= html_escape((string) ($log['ip_address'] ?? '—')) ?></td>
                    <td><?= html_escape((string) $log['created_at']) ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
