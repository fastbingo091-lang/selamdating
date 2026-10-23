<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/telegram.php';

$testBotToken = 'local-test-token-not-a-real-secret';
putenv('TELEGRAM_BOT_TOKEN=' . $testBotToken);

$makeInitData = static function (int $authDate, array $telegramUser) use ($testBotToken): string {
    $fields = [
        'auth_date' => (string) $authDate,
        'user' => json_encode($telegramUser, JSON_THROW_ON_ERROR),
    ];
    ksort($fields, SORT_STRING);

    $dataCheckString = implode("\n", array_map(
        static fn (string $key, string $value): string => $key . '=' . $value,
        array_keys($fields),
        array_values($fields)
    ));
    $secretKey = hash_hmac('sha256', $testBotToken, 'WebAppData', true);
    $fields['hash'] = hash_hmac('sha256', $dataCheckString, $secretKey);

    return http_build_query($fields);
};

$validInitData = $makeInitData(time(), ['id' => 123456789, 'username' => 'nut_test_user']);
$verifiedUser = verified_telegram_user($validInitData);
if ($verifiedUser !== ['id' => '123456789', 'username' => 'nut_test_user']) {
    throw new RuntimeException('Valid Telegram initData was not accepted as expected.');
}

parse_str($validInitData, $tamperedFields);
$tamperedTelegramUser = json_decode($tamperedFields['user'], true, 512, JSON_THROW_ON_ERROR);
$tamperedTelegramUser['username'] = 'changed_username';
$tamperedFields['user'] = json_encode($tamperedTelegramUser, JSON_THROW_ON_ERROR);
$tamperedRejected = false;
try {
    verified_telegram_user(http_build_query($tamperedFields));
} catch (RuntimeException $exception) {
    $tamperedRejected = true;
}
if (!$tamperedRejected) {
    throw new RuntimeException('Tampered Telegram initData was not rejected.');
}

$expiredRejected = false;
try {
    verified_telegram_user($makeInitData(time() - 901, ['id' => 123456789, 'username' => 'nut_test_user']));
} catch (RuntimeException $exception) {
    $expiredRejected = true;
}
if (!$expiredRejected) {
    throw new RuntimeException('Expired Telegram initData was not rejected.');
}

if (normalize_phone_number(' +251 912-345-678 ') !== '+251912345678') {
    throw new RuntimeException('International phone normalization failed.');
}
if (normalize_phone_number('0912345678') !== null) {
    throw new RuntimeException('A phone number without a country code was accepted.');
}

foreach (['123456', 'ABC123', 'a1b2c3', 'Gofer1'] as $validPassword) {
    if (!password_meets_policy($validPassword)) {
        throw new RuntimeException('A valid six-character password was rejected.');
    }
}
foreach (['12345', '1234567', 'abc!12', 'Gofer@1', '12 3456'] as $invalidPassword) {
    if (password_meets_policy($invalidPassword)) {
        throw new RuntimeException('An invalid six-character password was accepted.');
    }
}

$passwordHash = password_hash('ABC123', PASSWORD_DEFAULT);
if ($passwordHash === 'ABC123' || !password_verify('ABC123', $passwordHash)) {
    throw new RuntimeException('Six-character password hashing or verification failed.');
}

echo "Auth identity and password-policy checks passed.\n";
