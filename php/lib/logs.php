<?php
// 06/11/2025 : Content Type in header set to 'application/json' so all functions should return string or array, no JSON
// 06/11/2025 : Require_once added
// 06/11/2025 : Response code changed from 404 to 500
// 29/11/2025 : Moved functions getCONFIG and getStepPattern from general.php to nextcloud.php
// 29/11/2025 : Moved php to lib

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/nextcloud.php';

function getLogData(): array | string
{
    global $configFileName;
    try {
        $CONFIG = getCONFIG();
        $dataDirectory = $CONFIG['datadirectory'];

        $logFile = "$dataDirectory/nextcloud.log";

        if (!file_exists($logFile)) {
            throw new Exception('Log file not found');
        }

        $startDateLogRetrieval = strtotime('-30 days');

        $handle = fopen($logFile, 'r');
        if (!$handle) {
            throw new Exception('Log file could not be opened');
        }

        $logs = [];
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;

            $logEntry = json_decode($line, true);
            if ($logEntry === null) continue;

            if (isset($logEntry['time'])) {
                $logTime = strtotime($logEntry['time']);
                if ($logTime >= $startDateLogRetrieval) {
                    $logs[] = [
                        'time' => $logEntry['time'],
                        'level' => $logEntry['level'],
                        'app' => $logEntry['app'] ?? '',
                        'user' => $logEntry['user'] ?? '',
                        'message' => $logEntry['message'] ?? ''
                    ];
                }
            }
        }

        fclose($handle);
        return $logs;
    } catch (Exception $e) {
        http_response_code(500);
        return $e->getMessage();
    }
}