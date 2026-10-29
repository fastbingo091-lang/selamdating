<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

try {
    database()->query('SELECT 1');
    http_response_code(200);
    echo "ok\n";
} catch (Throwable $exception) {
    error_log(sprintf('Health check failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
    http_response_code(503);
    echo "unavailable\n";
}
