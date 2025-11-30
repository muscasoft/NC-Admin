<?php
// 06/11/2025 : Content Type in header set to 'application/json' so all functions should return string or array, no JSON
// 06/11/2025 : Require_once added
// 26/11/2025 : Added functions repairMimeTypeMigrationAvailable and repairDatabaseHasMissingIndices
// 26/11/2025 : Added exceptions
// 28/11/2025 : New functions getSkipRepairSetupChecks and getDefinedActions
// 29/11/2025 : Moved php to lib
// 29/11/2025 : Moved config.php back to php
// 30/11/2025 : Added logger

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/bootstrap.php';

$skipRepairSetupChecks = [
    'BruteForceThrottler',
    'ForwardedForHeaders',
];

$definedActions = [
    'LogErrors' => false,
    'BruteForceThrottler' => false,
    'MimeTypeMigrationAvailable' => true,
    'ForwardedForHeaders' => false,
    'DatabaseHasMissingIndices' => true,
    'SecurityHeaders' => true,
];

function getSkipRepairSetupChecks(): array {
    global $skipRepairSetupChecks, $logger;
    $logger->debug("getSkipRepairSetupChecks started/ ended succesfully");
    return $skipRepairSetupChecks;
}

function getDefinedActions(): array {
    global $definedActions, $logger;
    $logger->debug("getDefinedActions started/ ended succesfully");
    return $definedActions;
}

function getSetupChecks(): array | string {
    global $occCommand, $logger;
    $logger->debug("getSetupChecks started");
    $output = shell_exec("php --define apc.enable_cli=1 $occCommand setupchecks --output=json_pretty");
    $obj = json_decode($output);
    $result= [];
    
    // Loop through the object and select only warning and errors (not successes)
    foreach($obj as $groupKey=>$groupValue){
        foreach($groupValue as $itemKey=>$itemValue){
            if ($itemValue->severity !== 'success') {
                array_push($result, (object)[
                    'id' => $itemKey,
                    'name' => $itemValue->name,
                    'severity' => $itemValue->severity,
                    'description' => $itemValue->description,
                    'descriptionParameters' => $itemValue->descriptionParameters,
                    'linkToDoc' => $itemValue->linkToDoc,
                ]);
            }
        }
    }
    
    array_push($result, (object)[
        'logdata' => $obj,
    ]);
    $logger->debug("getSetupChecks ended successfully");
    return $result;
}

function repairMimeTypeMigrationAvailable(): string {
    global $occCommand, $logger;
    $logger->debug("repairMimeTypeMigrationAvailable started");
    if (!is_file($occCommand)) {
        $errorMessage = 'occ not found';
        $logger->warning("repairMimeTypeMigrationAvailable aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    if (!is_executable($occCommand)) {
        $errorMessage = 'occ not executable';
        $logger->warning("repairMimeTypeMigrationAvailable aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }
    
    $logger->debug("repairMimeTypeMigrationAvailable ended successfully");
    return shell_exec("php --define apc.enable_cli=1 $occCommand maintenance:repair --include-expensive");
}

function repairDatabaseHasMissingIndices(): string {
    global $occCommand, $logger;
    $logger->debug("repairDatabaseHasMissingIndices started");
    if (!is_file($occCommand)) {
        $errorMessage = 'occ not found';
        $logger->warning("repairDatabaseHasMissingIndices aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    if (!is_executable($occCommand)) {
        $errorMessage = 'occ not executable';
        $logger->warning("repairDatabaseHasMissingIndices aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }
    
    $result = shell_exec("php --define apc.enable_cli=1 $occCommand db:add-missing-indices");
    $logger->debug("repairDatabaseHasMissingIndices ended successfully");
    return $result;
}

function repairSecurityHeaders(): string {
    global $logger;
    $logger->debug("repairSecurityHeaders started");
    $searchString = <<<SEARCHSTRING
#### DO NOT CHANGE ANYTHING ABOVE THIS LINE ####

ErrorDocument 403 //index.php/error/403
ErrorDocument 404 //index.php/error/404

SEARCHSTRING;
    
    $replaceString = <<<REPLACESTRING
#### DO NOT CHANGE ANYTHING ABOVE THIS LINE ####

<IfModule mod_rewrite.c>
    RewriteCond %{HTTP:X-Forwarded-Proto} !https
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</IfModule>

Header onsuccess unset Strict-Transport-Security
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" env=HTTPS

ErrorDocument 403 //index.php/error/403
ErrorDocument 404 //index.php/error/404

REPLACESTRING;

    $searchString = str_replace('\r\n', '\n', $searchString);
    $replaceString = str_replace('\r\n', '\n', $replaceString);

    $charsToEscape = ['.', '/', '^', '(', ')', '{', '}', '[', ']', '*', '$', '"'];
    $charsEscaped = ['\.', '\/', '\^', '\(', '\)', '\{', '\}', '\[', '\]', '\*', '\$', '\"'];
    $charsToEscapeLess = ['$'];
    $charsEscapedLess  = ['\$'];
    
    $searchStringEscaped = '/' . str_replace($charsToEscape, $charsEscaped, $searchString) . '$/';
    $replaceStringEscaped = '/' . str_replace($charsToEscape, $charsEscaped, $replaceString) . '$/';
    $replaceStringEscapedLess = str_replace($charsToEscapeLess, $charsEscapedLess, $replaceString);
    
    $filePath = '../../nextcloud/.htaccess';
    if (file_exists($filePath)){
        $fileContents = file_get_contents($filePath);
        
        $foundSearchString = preg_match($searchStringEscaped, $fileContents, $matches);
        switch ($foundSearchString) {
            case 1:
                $fileContents = preg_replace($searchStringEscaped, $replaceStringEscapedLess, $fileContents);
                file_put_contents($filePath, $fileContents);
                $result = 'HSTS section updated';
                break;
            case 0:
                $foundReplaceString = preg_match($replaceStringEscaped, $fileContents, $matches);
                switch ($foundReplaceString) {
                    case 1:
                        $errorMessage = 'Nothing done: Updated HSTS section found';
                        $logger->warning("repairSecurityHeaders aborted with error: $errorMessage}");
                        throw new Exception($errorMessage, 500);
                    case 0:
                        $errorMessage = 'Nothing done: No HSTS section found';
                        $logger->warning("repairSecurityHeaders aborted with error: $errorMessage}");
                        throw new Exception($errorMessage, 500);
                    default:
                        $errorMessage = 'Nothing done: Multiple updated HSTS sections found';
                        $logger->warning("repairSecurityHeaders aborted with error: $errorMessage}");
                        throw new Exception($errorMessage, 500);
                }
            default:
                $errorMessage = 'Nothing done: Multiple updated HSTS sections found';
                $logger->warning("repairSecurityHeaders aborted with error: $errorMessage}");
                throw new Exception($errorMessage, 500);
        }
    } else {
        $errorMessage = 'Nothing done: .htAccess file not found';
        $logger->warning("repairSecurityHeaders aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }
    $logger->debug("repairSecurityHeaders ended successfully");
    return $result;
}