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

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'goferbet';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS');
    $password = $password === false ? '' : $password;

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);

    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}