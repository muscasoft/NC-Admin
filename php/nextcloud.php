<?php
// 29/11/2025 : Moved functions getCONFIG and getStepPattern from general.php to nextcloud.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/general.php';

function getCONFIG(): array {
    static $CONFIG = null;

    $loadConfigFile = function (): array {
        global $configFileName;

        if (!file_exists($configFileName)) {
            throw new Exception('Config file not found');
        }

        require $configFileName;

        if (!isset($CONFIG)) {
            throw new Exception('Configuration variable not found in config file');
        }

        return $CONFIG;
    };

    if ($CONFIG == null) {
        $CONFIG = $loadConfigFile();
    }

    return $CONFIG;
}

function getStepPattern(): string {
    global $configFileName;
    $CONFIG = getCONFIG();
    $dataDirectory = $CONFIG['datadirectory'];
    if (!isset($dataDirectory)) {
        throw new Exception('Data directory not found');
    }
    return "$dataDirectory/updater-*/.step";
}