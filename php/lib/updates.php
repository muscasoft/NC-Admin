<?php
// 29/11/2025 : Functions moved from main.php to updates.php and getNCVersion's return value changed from string into array
// 29/11/2025 : Moved php to lib
// 29/11/2025 : Moved config.php back to php
// 30/11/2025 : Added logger
// 30/11/2025 : Moved function removeFile from list.php to updates.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/nextcloud.php';

function getNCVersion(): array {
    global $versionFileName, $configFileName, $logger;
    $logger->debug("getNCVersion started");
    // get  $OC_Build;
    if (!file_exists($versionFileName)) {
        throw new Exception('Version file not found', 500);
    };
    require_once $versionFileName;

    $CONFIG = getCONFIG();

    global $releaseChannel, $updaterServer;

    $updateURL = $updaterServer . '?version=' . str_replace('.', 'x', $CONFIG['version']) . 'xxx'
                                                . $releaseChannel . 'xx'
                                                . urlencode($OC_Build) . 'x'
                                                . PHP_MAJOR_VERSION . 'x'
                                                . PHP_MINOR_VERSION . 'x'
                                                . PHP_RELEASE_VERSION;

    $curl = curl_init();
    curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $updateURL,
            CURLOPT_USERAGENT => 'Nextcloud Updater',
    ]);

    $response = curl_exec($curl);
    if ($response === false) {
        $errorMessage = 'Could not do request to updater server: ' . curl_error($curl);
        $logger->warning("getNCVersion aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }
    curl_close($curl);

    // Response can be empty when no update is available
    if ($response === '') {
        $logger->debug("getNCVersion ended successfully (no new version)");
        return [
            'current_version' => $CONFIG['version'],
            'latest_version'  => ''
        ];
    }

    $xml = simplexml_load_string($response);
    if ($xml === false) {
        $errorMessage = 'Could not parse updater server XML response';
        $logger->warning("getNCVersion aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    $response = get_object_vars($xml);
    $logger->debug("getNCVersion ended successfully (newer version)");
    return [
        'current_version' => $CONFIG['version'],
        'latest_version'  => $response['version']
    ];
}

function isUpdateRunning(): int {
    global $logger;
    $logger->debug("isUpdateRunning started/ ended succesfully");
    
    $result = !empty(glob(getStepPattern())) ? 1 : 0;
    
    $logger->debug("isUpdateRunning ended successfully");    
    return $result;
};

function resetUpdateRunning(): string {
    global $logger;
    $logger->debug("resetUpdateRunning started");

    $result = removeFile(glob(getStepPattern())[0]);

    $logger->debug("resetUpdateRunning ended successfully");    
    return $result;
};

function removeFile($filename): string {
    global $logger;
    $logger->debug("removeFile started");

    if (!is_file($filename)) {
        $errorMessage = 'File is not a valid file';
        $logger->warning("removeFile aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    if (!is_writable($filename)) {
        $errorMessage = 'Insufficient permissions to delete file';
        $logger->warning("removeFile aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    if (!unlink($filename)) {
        $errorMessage = 'Failed to delete file';
        $logger->warning("removeFile aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    $logger->debug("removeFile ended successfully");
    return true;
}