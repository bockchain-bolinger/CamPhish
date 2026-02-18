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

$imageData = $_POST['cat'] ?? '';
if ($imageData === '') {
    respond('error', 'missing_payload', 'Image payload missing', 400);
}

if (strlen($imageData) > 4_500_000) {
    respond('error', 'payload_too_large', 'Image payload too large', 413);
}

if (!preg_match('#^data:image\/(png|jpeg);base64,#i', $imageData)) {
    respond('error', 'invalid_payload_format', 'Unsupported image payload format', 422);
}

$commaPos = strpos($imageData, ',');
if ($commaPos === false) {
    respond('error', 'invalid_payload', 'Invalid payload', 422);
}

$filteredData = substr($imageData, $commaPos + 1);
$decodedData = base64_decode($filteredData, true);
if ($decodedData === false) {
    respond('error', 'invalid_base64', 'Could not decode image data', 422);
}

if (strlen($decodedData) > 3_500_000) {
    respond('error', 'decoded_payload_too_large', 'Decoded image too large', 413);
}

$date = gmdate('Ymd_His');
$fileName = 'cam' . $date . '_' . bin2hex(random_bytes(3)) . '.png';

if (file_put_contents($fileName, $decodedData, LOCK_EX) === false) {
    respond('error', 'write_failed', 'Could not save camera image', 500);
}

file_put_contents('Log.log', "Received\r\n", FILE_APPEND | LOCK_EX);
respond('success', 'ok', 'Image received', 200);
