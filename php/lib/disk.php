<?php

use LDAP\Result;
// 06/11/2025 : Response code changed from 404 to 500
// 26/11/2025 : Removed try/catch from function getDiskStatistics
// 29/11/2025 : Moved php to lib
// 30/11/2025 : Added logger

require_once __DIR__ . '/bootstrap.php';

function getDiskStatisticsForHomeDir(): string {
    global $logger;
    $logger->debug("getDiskStatisticsForHomeDir started");

    $homeDir = getHomeDir();
    $result = getDiskStatistics($homeDir, 2, 'B'); 

    $logger->debug("getDiskStatisticsForHomeDir ended successfully");
    return $result;
}

function getHomeDir(): string {
    $homeDir = getenv('HOME') ?: getenv('USERPROFILE');

    if (!$homeDir && function_exists('posix_getuid') && function_exists('posix_getpwuid')) {
        $info = posix_getpwuid(posix_getuid());
        $homeDir = $info['dir'] ?? null;
    }
 
    // If still not found, fallback to current working directory
    if (!$homeDir || !is_dir($homeDir)) {
        $homeDir = getcwd();
    }
    
    return $homeDir;
}

function getDiskStatistics($path = '/', $precision = 2, $unit = 'auto'): string
{
    global $logger;
    $logger->debug("getDiskStatistics started");

    $command = sprintf('du -sb %s 2>/dev/null', escapeshellarg($path));
    exec($command, $output, $returnVar);

    if ($returnVar !== 0 || empty($output)) {
        $errorMessage = 'Failed to run command';
        $logger->warning("getDiskStatistics aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    $parts = preg_split('/\s+/', trim($output[0]));
    $usedBytes = isset($parts[0]) ? (int)$parts[0] : 0;
    
    $logger->debug("getDiskStatistics ended successfully");
    return $usedBytes;
}