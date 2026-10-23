<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/telegram_bot.php';

if (!getenv('TELEGRAM_BOT_TOKEN')) {
    fwrite(STDERR, "TELEGRAM_BOT_TOKEN is not set. Start this script with telegram/run_bot.ps1.\n");
    exit(1);
}

if (telegram_admin_ids() === []) {
    fwrite(STDERR, "TELEGRAM_ADMIN_IDS must contain your numeric Telegram user ID.\n");
    exit(1);
}

try {
    $webhookInfo = telegram_bot_api('getWebhookInfo', []);
    $activeWebhook = $webhookInfo['result']['url'] ?? '';
    if (is_string($activeWebhook) && $activeWebhook !== '') {
        fwrite(STDERR, "This bot has a webhook configured. Remove it before using local polling, or use the hosted webhook instead.\n");
        exit(1);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Could not verify the Telegram bot connection. Check token/network configuration.\n");
    exit(1);
}

$offset = 0;
fwrite(STDOUT, "GoferBet Telegram support bot is polling. Press Ctrl+C to stop.\n");

while (true) {
    try {
        $response = telegram_bot_api('getUpdates', [
            'offset' => $offset,
            'timeout' => 25,
            'allowed_updates' => ['message'],
        ]);

        foreach ($response['result'] ?? [] as $update) {
            if (!is_array($update)) {
                continue;
            }

            $updateId = $update['update_id'] ?? null;
            if (is_int($updateId) && $updateId >= $offset) {
                $offset = $updateId + 1;
            }

            $message = $update['message'] ?? null;
            if (is_array($message)) {
                try {
                    telegram_process_message($message);
                } catch (Throwable $exception) {
                    error_log(sprintf(
                        'Telegram polling message failed [%s]: %s',
                        (string) $exception->getCode(),
                        $exception->getMessage()
                    ));
                    fwrite(STDERR, "A message could not be processed; details were written to the PHP error log.\n");
                }
            }
        }
    } catch (Throwable $exception) {
        error_log(sprintf(
            'Telegram polling request failed [%s]: %s',
            (string) $exception->getCode(),
            $exception->getMessage()
        ));
        fwrite(STDERR, "Telegram connection failed; retrying in five seconds.\n");
        sleep(5);
    }
}