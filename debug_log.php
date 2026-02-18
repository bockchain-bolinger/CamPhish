<?php

declare(strict_types=1);

header('Content-Type: application/json');

function respond(string $status, string $code, string $message, int $httpCode): void
{
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'code' => $code,
        'message' => $message,
    ]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond('error', 'method_not_allowed', 'Only POST is allowed', 405);
}

$message = $_POST['message'] ?? '';
if ($message === '') {
    respond('error', 'missing_message', 'No message provided', 400);
}

$message = preg_replace('/[\x00-\x1F\x7F]/', '', $message) ?? '';
$message = substr($message, 0, 512);

if ($message === '') {
    respond('error', 'empty_message', 'Message became empty after sanitizing', 422);
}

$filteredMessages = [
    'Location data sent',
    'getLocation called',
    'Geolocation error',
    'Location permission denied',
];

foreach ($filteredMessages as $phrase) {
    if (strpos($message, $phrase) !== false) {
        respond('success', 'filtered', 'Message ignored by filter', 200);
    }
}

$isAllowed =
    strpos($message, 'Lat:') !== false ||
    strpos($message, 'Latitude:') !== false ||
    strpos($message, 'Position obtained') !== false;

if (!$isAllowed) {
    respond('success', 'ignored', 'Message not relevant', 200);
}

$date = gmdate('Y-m-d H:i:s');
$line = "[{$date}] {$message}\n";

if (file_put_contents('location_debug.log', $line, FILE_APPEND | LOCK_EX) === false) {
    respond('error', 'write_failed', 'Could not write debug log', 500);
}

file_put_contents('LocationLog.log', "Location data captured\n", FILE_APPEND | LOCK_EX);
respond('success', 'ok', 'Debug message stored', 200);
