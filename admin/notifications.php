<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/admin_auth.php';

$adminId = require_active_admin();
$db = database();
$error = '';
$success = '';
$title = '';
$message = '';
$recipientInput = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $titleInput = $_POST['title'] ?? '';
    $messageInput = $_POST['message'] ?? '';
    $recipientPost = $_POST['recipient_user_id'] ?? '';
    $title = is_string($titleInput) ? trim($titleInput) : '';
    $message = is_string($messageInput) ? trim($messageInput) : '';
    $recipientInput = is_string($recipientPost) ? trim($recipientPost) : '';

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Reload the page and try again.';
    } elseif (!is_string($recipientPost)) {
        $error = 'Enter a user ID or leave the recipient field blank to notify everyone.';
    } elseif ($title === '' || mb_strlen($title) > 120) {
        $error = 'Enter a title of 1–120 characters.';
    } elseif ($message === '' || mb_strlen($message) > 5000) {
        $error = 'Enter a message of 1–5,000 characters.';
    } else {
        $recipientId = null;
        try {
            if ($recipientInput !== '') {
                $recipientId = filter_var($recipientInput, FILTER_VALIDATE_INT);
                if ($recipientId === false || $recipientId < 1) {
                    $error = 'Enter a valid user ID or leave it blank to notify everyone.';
                } else {
                    $userQuery = $db->prepare("SELECT id FROM users WHERE id = ? AND status = 'active' LIMIT 1");
                    $userQuery->execute([$recipientId]);
                    if ($userQuery->fetchColumn() === false) {
                        $error = 'No active user exists with that ID.';
                    }
                }
            }

            if ($error === '') {
                $insert = $db->prepare("
                    INSERT INTO user_notifications (recipient_user_id, created_by_admin_id, title, message)
                    VALUES (?, ?, ?, ?)
                ");
                $insert->execute([$recipientId, $adminId, $title, $message]);
                $success = $recipientId === null
                    ? 'Announcement sent to all users.'
                    : 'Message sent to user #' . $recipientId . '.';
                $title = '';
                $message = '';
                $recipientInput = '';
            }
        } catch (PDOException $exception) {
            error_log(sprintf('Admin notification send failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
            $error = 'The notification could not be sent. Please try again.';
        }
    }
}

$recentQuery = $db->query("
    SELECT n.id, n.recipient_user_id, n.title, n.message, n.created_at, u.username
    FROM user_notifications n
    LEFT JOIN users u ON u.id = n.recipient_user_id
    ORDER BY n.created_at DESC, n.id DESC
    LIMIT 50
");
$recentNotifications = $recentQuery->fetchAll(PDO::FETCH_ASSOC);

$adminRoot = '';
$adminTitle = 'Notifications';
$adminActive = 'notifications';
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="data-panel mb-4">
    <div class="data-panel-header"><h2>Send announcement</h2></div>
    <form method="post" class="p-4">
        <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= html_escape($success) ?></div><?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="notification-title">Title</label>
            <input class="form-control" id="notification-title" name="title" maxlength="120" value="<?= html_escape($title) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="notification-message">Message</label>
            <textarea class="form-control" id="notification-message" name="message" rows="5" maxlength="5000" required><?= html_escape($message) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label" for="notification-recipient">Specific user ID <span class="muted">(optional)</span></label>
            <input class="form-control" id="notification-recipient" name="recipient_user_id" type="number" min="1" step="1" value="<?= html_escape($recipientInput) ?>" aria-describedby="notification-recipient-help">
            <div class="form-text" id="notification-recipient-help">Leave blank to send to every user. Enter an ID to message one active user.</div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Send notification</button>
    </form>
</section>
<section class="data-panel">
    <div class="data-panel-header"><h2>Recent messages</h2></div>
    <?php if ($recentNotifications === []): ?>
        <div class="p-4 muted">No announcements have been sent yet.</div>
    <?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Title</th><th>Recipient</th><th>Message</th><th>Sent</th></tr></thead>
            <tbody><?php foreach ($recentNotifications as $notification): ?>
                <tr>
                    <td><?= html_escape((string) $notification['title']) ?></td>
                    <td><?= $notification['recipient_user_id'] === null ? 'All users' : html_escape((string) $notification['username']) . ' (#' . (int) $notification['recipient_user_id'] . ')' ?></td>
                    <td><?= nl2br(html_escape((string) $notification['message'])) ?></td>
                    <td><?= html_escape((string) $notification['created_at']) ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
