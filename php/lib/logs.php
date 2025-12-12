<?php
// 06/11/2025 : Content Type in header set to 'application/json' so all functions should return string or array, no JSON
// 06/11/2025 : Require_once added
// 06/11/2025 : Response code changed from 404 to 500
// 29/11/2025 : Moved functions getCONFIG and getStepPattern from general.php to nextcloud.php
// 29/11/2025 : Moved php to lib
// 29/11/2025 : Moved config.php back to php
// 30/11/2025 : Added logger
// 12/12/2025 : Global $logger replaced by Logger::getInstance()

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/nextcloud.php';

function getLogData(): array | string
{
    global $configFileName;
    $logger = Logger::getInstance();
    $logger->debug("getLogData started");
    try {
        $CONFIG = getCONFIG();
        $dataDirectory = $CONFIG['datadirectory'];

        $logFile = "$dataDirectory/nextcloud.log";

        if (!file_exists($logFile)) {
            $errorMessage = 'Log file not found';
            $logger->warning("getLogData aborted with error: $errorMessage}");
            throw new Exception($errorMessage, 500);
        }

        $startDateLogRetrieval = strtotime('-30 days');

        $handle = fopen($logFile, 'r');
        if (!$handle) {
            $errorMessage = 'Log file could not be opened';
            $logger->warning("getLogData aborted with error: $errorMessage}");
            throw new Exception($errorMessage, 500);
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
        $logger->debug("getLogData ended successfully");
        return $logs;
    } catch (Exception $e) {
        http_response_code(500);
        return $e->getMessage();
    }
}