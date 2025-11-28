<?php
// 06/11/2025 : Require_once added
// 28/11/2025 : $CONFIG changed into static variable; new loadConfigFile() function

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

function getHash($filename): string
{
    $name = basename($filename);
    $size = filesize($filename);
    $mtime = filemtime($filename);
    return hash('sha256', "$name|$size|$mtime");
}