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

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/disk.php';
require_once __DIR__ . '/files.php';
require_once __DIR__ . '/general.php';
require_once __DIR__ . '/logs.php';
require_once __DIR__ . '/setupchecks.php';

$action = $_POST['action'];
switch ($action) {
    case 'GetNCVersion':
        returnValue(getNCVersion());
        break;
    case 'IsUpdateRunning':
        returnValue(!empty(glob(getStepPattern())) ? 1 : 0);
        break;
    case 'ResetUpdateRunning':
        returnValue(removeFile(glob(getStepPattern())[0]));
        break;
    case 'GetDiskStatistics':
        returnValue(getDiskStatisticsForHomeDir());
        break;
    case 'GetLatestBackupFile':
        returnValue(getLatestBackupFile());
        break;
    case 'MakeBackupDatabase':
        returnValue(makeBackupDatabase());
        break;
    case 'ListBackupFiles':
        returnValue(listBackupFiles());
        break;
    case 'DeleteBackupFiles':
        returnValue(deleteBackupFiles());
        break;
    case 'GetLogData':
        returnValue(getLogData());
        break;        
    case 'GetSetupChecks':
        returnValue(getSetupChecks());
        break;
    case 'SkipRepairSetupChecks':
        returnValue($skipRepairSetupChecks);
        break;
    case 'DefinedActions':
        returnValue($definedActions);
        break;
    case 'MimeTypeMigrationAvailable':
        $result = shell_exec("php --define apc.enable_cli=1 $occCommand maintenance:repair --include-expensive");
        returnValue($result);
        break;
    case 'DatabaseHasMissingIndices':
        $result = shell_exec("php --define apc.enable_cli=1 $occCommand db:add-missing-indices");
        returnValue($result);
        break;
    case 'SecurityHeaders':
        returnValue(repairSecurityHeaders());
        break;
    default:
        http_response_code(400);
        returnValue('error: action not defined');
        break;
}    

function getNCVersion(): string {
    global $versionFileName, $configFileName;
    try {
        // get  $OC_Build;
        if (!file_exists($versionFileName)) {
            throw new Exception('Version file not found');
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
            throw new Exception('Could not do request to updater server: ' . curl_error($curl));
        }
        curl_close($curl);
    
        // Response can be empty when no update is available
        if ($response === '') {
            throw new Exception('Current version: ' . $CONFIG['version'] . '. No update available.');
        }
    
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            throw new Exception('Could not parse updater server XML response');
        }
    
        $response = get_object_vars($xml);
        return 'Current version: ' . $CONFIG['version'] . '. Update available to ' . $response['version'];
    } catch (Exception $e) {
        return $e->getMessage();
    }
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