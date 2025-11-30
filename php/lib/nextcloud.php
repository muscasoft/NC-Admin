<?php
// 29/11/2025 : Moved functions getCONFIG and getStepPattern from general.php to nextcloud.php
// 29/11/2025 : Moved php to lib
// 29/11/2025 : Moved config.php back to php
// 30/11/2025 : Added logger

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/bootstrap.php';

function getCONFIG(): array {
    static $CONFIG = null;
    global $logger;
    $logger->debug("getCONFIG started");

    $loadConfigFile = function (): array {
        global $configFileName, $logger;

        if (!file_exists($configFileName)) {
            $errorMessage = 'Config file not found';
            $logger->warning("getCONFIG aborted with error: $errorMessage}");
            throw new Exception($errorMessage, 500);
        }

        require $configFileName;

        if (!isset($CONFIG)) {
            $errorMessage = 'Configuration variable not found in config file';
            $logger->warning("getCONFIG aborted with error: $errorMessage}");
            throw new Exception($errorMessage, 500);
        }

        return $CONFIG;
    };

    if ($CONFIG == null) {
        $CONFIG = $loadConfigFile();
        $logger->debug("getCONFIG ended successfully (configFile loaded)");
    } else {
        $logger->debug("getCONFIG ended successfully (configFile from memory)");
    }

    return $CONFIG;
}

function getStepPattern(): string {
    global $configFileName, $logger;
    $logger->debug("getStepPattern started");
    $CONFIG = getCONFIG();
    $dataDirectory = $CONFIG['datadirectory'];
    if (!isset($dataDirectory)) {
        $errorMessage = 'Data directory not found';
        $logger->warning("getStepPattern aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }
    $logger->debug("getStepPattern ended successfully");
    return "$dataDirectory/updater-*/.step";
}