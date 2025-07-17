<?php
function writewriteLogToFile($message, $logDir = './logs/') {
    // Ensure log directory exists
    if (!file_exists($logDir)) {
        mkdir($logDir, 0775, true);
    }

    $date = date('Y-m-d');
    $timestamp = date('Y-m-d H:i:s');
    $fileName = $logDir . "Log-$date.txt";
    $logEntry = "[$timestamp] $message" . PHP_EOL;

    file_put_contents($fileName, $logEntry, FILE_APPEND);
}