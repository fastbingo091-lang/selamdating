<?php
declare(strict_types=1);

/**
 * Validate Telegram Mini App initData before using its user identity.
 * Returns null for a regular browser request without Telegram data.
 *
 * @return array{id: string, username: ?string}|null
 */
function verified_telegram_user(string $initData): ?array
{
    if ($initData === '') {
        return null;
    }

    if (strlen($initData) > 16384) {
        throw new RuntimeException('Telegram sign-in data is too large.');
    }

    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    if ($botToken === false || $botToken === '') {
        throw new RuntimeException('Telegram sign-in is not configured on this server.');
    }

    parse_str($initData, $fields);
    $receivedHash = $fields['hash'] ?? null;
    $authDate = $fields['auth_date'] ?? null;
    $userJson = $fields['user'] ?? null;

    if (!is_string($receivedHash)
        || !preg_match('/\A[a-f0-9]{64}\z/i', $receivedHash)
        || !is_string($authDate)
        || !ctype_digit($authDate)
        || !is_string($userJson)
    ) {
        throw new RuntimeException('Telegram sign-in data is invalid.');
    }

    unset($fields['hash']);
    foreach ($fields as $value) {
        if (!is_string($value)) {
            throw new RuntimeException('Telegram sign-in data is invalid.');
        }
    }
    ksort($fields, SORT_STRING);

    $dataCheckString = implode("\n", array_map(
        static fn (string $key, string $value): string => $key . '=' . $value,
        array_keys($fields),
        array_values($fields)
    ));
    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

    if (!hash_equals(strtolower($receivedHash), $calculatedHash)) {
        throw new RuntimeException('Telegram sign-in data could not be verified.');
    }

    $issuedAt = (int) $authDate;
    if ($issuedAt > time() + 60 || $issuedAt < time() - 900) {
        throw new RuntimeException('Telegram sign-in has expired. Open the Mini App again.');
    }

    $telegramUser = json_decode($userJson, true);
    if (!is_array($telegramUser)) {
        throw new RuntimeException('Telegram user data is invalid.');
    }

    $telegramId = $telegramUser['id'] ?? null;
    if ((!is_int($telegramId) && !is_string($telegramId))
        || !preg_match('/\A[1-9][0-9]{0,19}\z/', (string) $telegramId)
    ) {
        throw new RuntimeException('Telegram user data is invalid.');
    }

    $telegramUsername = $telegramUser['username'] ?? null;
    if ($telegramUsername !== null
        && (!is_string($telegramUsername)
            || !preg_match('/\A[A-Za-z][A-Za-z0-9_]{4,31}\z/', $telegramUsername))
    ) {
        throw new RuntimeException('Telegram username is invalid.');
    }

    return [
        'id' => (string) $telegramId,
        'username' => $telegramUsername,
    ];
}
