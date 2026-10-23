<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();

function json_response(
    bool $success,
    string $message = '',
    array $data = [],
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(
            false,
            'Invalid request method.',
            [],
            405
        );
    }

    if (!isset($_SESSION['user_id'])) {
        json_response(
            false,
            'Your session has expired. Please sign in again.',
            [],
            401
        );
    }

    $userId = (int) $_SESSION['user_id'];

    $amount = filter_input(
        INPUT_POST,
        'amount',
        FILTER_VALIDATE_FLOAT
    );

    $paymentAccountId = filter_input(
        INPUT_POST,
        'payment_account_id',
        FILTER_VALIDATE_INT
    );

    $provider = trim(
        (string) ($_POST['provider'] ?? '')
    );

    $receiptUrl = trim(
        (string) ($_POST['receipt_url'] ?? '')
    );

    $transactionReference = trim(
        (string) ($_POST['transaction_reference'] ?? '')
    );

    if ($amount === false || $amount === null || $amount <= 0) {
        json_response(
            false,
            'Invalid deposit amount.',
            [],
            400
        );
    }

    if ($paymentAccountId === false || $paymentAccountId <= 0) {
        json_response(
            false,
            'Invalid payment account.',
            [],
            400
        );
    }

    if ($provider === '') {
        json_response(
            false,
            'Payment provider is required.',
            [],
            400
        );
    }

    if ($receiptUrl === '') {
        json_response(
            false,
            'Payment receipt link is required.',
            [],
            400
        );
    }

    if (!filter_var($receiptUrl, FILTER_VALIDATE_URL)) {
        json_response(
            false,
            'Please enter a valid receipt URL.',
            [],
            400
        );
    }

    if (strlen($receiptUrl) > 1000) {
        json_response(
            false,
            'Receipt URL is too long.',
            [],
            400
        );
    }

    $db = database();

    /*
     * Confirm that the selected payment account
     * still exists and is active.
     */
    $stmt = $db->prepare("
        SELECT
            id,
            provider,
            account_name,
            account_number,
            currency
        FROM payment_accounts
        WHERE id = ?
          AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        $paymentAccountId
    ]);

    $paymentAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paymentAccount) {
        json_response(
            false,
            'The selected payment account is no longer available.',
            [],
            404
        );
    }

    /*
     * Prevent the same receipt URL from being
     * submitted repeatedly.
     */
    $stmt = $db->prepare("
        SELECT id
        FROM deposit_requests
        WHERE receipt_url = ?
        LIMIT 1
    ");

    $stmt->execute([
        $receiptUrl
    ]);

    if ($stmt->fetch()) {
        json_response(
            false,
            'This receipt has already been submitted.',
            [],
            409
        );
    }

    /*
     * Create a pending deposit request.
     *
     * The wallet balance is NOT increased here.
     */
    $stmt = $db->prepare("
        INSERT INTO deposit_requests
        (
            user_id,
            payment_account_id,
            provider,
            amount,
            currency,
            receipt_url,
            transaction_reference,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'pending'
        )
    ");

    $stmt->execute([
        $userId,
        $paymentAccountId,
        $provider,
        number_format($amount, 2, '.', ''),
        $paymentAccount['currency'],
        $receiptUrl,
        $transactionReference !== ''
            ? $transactionReference
            : null
    ]);

    $depositId = (int) $db->lastInsertId();

    json_response(
        true,
        'Your payment receipt has been submitted and is pending verification.',
        [
            'status' => 'pending',
            'deposit_id' => $depositId
        ]
    );

} catch (Throwable $e) {

    error_log(
        'deposit-verify.php: ' . $e->getMessage()
    );

    json_response(
        false,
        'Unable to submit the deposit verification request.',
        [],
        500
    );
}