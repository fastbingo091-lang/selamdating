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
$conversationsQuery = $db->prepare("
    SELECT conversation.id, conversation.updated_at,
        profile.user_id AS other_user_id, profile.display_name, profile.gender,
        (SELECT message.message FROM dating_messages message
         WHERE message.conversation_id = conversation.id
         ORDER BY message.created_at DESC, message.id DESC LIMIT 1) AS last_message,
        (SELECT message.created_at FROM dating_messages message
         WHERE message.conversation_id = conversation.id
         ORDER BY message.created_at DESC, message.id DESC LIMIT 1) AS last_message_at
    FROM dating_conversations conversation
    INNER JOIN users other_user ON other_user.id = IF(conversation.user_low_id = :user_low, conversation.user_high_id, conversation.user_low_id)
        AND other_user.status = 'active'
    INNER JOIN dating_profiles profile ON profile.user_id = other_user.id
    WHERE conversation.user_low_id = :user_low_filter OR conversation.user_high_id = :user_high_filter
    ORDER BY COALESCE(last_message_at, conversation.updated_at) DESC, conversation.id DESC
");
$conversationsQuery->execute([
    'user_low' => $userId,
    'user_low_filter' => $userId,
    'user_high_filter' => $userId,
]);
$conversations = $conversationsQuery->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Your chats';
$pageDescription = 'Private conversations with people you have chosen.';
$activeNav = 'chats';
$appRoot = '../';
require __DIR__ . '/../includes/app_header.php';
?>

<section class="dating-chat-list">
    <?php if ($conversations === []): ?>
        <div class="dating-empty-state">
            <i class="bi bi-chat-heart" aria-hidden="true"></i>
            <h2>No conversations yet</h2>
            <p>When you find someone in Discover, choose “Say hello” to open a private chat.</p>
            <a class="btn btn-primary" href="discover.php">Go to Discover</a>
        </div>
    <?php else: ?>
        <div class="data-panel dating-conversations">
            <?php foreach ($conversations as $conversation): ?>
                <a class="dating-conversation-row" href="chat.php?id=<?= (int) $conversation['id'] ?>">
                    <span class="dating-chat-avatar"><i class="bi bi-person-heart" aria-hidden="true"></i></span>
                    <span class="dating-chat-summary">
                        <strong><?= html_escape((string) $conversation['display_name']) ?></strong>
                        <span><?= html_escape((string) ($conversation['last_message'] ?? 'Conversation started — say hello.')) ?></span>
                    </span>
                    <span class="dating-chat-date"><?= html_escape((string) ($conversation['last_message_at'] ?? $conversation['updated_at'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<style>
.dating-chat-list{max-width:900px;margin:auto}.dating-conversations{overflow:hidden;padding:0}.dating-conversation-row{display:flex;align-items:center;gap:14px;padding:16px;border-bottom:1px solid #f1e8eb;color:inherit;text-decoration:none}.dating-conversation-row:last-child{border-bottom:0}.dating-conversation-row:hover{background:#fff8f7}.dating-chat-avatar{display:grid;width:48px;height:48px;flex:0 0 48px;place-items:center;border-radius:50%;background:#fde8ec;color:#ae4967;font-size:21px}.dating-chat-summary{display:grid;min-width:0;flex:1;gap:4px}.dating-chat-summary strong{color:#382a32}.dating-chat-summary span{overflow:hidden;color:#796c74;font-size:13px;text-overflow:ellipsis;white-space:nowrap}.dating-chat-date{color:#90858b;font-size:11px}
</style>

<?php require __DIR__ . '/../includes/app_footer.php'; ?>
