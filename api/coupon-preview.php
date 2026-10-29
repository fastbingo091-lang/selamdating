<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond_coupon_preview(int $status, array $response): never
{
    http_response_code($status);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respond_coupon_preview(405, ['success' => false, 'message' => 'Use the coupon form to check a code.']);
}
if (!isset($_SESSION['user_id'])) {
    respond_coupon_preview(401, ['success' => false, 'message' => 'Sign in to check a coupon.']);
}
if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
    respond_coupon_preview(403, ['success' => false, 'message' => 'Your form expired. Reload the page and try again.']);
}

$codeInput = $_POST['code'] ?? '';
$code = is_string($codeInput) ? strtoupper(trim($codeInput)) : '';
if (preg_match('/\A[A-Z0-9][A-Z0-9_-]{2,39}\z/', $code) !== 1) {
    respond_coupon_preview(400, ['success' => false, 'message' => 'Enter a valid coupon code.']);
}

$db = database();
try {
    $db->beginTransaction();
    $couponQuery = $db->prepare("
        SELECT id, discount_amount, max_redemptions
        FROM marketplace_coupons
        WHERE code = ? AND status = 'active'
          AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1
        FOR UPDATE
    ");
    $couponQuery->execute([$code]);
    $coupon = $couponQuery->fetch(PDO::FETCH_ASSOC);
    if (!$coupon) {
        $db->rollBack();
        respond_coupon_preview(404, ['success' => false, 'message' => 'This coupon is invalid, expired, or inactive.']);
    }

    $userId = (int) $_SESSION['user_id'];
    $userRedemption = $db->prepare('SELECT 1 FROM marketplace_coupon_redemptions WHERE coupon_id = ? AND user_id = ? LIMIT 1');
    $userRedemption->execute([(int) $coupon['id'], $userId]);
    if ($userRedemption->fetchColumn() !== false) {
        $db->rollBack();
        respond_coupon_preview(409, ['success' => false, 'message' => 'You have already used this coupon.']);
    }

    if ($coupon['max_redemptions'] !== null) {
        $redemptionCount = $db->prepare('SELECT COUNT(*) FROM marketplace_coupon_redemptions WHERE coupon_id = ?');
        $redemptionCount->execute([(int) $coupon['id']]);
        if ((int) $redemptionCount->fetchColumn() >= (int) $coupon['max_redemptions']) {
            $db->rollBack();
            respond_coupon_preview(409, ['success' => false, 'message' => 'This coupon has reached its redemption limit.']);
        }
    }

    $db->commit();
    respond_coupon_preview(200, [
        'success' => true,
        'code' => $code,
        'discount_amount' => number_format((float) $coupon['discount_amount'], 2, '.', ''),
    ]);
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log(sprintf('Coupon preview failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
    respond_coupon_preview(500, ['success' => false, 'message' => 'Unable to check this coupon right now. Please try again.']);
}
