<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond_wallet_request_details(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    respond_wallet_request_details(401, ['success' => false, 'message' => 'Sign in to view this request.']);
}

$kindInput = $_GET['kind'] ?? '';
$kind = is_string($kindInput) ? $kindInput : '';
$idInput = $_GET['id'] ?? null;
$id = is_string($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;

if (!in_array($kind, ['deposit', 'withdrawal'], true) || $id === false || $id === null || $id < 1) {
    respond_wallet_request_details(400, ['success' => false, 'message' => 'Choose a valid request.']);
}

$userId = (int) $_SESSION['user_id'];
$db = database();

if ($kind === 'deposit') {
    $query = $db->prepare("
        SELECT id, amount, currency, provider, transaction_reference, status,
               rejection_reason, created_at, verified_at
        FROM deposit_requests
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
} else {
    $query = $db->prepare("
        SELECT id, amount, currency, destination_provider AS provider,
               destination_name, destination_account, payout_reference, status,
               rejection_reason, created_at, reviewed_at
        FROM withdrawal_requests
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
}

try {
    $query->execute([$id, $userId]);
    $request = $query->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log(sprintf('Wallet request detail lookup failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
    respond_wallet_request_details(500, ['success' => false, 'message' => 'Request details are temporarily unavailable.']);
}
if (!$request) {
    respond_wallet_request_details(404, ['success' => false, 'message' => 'Request not found.']);
}

$request['kind'] = $kind;
$request['id'] = (int) $request['id'];
respond_wallet_request_details(200, ['success' => true, 'request' => $request]);
