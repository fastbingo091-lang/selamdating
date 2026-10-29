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
$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Reload the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'mark_all_read') {
        $markAll = database()->prepare("
            INSERT IGNORE INTO user_notification_reads (notification_id, user_id)
            SELECT n.id, ?
            FROM user_notifications n
            WHERE n.recipient_user_id IS NULL OR n.recipient_user_id = ?
        ");
        $markAll->execute([$userId, $userId]);
        $message = 'All notifications marked as read.';
    } elseif (($_POST['action'] ?? '') === 'mark_read') {
        $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
        if ($notificationId === false || $notificationId === null || $notificationId < 1) {
            $error = 'Choose a valid notification.';
        } else {
            $visibleNotification = database()->prepare("
                SELECT id
                FROM user_notifications
                WHERE id = ? AND (recipient_user_id IS NULL OR recipient_user_id = ?)
                LIMIT 1
            ");
            $visibleNotification->execute([$notificationId, $userId]);
            if ($visibleNotification->fetchColumn() === false) {
                $error = 'Notification not found.';
            } else {
                $markOne = database()->prepare("
                    INSERT IGNORE INTO user_notification_reads (notification_id, user_id)
                    VALUES (?, ?)
                ");
                $markOne->execute([$notificationId, $userId]);
                $message = 'Notification marked as read.';
            }
        }
    } else {
        $error = 'That notification action is not available.';
    }
}

$notificationsQuery = database()->prepare("
    SELECT n.id, n.title, n.message, n.created_at, a.username AS sender,
           r.notification_id IS NOT NULL AS is_read
    FROM user_notifications n
    LEFT JOIN admins a ON a.id = n.created_by_admin_id
    LEFT JOIN user_notification_reads r
        ON r.notification_id = n.id AND r.user_id = ?
    WHERE n.recipient_user_id IS NULL OR n.recipient_user_id = ?
    ORDER BY n.created_at DESC, n.id DESC
    LIMIT 100
");
$notificationsQuery->execute([$userId, $userId]);
$notifications = $notificationsQuery->fetchAll(PDO::FETCH_ASSOC);

$appRoot = '';
$pageTitle = 'Notifications';
$pageDescription = 'Announcements and messages from Selam administrators.';
$activeNav = 'notifications';
require __DIR__ . '/includes/app_header.php';
?>
<section class="data-panel">
    <div class="data-panel-header">
        <h2>Inbox</h2>
        <?php if ($notifications !== []): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button class="btn btn-sm btn-outline-light" type="submit">Mark all read</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="p-3">
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
        <?php if ($message !== ''): ?><div class="alert alert-success" role="status"><?= html_escape($message) ?></div><?php endif; ?>
        <?php if ($notifications === []): ?>
            <p class="muted mb-0">You have no notifications yet.</p>
        <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <article class="notification-card<?= (bool) $notification['is_read'] ? '' : ' is-unread' ?>">
                        <div class="notification-card-heading">
                            <h3><?= html_escape((string) $notification['title']) ?></h3>
                            <?php if (!(bool) $notification['is_read']): ?><span class="notification-unread-label">New</span><?php endif; ?>
                        </div>
                        <p><?= nl2br(html_escape((string) $notification['message'])) ?></p>
                        <div class="notification-meta">
                            <span><?= html_escape((string) ($notification['sender'] ?? 'Selam Admin')) ?></span>
                            <time><?= html_escape((string) $notification['created_at']) ?></time>
                        </div>
                        <?php if (!(bool) $notification['is_read']): ?>
                            <form method="post" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                                <button class="btn btn-sm btn-outline-light" type="submit">Mark as read</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/app_footer.php'; ?>
