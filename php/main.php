<?php
// v2 version check included
// mogelijk nog vervangen ' en .  door formatted string en ""
// mogelijk nog vervangen shell_exec door exec
// get fileLocations: $occCommand, $versionFileName, $configFileName and $stepPattern;
// 06/11/2025 : Content Type in header set to 'application/json' so all functions should return string or array, no JSON
// 06/11/2025 : Response code changed from 404 to 400
// 06/11/2025 : Spaces removed or added
// 07/11/2025 : Replaced returnAsJson by returnValue to prevent quotes around strings
// 26/11/2025 : Added header info in returnValue
// 26/11/2025 : Error solved in IsUpdateRunning
// 26/11/2025 : Long switch statement replaced with  associative array mapping action names to corresponding handler functions
// 26/11/2025 : Call to new functions repairMimeTypeMigrationAvailable and repairDatabaseHasMissingIndices
// 26/11/2025 : Try/catch to catch errors
// 28/11/2025 : Correct bug in callable array
// 28/11/2025 : Function getNCVersion throws no error if no update is available

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/disk.php';
require_once __DIR__ . '/files.php';
require_once __DIR__ . '/general.php';
require_once __DIR__ . '/logs.php';
require_once __DIR__ . '/setupchecks.php';

global $skipRepairSetupChecks, $definedActions;

$action = $_POST['action'];
$actions = [

    'GetNCVersion'               => 'getNCVersion',
    'IsUpdateRunning'            => fn() => !empty(glob(getStepPattern())) ? 1 : 0,
    'ResetUpdateRunning'         => fn() => removeFile(glob(getStepPattern())[0]),
    'GetDiskStatistics'          => 'getDiskStatisticsForHomeDir',
    'GetLatestBackupFile'        => 'getLatestBackupFile',
    'MakeBackupDatabase'         => 'makeBackupDatabase',
    'ListBackupFiles'            => 'listBackupFiles',
    'DeleteBackupFiles'          => 'deleteBackupFiles',
    'GetLogData'                 => 'getLogData',
    'GetSetupChecks'             => 'getSetupChecks',
    'SkipRepairSetupChecks'      => fn() => $skipRepairSetupChecks,
    'DefinedActions'             => fn() => $definedActions,
    'MimeTypeMigrationAvailable' => 'repairMimeTypeMigrationAvailable',
    'DatabaseHasMissingIndices'  => 'repairDatabaseHasMissingIndices',
    'SecurityHeaders'            => 'repairSecurityHeaders',
];

try {
    if (!isset($actions[$action])) {
        throw new Exception('action not defined', 400);
    }

    $result = call_user_func($actions[$action]);
    returnValue($result);

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);

    returnValue('error: ' . $e->getMessage());
}

function getNCVersion(): string {
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
        return 'Current version: ' . $CONFIG['version'] . '. No update available.';
    }

    $xml = simplexml_load_string($response);
    if ($xml === false) {
        throw new Exception('Could not parse updater server XML response', 500);
    }

    $response = get_object_vars($xml);
    return 'Current version: ' . $CONFIG['version'] . '. Update available to ' . $response['version'];
}

function returnValue($result)
{
    if (is_string($result)) {
        header("Content-Type: text/html; charset=utf-8");
        echo $result;
    } else {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($result);
    };
}