<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/commands.php';


/*
|--------------------------------------------------------------------------
| Get Telegram Update
|--------------------------------------------------------------------------
*/

$rawUpdate = file_get_contents('php://input');

if ($rawUpdate === false || trim($rawUpdate) === '') {
    exit('OK');
}

$update = json_decode($rawUpdate, true);

if (!is_array($update)) {
    exit('OK');
}


/*
|--------------------------------------------------------------------------
| Normal Message
|--------------------------------------------------------------------------
*/

if (isset($update['message'])) {

    $message = $update['message'];

    $chatId = $message['chat']['id'] ?? null;
    $text   = trim($message['text'] ?? '');

    if (!$chatId) {
        exit('OK');
    }

    /*
     * /start
     */
    if ($text === '/start' || str_starts_with($text, '/start ')) {

        telegram_send_start($chatId);

        exit('OK');
    }

    /*
     * Unknown message
     */
    telegram_send_message(
        $chatId,
        "Please choose an option from the menu.",
        telegram_main_menu()
    );

    exit('OK');
}


/*
|--------------------------------------------------------------------------
| Button Callback
|--------------------------------------------------------------------------
*/

if (isset($update['callback_query'])) {

    $callback = $update['callback_query'];

    $callbackId = $callback['id'] ?? '';
    $data       = $callback['data'] ?? '';
    $chatId     = $callback['message']['chat']['id'] ?? null;

    if (!$chatId) {
        exit('OK');
    }

    telegram_answer_callback($callbackId);

    switch ($data) {

        case 'balance':
            telegram_handle_balance($chatId);
            break;

        case 'deposit':
            telegram_handle_deposit($chatId);
            break;

        case 'withdraw':
            telegram_handle_withdraw($chatId);
            break;

        case 'profile':
            telegram_handle_profile($chatId);
            break;

        default:
            telegram_send_message(
                $chatId,
                "Unknown option.",
                telegram_main_menu()
            );
            break;
    }

    exit('OK');
}


exit('OK');