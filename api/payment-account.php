<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

try {

    start_app_session();

    /*
    |--------------------------------------------------------------------------
    | Check login
    |--------------------------------------------------------------------------
    */

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'You are not logged in. Please sign in again.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Get deposit amount
    |--------------------------------------------------------------------------
    */

    $amount = filter_input(
        INPUT_GET,
        'amount',
        FILTER_VALIDATE_FLOAT
    );

    if ($amount === false || $amount === null || $amount <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid deposit amount.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Connect to database
    |--------------------------------------------------------------------------
    */

    $db = database();

    /*
    |--------------------------------------------------------------------------
    | Find active GoferBet payment account
    |--------------------------------------------------------------------------
    */

    $stmt = $db->query("
        SELECT
            id,
            provider,
            account_name,
            account_number,
            currency
        FROM payment_accounts
        WHERE status = 'active'
        ORDER BY id ASC
        LIMIT 1
    ");

    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | No payment account
    |--------------------------------------------------------------------------
    */

    if (!$account) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'No active payment account is currently available.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Return payment account
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'message' => 'Payment account loaded successfully.',
        'payment_account' => [
            'id' => (int) $account['id'],
            'provider' => $account['provider'],
            'provider_name' => $account['provider'],
            'account_name' => $account['account_name'],
            'account_number' => $account['account_number'],
            'currency' => $account['currency']
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Server error
    |--------------------------------------------------------------------------
    */

    error_log(
        'payment-account.php error: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load the payment account.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

    exit;
}