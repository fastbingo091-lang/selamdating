<?php
declare(strict_types=1);

/**
 * Return the application's shared PDO connection.
 * Set DB_HOST, DB_NAME, DB_USER, and DB_PASS in the Apache environment
 * to override the local XAMPP defaults.
 */
function database(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $appEnvironment = getenv('APP_ENV') ?: 'development';
    if ($appEnvironment === 'production') {
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $requiredSetting) {
            $setting = getenv($requiredSetting);
            if (!is_string($setting) || trim($setting) === '') {
                throw new RuntimeException(sprintf('Set %s for production.', $requiredSetting));
            }
        }
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'goferbet';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS');
    $password = $password === false ? '' : $password;
    $portInput = getenv('DB_PORT');
    $port = $portInput === false || $portInput === '' ? 3306 : filter_var($portInput, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);

    if ($port === false) {
        throw new RuntimeException('DB_PORT must be a valid TCP port.');
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $sslCa = getenv('DB_SSL_CA');
    if (is_string($sslCa) && trim($sslCa) !== '') {
        if (!is_readable($sslCa) || !defined('PDO::MYSQL_ATTR_SSL_CA')) {
            throw new RuntimeException('DB_SSL_CA must point to a readable MySQL CA certificate file.');
        }
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }
    }

    $connection = new PDO($dsn, $user, $password, $options);

    return $connection;
}