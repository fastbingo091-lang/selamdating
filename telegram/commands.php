<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';


/*
|--------------------------------------------------------------------------
| Main Telegram Menu
|--------------------------------------------------------------------------
*/

function telegram_main_menu(): array
{
    global $telegram;

    return [
        'inline_keyboard' => [
            [
                [
                    'text' => '💰 Balance',
                    'callback_data' => 'balance'
                ]
            ],
            [
                [
                    'text' => '➕ Deposit',
                    'callback_data' => 'deposit'
                ],
                [
                    'text' => '➖ Withdraw',
                    'callback_data' => 'withdraw'
                ]
            ],
            [
                [
                    'text' => '🎮 PLAY',
                    'web_app' => [
                        'url' => $telegram['mini_app_url']
                    ]
                ]
            ],
            [
                [
                    'text' => '👤 Profile',
                    'callback_data' => 'profile'
                ]
            ]
        ]
    ];
}


/*
|--------------------------------------------------------------------------
| Send Start Menu
|--------------------------------------------------------------------------
*/

function telegram_send_start(string|int $chatId): array
{
    $message = <<<HTML
<b>ጎፈርቤት 👋</b>

Welcome to GoferBet.

Choose an option below:
HTML;

    return telegram_send_message(
        $chatId,
        $message,
        telegram_main_menu()
    );
}


/*
|--------------------------------------------------------------------------
| Balance
|--------------------------------------------------------------------------
*/

function telegram_handle_balance(string|int $chatId): array
{
    /*
     * Wallet database connection will be connected here next.
     */

    $message = <<<HTML
<b>💰 Balance</b>

Your current balance will appear here.
HTML;

    return telegram_send_message(
        $chatId,
        $message,
        telegram_main_menu()
    );
}


/*
|--------------------------------------------------------------------------
| Deposit
|--------------------------------------------------------------------------
*/

function telegram_handle_deposit(string|int $chatId): array
{
    $message = <<<HTML
<b>➕ Deposit</b>

Choose your deposit method from the GoferBet Mini App.
HTML;

    return telegram_send_message(
        $chatId,
        $message,
        telegram_main_menu()
    );
}


/*
|--------------------------------------------------------------------------
| Withdraw
|--------------------------------------------------------------------------
*/

function telegram_handle_withdraw(string|int $chatId): array
{
    $message = <<<HTML
<b>➖ Withdraw</b>

Withdrawal options will appear here.
HTML;

    return telegram_send_message(
        $chatId,
        $message,
        telegram_main_menu()
    );
}


/*
|--------------------------------------------------------------------------
| Profile
|--------------------------------------------------------------------------
*/

function telegram_handle_profile(string|int $chatId): array
{
    $message = <<<HTML
<b>👤 Profile</b>

Your GoferBet profile will appear here.
HTML;

    return telegram_send_message(
        $chatId,
        $message,
        telegram_main_menu()
    );
}