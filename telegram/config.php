<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GoferBet Telegram Configuration
|--------------------------------------------------------------------------
*/

$telegramSecretsFile = 'C:\\xampp\\goferbet-secrets\\telegram.php';

if (!file_exists($telegramSecretsFile)) {
    die('Telegram configuration file is missing.');
}

$telegram = require $telegramSecretsFile;

if (!is_array($telegram)) {
    die('Invalid Telegram configuration.');
}

if (empty($telegram['bot_token'])) {
    die('Telegram bot token is not configured.');
}

if (empty($telegram['admin_chat_id'])) {
    die('Telegram admin chat ID is not configured.');
}

if (!isset($telegram['mini_app_url'])) {
    $telegram['mini_app_url'] = '';
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Telegram API helper
|--------------------------------------------------------------------------
*/

function telegram_api(string $method, array $data = []): array
{
    global $telegram;

    $url = 'https://api.telegram.org/bot'
        . $telegram['bot_token']
        . '/'
        . $method;

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'ok' => false,
            'error' => $error,
        ];
    }

    curl_close($ch);

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'error' => 'Invalid Telegram API response.',
        ];
    }

    return $decoded;
}

/*
|--------------------------------------------------------------------------
| Send Telegram message
|--------------------------------------------------------------------------
*/

function telegram_send_message(
    string|int $chatId,
    string $text,
    ?array $keyboard = null
): array {

    $data = [
        'chat_id' => (string) $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
    ];

    if ($keyboard !== null) {
        $data['reply_markup'] = json_encode(
            $keyboard,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    return telegram_api('sendMessage', $data);
}

/*
|--------------------------------------------------------------------------
| Answer callback query
|--------------------------------------------------------------------------
*/

function telegram_answer_callback(
    string $callbackQueryId,
    string $text = ''
): array {

    return telegram_api('answerCallbackQuery', [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => false,
    ]);
}