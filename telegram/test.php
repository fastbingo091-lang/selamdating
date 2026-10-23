<?php

require_once __DIR__ . '/config.php';

$result = telegram_api('getMe');

header('Content-Type: application/json; charset=utf-8');

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
);