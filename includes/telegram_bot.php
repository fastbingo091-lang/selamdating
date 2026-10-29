<?php
declare(strict_types=1);

function telegram_admin_ids(): array
{
    $configuredIds = getenv('TELEGRAM_ADMIN_IDS');
    if ($configuredIds === false || trim($configuredIds) === '') {
        return [];
    }

    $ids = preg_split('/\s*,\s*/', trim($configuredIds), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    return array_values(array_filter(
        $ids,
        static fn (string $id): bool => preg_match('/\A[1-9][0-9]{0,19}\z/', $id) === 1
    ));
}

function telegram_bot_api(string $method, array $payload): array
{
    $token = getenv('TELEGRAM_BOT_TOKEN');
    if ($token === false || $token === '') {
        throw new RuntimeException('Telegram bot is not configured.');
    }

    if (!preg_match('/\A[a-zA-Z][a-zA-Z0-9]{1,63}\z/', $method)) {
        throw new RuntimeException('Invalid Telegram API method.');
    }

    $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $curl = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
    if ($curl === false) {
        throw new RuntimeException('Telegram API client could not start.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $method === 'getUpdates' ? 35 : 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $responseBody = curl_exec($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if (!is_string($responseBody) || $httpStatus < 200 || $httpStatus >= 300) {
        $safeError = $curlError === '' ? 'HTTP ' . $httpStatus : str_replace($token, '[redacted]', $curlError);
        error_log(sprintf('Telegram API %s failed: %s', $method, $safeError));
        throw new RuntimeException('Telegram API request failed.');
    }

    $result = json_decode($responseBody, true);
    if (!is_array($result) || ($result['ok'] ?? false) !== true) {
        error_log(sprintf('Telegram API %s returned an unsuccessful response.', $method));
        throw new RuntimeException('Telegram API request failed.');
    }

    return $result;
}

function telegram_send_text(string $chatId, string $text): void
{
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($length < 1 || $length > 4096) {
        throw new RuntimeException('Telegram message length is invalid.');
    }

    telegram_bot_api('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ]);
}

function telegram_linked_user(string $telegramUserId): ?array
{
    $query = database()->prepare(
        'SELECT id, username, status FROM users WHERE telegram_user_id = :telegram_user_id LIMIT 1'
    );
    $query->execute(['telegram_user_id' => $telegramUserId]);
    $user = $query->fetch();

    return is_array($user) ? $user : null;
}

function telegram_process_message(array $message): void
{
    $chat = $message['chat'] ?? null;
    $sender = $message['from'] ?? null;
    $text = $message['text'] ?? null;

    if (!is_array($chat) || !is_array($sender) || !is_string($text)) {
        return;
    }

    $chatId = $chat['id'] ?? null;
    $senderId = $sender['id'] ?? null;
    if ((!is_int($chatId) && !is_string($chatId))
        || !preg_match('/\A-?[0-9]{1,20}\z/', (string) $chatId)
        || (!is_int($senderId) && !is_string($senderId))
        || !preg_match('/\A[1-9][0-9]{0,19}\z/', (string) $senderId)
    ) {
        return;
    }

    $chatId = (string) $chatId;
    $senderId = (string) $senderId;
    $text = trim($text);
    if ($text === '') {
        return;
    }

    $isAdmin = in_array($senderId, telegram_admin_ids(), true);
    if (preg_match('/\A\/([a-zA-Z0-9_]+)(?:@[a-zA-Z0-9_]+)?(?:\s+([\s\S]*))?\z/u', $text, $matches) === 1) {
        $command = strtolower($matches[1]);
        $arguments = trim($matches[2] ?? '');

        if ($command === 'reply') {
            if (!$isAdmin) {
                telegram_send_text($chatId, 'This command is available to support staff only.');
                return;
            }

            if (preg_match('/\A([1-9][0-9]{0,19})\s+([\s\S]{1,3900})\z/u', $arguments, $reply) !== 1) {
                telegram_send_text($chatId, 'Use: /reply <chat_id> <message>');
                return;
            }

            telegram_send_text($reply[1], trim($reply[2]));
            telegram_send_text($chatId, 'Reply sent.');
            return;
        }

        if ($isAdmin) {
            telegram_send_text($chatId, 'Support console ready. Reply to a user with /reply <chat_id> <message>.');
            return;
        }

        if ($command === 'start' || $command === 'help') {
            telegram_send_text(
                $chatId,
                "Welcome to Selam Market. Use the app to manage your ETB wallet and product portfolio.\n\n"
                . "Commands:\n/balance - view your wallet balance\n/profile - view your linked account\n/help - show this message\n\n"
                . "Send a message here to contact support."
            );
            return;
        }

        if ($command === 'balance' || $command === 'profile') {
            try {
                $user = telegram_linked_user($senderId);
            } catch (PDOException $exception) {
                error_log(sprintf('Telegram linked-user lookup failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
                telegram_send_text($chatId, 'Selam is temporarily unable to load your account. Please try again later.');
                return;
            }

            if ($user === null) {
                telegram_send_text($chatId, 'Your Telegram account is not linked to Selam. Open the official Mini App and register or link your account first.');
                return;
            }

            if ($user['status'] !== 'active') {
                telegram_send_text($chatId, 'Your Selam account is inactive. Please contact support.');
                return;
            }

            if ($command === 'profile') {
                telegram_send_text($chatId, 'Your linked Selam username is ' . (string) $user['username'] . '.');
                return;
            }

            try {
                $balanceQuery = database()->prepare(
                    "SELECT balance FROM wallets WHERE user_id = :user_id ORDER BY CASE WHEN currency = 'ETB' THEN 0 ELSE 1 END, id LIMIT 1"
                );
                $balanceQuery->execute([
                    'user_id' => (int) $user['id'],
                ]);
                $balance = $balanceQuery->fetchColumn();
            } catch (PDOException $exception) {
                error_log(sprintf('Telegram balance lookup failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
                telegram_send_text($chatId, 'Selam is temporarily unable to load your balance. Please try again later.');
                return;
            }

            telegram_send_text($chatId, 'ETB wallet balance: ' . ($balance === false ? '0.00' : (string) $balance));
            return;
        }

        telegram_send_text($chatId, 'I do not recognize that command. Send /help for available commands.');
        return;
    }

    if ($isAdmin) {
        telegram_send_text($chatId, 'Use /reply <chat_id> <message> to reply to a user.');
        return;
    }

    $adminIds = telegram_admin_ids();
    if ($adminIds === []) {
        telegram_send_text($chatId, 'Support chat is not configured yet. Please try again later.');
        return;
    }

    $senderName = trim((string) ($sender['first_name'] ?? 'Telegram user'));
    $senderUsername = $sender['username'] ?? null;
    $senderLabel = is_string($senderUsername) && $senderUsername !== ''
        ? '@' . $senderUsername
        : $senderName;
    $supportMessage = "Selam support message\nFrom: " . $senderLabel . "\nChat ID: " . $chatId . "\n\n" . $text;

    foreach ($adminIds as $adminId) {
        telegram_send_text($adminId, $supportMessage);
    }
    telegram_send_text($chatId, 'Your message was sent to Selam support. Reply here to continue the conversation.');
}