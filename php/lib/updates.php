<?php
// 29/11/2025 : Functions moved from main.php to updates.php and getNCVersion's return value changed from string into array
// 29/11/2025 : Moved php to lib
// 29/11/2025 : Moved config.php back to php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/nextcloud.php';

function getNCVersion(): array {
    global $versionFileName, $configFileName;
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
        throw new Exception('Could not do request to updater server: ' . curl_error($curl), 500);
    }
    curl_close($curl);

    // Response can be empty when no update is available
    if ($response === '') {
        return [
            'current_version' => $CONFIG['version'],
            'latest_version'  => ''
        ];
    } else {
//        return 'Current version: ' . $CONFIG['version'] . '. No update available.';
//    }

//    $xml = simplexml_load_string($response);
//    if ($xml === false) {
//        throw new Exception('Could not parse updater server XML response', 500);
//    }

    $response = get_object_vars($xml);
//     return 'Current version: ' . $CONFIG['version'] . '. Update available to ' . $response['version'];
        return [
            'current_version' => $CONFIG['version'],
            'latest_version'  => $response['version']
        ];
    }
}

function isUpdateRunning(): int {
    return !empty(glob(getStepPattern())) ? 1 : 0;
};

function resetUpdateRunning(): string {
    return removeFile(glob(getStepPattern())[0]);
};