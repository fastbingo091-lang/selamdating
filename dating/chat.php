<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/dating.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$db = database();
$error = '';
$formMessage = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        if (($_POST['action'] ?? '') === 'start_chat') {
            http_response_code(403);
            exit('Your form expired. Reload Discover and try again.');
        }
        $error = 'Your form expired. Reload the page and try again.';
    } else {
        $actionInput = $_POST['action'] ?? '';
        $action = is_string($actionInput) ? $actionInput : '';

        if ($action === 'start_chat') {
            $targetInput = $_POST['user_id'] ?? '';
            $targetUserId = is_string($targetInput) || is_int($targetInput)
                ? filter_var($targetInput, FILTER_VALIDATE_INT)
                : false;
            if (!is_int($targetUserId) || !dating_profiles_are_mutual_matches($db, $userId, $targetUserId)) {
                http_response_code(404);
                exit('This profile is not available for chat.');
            }

            try {
                $lowUserId = min($userId, $targetUserId);
                $highUserId = max($userId, $targetUserId);
                $findConversation = $db->prepare(
                    'SELECT id FROM dating_conversations WHERE user_low_id = ? AND user_high_id = ? LIMIT 1'
                );
                $findConversation->execute([$lowUserId, $highUserId]);
                $conversationId = $findConversation->fetchColumn();
                if ($conversationId === false) {
                    try {
                        $createConversation = $db->prepare(
                            'INSERT INTO dating_conversations (user_low_id, user_high_id) VALUES (?, ?)'
                        );
                        $createConversation->execute([$lowUserId, $highUserId]);
                        $conversationId = $db->lastInsertId();
                    } catch (PDOException $exception) {
                        if ($exception->getCode() !== '23000') {
                            throw $exception;
                        }
                        $findConversation->execute([$lowUserId, $highUserId]);
                        $conversationId = $findConversation->fetchColumn();
                        if ($conversationId === false) {
                            throw $exception;
                        }
                    }
                }
                header('Location: chat.php?id=' . (int) $conversationId);
                exit;
            } catch (PDOException $exception) {
                error_log(sprintf('Selam chat start failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
                http_response_code(503);
                exit('We could not open this chat right now. Please try again.');
            }
        }

        if ($action === 'send_message') {
            $conversationInput = $_POST['conversation_id'] ?? '';
            $conversationId = is_string($conversationInput) || is_int($conversationInput)
                ? filter_var($conversationInput, FILTER_VALIDATE_INT)
                : false;
            $messageInput = $_POST['message'] ?? '';
            $formMessage = is_string($messageInput) ? trim($messageInput) : '';

            if (!is_int($conversationId) || $conversationId < 1 || $formMessage === '' || mb_strlen($formMessage) > 2000) {
                $error = 'Enter a message between 1 and 2,000 characters.';
            } else {
                try {
                    $db->beginTransaction();
                    $lockConversation = $db->prepare(
                        'SELECT id FROM dating_conversations WHERE id = ? AND (user_low_id = ? OR user_high_id = ?) FOR UPDATE'
                    );
                    $lockConversation->execute([$conversationId, $userId, $userId]);
                    if ($lockConversation->fetchColumn() === false) {
                        $db->rollBack();
                        http_response_code(404);
                        exit('Conversation not found.');
                    }

                    $premium = dating_has_active_membership($db, $userId);
                    if (!$premium) {
                        $sentCount = $db->prepare(
                            'SELECT COUNT(*) FROM dating_messages WHERE conversation_id = ? AND sender_id = ?'
                        );
                        $sentCount->execute([$conversationId, $userId]);
                        if ((int) $sentCount->fetchColumn() >= 1) {
                            $db->rollBack();
                            $error = 'Your free message in this chat has been used. Get a Selam membership to keep the conversation going.';
                        }
                    }

                    if ($error === '') {
                        $insertMessage = $db->prepare(
                            'INSERT INTO dating_messages (conversation_id, sender_id, message) VALUES (?, ?, ?)'
                        );
                        $insertMessage->execute([$conversationId, $userId, $formMessage]);
                        $updateConversation = $db->prepare(
                            'UPDATE dating_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?'
                        );
                        $updateConversation->execute([$conversationId]);
                        $db->commit();
                        header('Location: chat.php?id=' . $conversationId);
                        exit;
                    }
                    $db->commit();
                } catch (PDOException $exception) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log(sprintf('Selam message send failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
                    $error = 'We could not send your message right now. Please try again.';
                }
            }
            if (is_int($conversationId) && $conversationId > 0) {
                $_GET['id'] = (string) $conversationId;
            }
        }
    }
}

$conversationInput = $_GET['id'] ?? '';
$conversationId = is_string($conversationInput) || is_int($conversationInput)
    ? filter_var($conversationInput, FILTER_VALIDATE_INT)
    : false;
if (!is_int($conversationId) || $conversationId < 1) {
    http_response_code(404);
    exit('Conversation not found.');
}

try {
    $conversationQuery = $db->prepare("
        SELECT conversation.id, conversation.user_low_id, conversation.user_high_id,
            profile.user_id AS other_user_id, profile.display_name, profile.gender,
            profile.telegram_username
        FROM dating_conversations conversation
        INNER JOIN users other_user ON other_user.id = IF(conversation.user_low_id = :current_user_low, conversation.user_high_id, conversation.user_low_id)
            AND other_user.status = 'active'
        INNER JOIN dating_profiles profile ON profile.user_id = other_user.id
        WHERE conversation.id = :conversation_id
          AND (conversation.user_low_id = :current_user_filter OR conversation.user_high_id = :current_user_high)
        LIMIT 1
    ");
    $conversationQuery->execute([
        'current_user_low' => $userId,
        'conversation_id' => $conversationId,
        'current_user_filter' => $userId,
        'current_user_high' => $userId,
    ]);
    $conversation = $conversationQuery->fetch(PDO::FETCH_ASSOC);
    if (!$conversation || !dating_user_is_conversation_member($conversation, $userId)) {
        http_response_code(404);
        exit('Conversation not found.');
    }

    $messageQuery = $db->prepare("
        SELECT message.id, message.sender_id, message.message, message.created_at, sender.username
        FROM dating_messages message
        INNER JOIN users sender ON sender.id = message.sender_id
        WHERE message.conversation_id = ?
        ORDER BY message.created_at ASC, message.id ASC
    ");
    $messageQuery->execute([$conversationId]);
    $messages = $messageQuery->fetchAll(PDO::FETCH_ASSOC);
    $premium = dating_has_active_membership($db, $userId);
    $sentCount = 0;
    foreach ($messages as $message) {
        if ((int) $message['sender_id'] === $userId) {
            $sentCount++;
        }
    }
} catch (PDOException $exception) {
    error_log(sprintf('Selam chat read failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
    http_response_code(503);
    exit('Your chat is temporarily unavailable. Please try again.');
}

$pageTitle = 'Chat with ' . (string) $conversation['display_name'];
$pageDescription = 'A private conversation on Selam.';
$activeNav = 'chats';
$appRoot = '../';
require __DIR__ . '/../includes/app_header.php';
?>

<section class="dating-chat-room">
    <header class="dating-chat-room-heading">
        <a class="btn btn-outline-secondary" href="chats.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> All chats</a>
        <div><span class="eyebrow">PRIVATE CONVERSATION</span><h2><?= html_escape((string) $conversation['display_name']) ?></h2></div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="alert alert-warning" role="alert">
            <?= html_escape($error) ?>
            <?php if (!$premium): ?> <a href="../products.php">View Selam memberships</a><?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="dating-chat-messages" aria-live="polite">
        <?php if ($messages === []): ?>
            <p class="dating-chat-empty">Say hello and start the conversation.</p>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <article class="dating-message<?= (int) $message['sender_id'] === $userId ? ' own' : '' ?>">
                    <p><?= nl2br(html_escape((string) $message['message'])) ?></p>
                    <time><?= html_escape((string) $message['created_at']) ?></time>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($conversation['telegram_username'] !== null && $conversation['telegram_username'] !== ''): ?>
        <aside class="dating-telegram-card">
            <i class="bi bi-telegram" aria-hidden="true"></i>
            <?php if ($premium): ?>
                <span>Telegram: <a href="https://t.me/<?= rawurlencode((string) $conversation['telegram_username']) ?>" target="_blank" rel="noopener noreferrer">@<?= html_escape((string) $conversation['telegram_username']) ?></a></span>
            <?php else: ?>
                <span>Telegram username hidden · <a href="../products.php">A membership is required to reveal it</a>.</span>
            <?php endif; ?>
        </aside>
    <?php endif; ?>

    <?php if (!$premium && $sentCount >= 1): ?>
        <div class="dating-premium-prompt">
            <i class="bi bi-gem" aria-hidden="true"></i>
            <div><strong>Your free message has been used</strong><span>Get a Selam membership to send more messages in this chat and reveal members’ Telegram usernames.</span></div>
            <a class="btn btn-primary" href="../products.php">Explore memberships</a>
        </div>
    <?php else: ?>
        <form method="post" class="dating-chat-composer">
            <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
            <input type="hidden" name="action" value="send_message">
            <input type="hidden" name="conversation_id" value="<?= $conversationId ?>">
            <label class="visually-hidden" for="message">Your message</label>
            <textarea class="form-control" id="message" name="message" rows="3" maxlength="2000" placeholder="Write a kind hello…" required><?= html_escape($formMessage) ?></textarea>
            <button class="btn btn-primary" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Send</button>
            <?php if (!$premium): ?><small>You can send one free message in this chat. A membership unlocks more.</small><?php endif; ?>
        </form>
    <?php endif; ?>
</section>

<style>
.dating-chat-room{display:grid;gap:16px;max-width:900px;margin:auto}.dating-chat-room-heading{display:flex;align-items:center;gap:16px}.dating-chat-room-heading h2{margin:4px 0;color:#382a32;font-size:21px;font-weight:750}.dating-chat-messages{display:flex;min-height:240px;max-height:55vh;flex-direction:column;gap:12px;overflow-y:auto;padding:18px;border:1px solid #efdee4;border-radius:18px;background:#fff}.dating-chat-empty{margin:auto;color:#796c74}.dating-message{max-width:78%;align-self:flex-start;padding:11px 14px;border-radius:16px 16px 16px 4px;background:#f7eef1}.dating-message.own{align-self:flex-end;border-radius:16px 16px 4px 16px;background:#fbe7ec}.dating-message p{margin:0;color:#382a32;white-space:normal;overflow-wrap:anywhere}.dating-message time{display:block;margin-top:6px;color:#90858b;font-size:10px;text-align:right}.dating-chat-composer{display:grid;grid-template-columns:1fr auto;gap:10px}.dating-chat-composer textarea{grid-column:1/-1}.dating-chat-composer small{grid-column:1/-1;color:#796c74}.dating-chat-composer button{justify-self:end}.dating-telegram-card,.dating-premium-prompt{display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid #efdee4;border-radius:15px;background:#fff}.dating-telegram-card>i,.dating-premium-prompt>i{color:#a94867;font-size:21px}.dating-telegram-card span{color:#655861;font-size:13px}.dating-premium-prompt>div{display:grid;flex:1;gap:3px}.dating-premium-prompt strong{color:#382a32}.dating-premium-prompt span{color:#796c74;font-size:12px}@media(max-width:600px){.dating-premium-prompt{align-items:flex-start;flex-wrap:wrap}.dating-premium-prompt .btn{width:100%}.dating-chat-room-heading{align-items:flex-start}.dating-chat-room-heading .btn{font-size:12px}}
</style>

<?php require __DIR__ . '/../includes/app_footer.php'; ?>
