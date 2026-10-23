<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/telegram_bot.php';

$webhookUrl = getenv('TELEGRAM_WEBHOOK_URL');
$secret = getenv('TELEGRAM_WEBHOOK_SECRET');
$urlParts = is_string($webhookUrl) ? parse_url($webhookUrl) : false;

if (!is_string($webhookUrl) || filter_var($webhookUrl, FILTER_VALIDATE_URL) === false
    || !is_array($urlParts) || strtolower((string) ($urlParts['scheme'] ?? '')) !== 'https'
) {
    fwrite(STDERR, "Set TELEGRAM_WEBHOOK_URL to the public HTTPS URL ending in /telegram/webhook.php.\n");
    exit(1);
}

if (!is_string($secret) || strlen($secret) < 32 || strlen($secret) > 256
    || preg_match('/\A[A-Za-z0-9_-]+\z/', $secret) !== 1
) {
    fwrite(STDERR, "Set TELEGRAM_WEBHOOK_SECRET to a random 32-256 character alphanumeric value.\n");
    exit(1);
}

try {
    $result = telegram_bot_api('setWebhook', [
        'url' => $webhookUrl,
        'secret_token' => $secret,
        'allowed_updates' => ['message'],
        'drop_pending_updates' => false,
    ]);
    echo ($result['result'] ?? false) === true
        ? "Telegram webhook registered successfully.\n"
        : "Telegram did not confirm webhook registration.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Webhook registration failed. Check the local error log and Telegram configuration.\n");
    exit(1);
}