<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/dating.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    http_response_code(404);
    exit;
}

$mediaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!is_int($mediaId) || $mediaId < 1) {
    http_response_code(404);
    exit;
}

try {
    $mediaQuery = database()->prepare("
        SELECT m.storage_name, m.mime_type, m.media_type, p.user_id
        FROM dating_profile_media m
        INNER JOIN dating_profiles p ON p.id = m.profile_id
        WHERE m.id = :media_id
        LIMIT 1
    ");
    $mediaQuery->execute(['media_id' => $mediaId]);
    $media = $mediaQuery->fetch();
} catch (PDOException $exception) {
    error_log(sprintf('Selam private media lookup failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
    http_response_code(503);
    exit;
}

if (!$media) {
    http_response_code(404);
    exit;
}

if ((int) $media['user_id'] !== (int) $_SESSION['user_id']) {
    try {
        if (!dating_profiles_are_mutual_matches(database(), (int) $_SESSION['user_id'], (int) $media['user_id'])) {
            http_response_code(404);
            exit;
        }
    } catch (PDOException $exception) {
        error_log(sprintf('Selam private media matching check failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
        http_response_code(503);
        exit;
    }
}

$allowedTypes = [
    'image/jpeg' => ['type' => 'photo', 'extension' => 'jpg'],
    'image/png' => ['type' => 'photo', 'extension' => 'png'],
    'image/webp' => ['type' => 'photo', 'extension' => 'webp'],
    'video/mp4' => ['type' => 'video', 'extension' => 'mp4'],
    'video/webm' => ['type' => 'video', 'extension' => 'webm'],
];
$mimeType = (string) $media['mime_type'];
$storageName = (string) $media['storage_name'];
$allowedType = $allowedTypes[$mimeType] ?? null;

if (
    $allowedType === null
    || $allowedType['type'] !== $media['media_type']
    || !preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-a[a-f0-9]{3}-[a-f0-9]{12}\.' . preg_quote($allowedType['extension'], '/') . '\z/', $storageName)
) {
    error_log('Selam private media metadata was invalid.');
    http_response_code(404);
    exit;
}

$mediaPath = __DIR__ . '/../uploads/private-dating/' . $storageName;
if (!is_file($mediaPath) || !is_readable($mediaPath)) {
    error_log('Selam private media file was unavailable.');
    http_response_code(404);
    exit;
}

$fileSize = filesize($mediaPath);
$fileHandle = fopen($mediaPath, 'rb');
if ($fileSize === false || $fileSize < 1 || $fileHandle === false) {
    if (is_resource($fileHandle)) {
        fclose($fileHandle);
    }
    error_log('Selam private media file could not be opened.');
    http_response_code(500);
    exit;
}

$start = 0;
$end = $fileSize - 1;
$statusCode = 200;
$rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';
if (is_string($rangeHeader) && $rangeHeader !== '') {
    if (!preg_match('/\Abytes=(\d*)-(\d*)\z/', trim($rangeHeader), $matches) || ($matches[1] === '' && $matches[2] === '')) {
        fclose($fileHandle);
        header('Content-Range: bytes */' . $fileSize);
        http_response_code(416);
        exit;
    }

    if ($matches[1] === '') {
        $suffixLength = (int) $matches[2];
        if ($suffixLength < 1) {
            fclose($fileHandle);
            header('Content-Range: bytes */' . $fileSize);
            http_response_code(416);
            exit;
        }
        $start = max(0, $fileSize - $suffixLength);
    } else {
        $start = (int) $matches[1];
        $end = $matches[2] === '' ? $end : (int) $matches[2];
    }

    if ($start > $end || $start >= $fileSize) {
        fclose($fileHandle);
        header('Content-Range: bytes */' . $fileSize);
        http_response_code(416);
        exit;
    }
    $end = min($end, $fileSize - 1);
    $statusCode = 206;
}

$contentLength = $end - $start + 1;
http_response_code($statusCode);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $contentLength);
header('Accept-Ranges: bytes');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox');
header('Content-Disposition: inline; filename="selam-media.' . $allowedType['extension'] . '"');
if ($statusCode === 206) {
    header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    if (fseek($fileHandle, $start) !== 0) {
        fclose($fileHandle);
        error_log('Selam private media file seek failed.');
        exit;
    }

    $remaining = $contentLength;
    while ($remaining > 0 && !feof($fileHandle)) {
        $chunk = fread($fileHandle, min(8192, $remaining));
        if ($chunk === false) {
            fclose($fileHandle);
            error_log('Selam private media stream failed.');
            exit;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
    }
}

fclose($fileHandle);
