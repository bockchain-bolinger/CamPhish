<?php

declare(strict_types=1);

$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$clientIp = $remoteAddr;

$trustProxy = getenv('TRUST_PROXY') === '1';
if ($trustProxy) {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $candidate = trim($parts[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $clientIp = $candidate;
        }
    }
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$userAgent = substr(preg_replace('/[\x00-\x1F\x7F]/', '', $userAgent) ?? 'unknown', 0, 512);

$line = sprintf("IP: %s\r\nUser-Agent: %s\r\n", $clientIp, $userAgent);
file_put_contents('ip.txt', $line, FILE_APPEND | LOCK_EX);
