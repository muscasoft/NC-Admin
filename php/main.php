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
// 28/11/2025 : New functions isUpdateRunning and resetUpdateRunning
// 28/11/2025 : Callable calls to isUpdateRunning, resetUpdateRunning,  getSkipRepairSetupChecks and getDefinedActions
// 29/11/2025 : Functions moved from main.php to updates.php and getNCVersion's return value changed from string into array
// 29/11/2025 : Moved functions getCONFIG and getStepPattern from general.php to nextcloud.php
// 29/11/2025 : Moved php to lib
// 30/11/2025 : Added logger

require_once __DIR__ . '/lib/backup.php';
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/disk.php';
require_once __DIR__ . '/lib/logs.php';
require_once __DIR__ . '/lib/nextcloud.php';
require_once __DIR__ . '/lib/setupchecks.php';
require_once __DIR__ . '/lib/updates.php';

global $skipRepairSetupChecks, $definedActions;

$action = $_POST['action'];
$logger->info("$action started");
$actions = [

    'GetNCVersion'               => 'getNCVersion',
    'IsUpdateRunning'            => 'isUpdateRunning',
    'ResetUpdateRunning'         => 'resetUpdateRunning',
    'GetDiskStatistics'          => 'getDiskStatisticsForHomeDir',
    'GetLatestBackupFile'        => 'getLatestBackupFile',
    'MakeBackupDatabase'         => 'makeBackupDatabase',
    'ListBackupFiles'            => 'listBackupFiles',
    'DeleteBackupFiles'          => 'deleteBackupFiles',
    'GetLogData'                 => 'getLogData',
    'GetSetupChecks'             => 'getSetupChecks',
    'SkipRepairSetupChecks'      => 'getSkipRepairSetupChecks',
    'DefinedActions'             => 'getDefinedActions',
    'MimeTypeMigrationAvailable' => 'repairMimeTypeMigrationAvailable',
    'DatabaseHasMissingIndices'  => 'repairDatabaseHasMissingIndices',
    'SecurityHeaders'            => 'repairSecurityHeaders',
];

try {
    if (!isset($actions[$action])) {
        throw new Exception('action not defined', 400);
    }

    $result = call_user_func($actions[$action]);
    $logger->info("$action ended successfully");
    returnValue($result);

} catch (Exception $e) {
    $logger->warning("$action with error: {$e->getMessage()}");
    $code = $e->getCode() ?: 500;
    http_response_code($code);

    returnValue('error: ' . $e->getMessage());
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