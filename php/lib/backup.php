<?php
// 06/11/2025 : Content Type in header set to 'application/json' so all functions should return string or array, no JSON
// 06/11/2025 : Require_once added
// 06/11/2025 : Response code changed from 404 to 500
// 26/11/2025 : Removed try/catch from all functions
// 29/11/2025 : Moved php to lib
// 29/11/2025 : Moved config.php back to php
// 30/11/2025 : Added logger
// 30/11/2025 : Moved function listFiles from list.php to backup.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/general.php';

function getLatestBackupFile(): array | string
{
    global $backupFolder, $logger;
    $logger->debug("getLatestBackupFile started");

    if (!is_dir($backupFolder)) {
        $errorMessage = 'Back-up folder not found';
        $logger->warning("getLatestBackupFile aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    $files = array_filter(glob("$backupFolder/*"), 'is_file');

    if (!$files) {
        $errorMessage = 'No files found in back-up folder';
        $logger->warning("getLatestBackupFile aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    $latestFile = array_reduce($files, function ($fileA, $fileB) {
        if ($fileA === null) return $fileB;
        return filemtime($fileA) > filemtime($fileB) ? $fileA : $fileB;
    }, null);

    if ($latestFile === null) {
        $errorMessage = 'No valid files found';
        $logger->warning("getLatestBackupFile aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    $mtime = filemtime($latestFile);

    $logger->debug("getLatestBackupFile ended successfully");
    return [
        "latest_file"   => basename($latestFile),
        "last_modified" => date("Y-m-d H:i:s", $mtime),
        "timestamp"     => $mtime
    ];        
}

function listBackupFiles(): array | string
{
    global $backupFolder, $logger;
    $logger->debug("listBackupFiles started");

    $result = listFiles($backupFolder);

    $logger->debug("listBackupFiles ended successfully");
    return $result;
}

function listFiles($folder): array | string
{
    global $logger;
    $logger->debug("listFiles started");

    if (!is_dir($folder)) {
        $errorMessage = 'Folder not found';
        $logger->warning("listFiles aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    $filenames = array_filter(glob("$folder/*"), 'is_file');
    
    $result = array_map(fn($filename) => [
        'name' => basename($filename),
        'hash' => getHash($filename),
    ], $filenames);

    $logger->debug("listFiles ended successfully");
    return $result;
}
function makeBackupDatabase(): string
{
    global $logger;
    $logger->debug("makeBackupDatabase started/ ended succesfully");

    global $configFileName, $backupFolder;
    $CONFIG = getCONFIG();

    if (!is_dir($backupFolder)) {
        if (!mkdir($backupFolder, 0755, true)) {
            $errorMessage = 'Could not create backup directory';
            $logger->warning("makeBackupDatabase aborted with error: $errorMessage}");
            throw new Exception($errorMessage, 500);
        }
    }

    // --- Create filenames with timestamp ---
    $date = date(format: 'Y-m-d_H-i-s');
    $sqlFile = "$backupFolder/{$CONFIG['dbname']}_backup_$date.sql";
    $tarFile = "$backupFolder/{$CONFIG['dbname']}_backup_$date.tar.gz";

    // --- Do mysqldump ---
    $command = sprintf(
        'mysqldump --single-transaction %s --user=%s --password=%s --host=%s %s > %s 2>&1',
        $CONFIG['mysql.utf8mb4'] ? '--default-character-set=utf8mb4 ' : '',
        escapeshellarg($CONFIG['dbuser']),
        escapeshellarg($CONFIG['dbpassword']),
        escapeshellarg($CONFIG['dbhost']),
        escapeshellarg($CONFIG['dbname']),
        escapeshellarg($sqlFile)
    );

    exec($command, $output, $returnVar);

    $deleteSqlFileIfExist = function() use ($sqlFile) {
        if (isset($sqlFile) && file_exists($sqlFile)) {
            unlink($sqlFile);
        }
    };

    if ($returnVar !== 0) {
        $deleteSqlFileIfExist();
        $errorMessage = 'Backup failed\nOutput:\n' . implode('\n', $output);
        $logger->warning("makeBackupDatabase aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    // --- Create tar.gz archive ---
    $tarCommand = sprintf(
        'tar -czf %s -C %s %s',
        escapeshellarg($tarFile),
        escapeshellarg($backupFolder),
        escapeshellarg(basename($sqlFile))
    );

    exec($tarCommand, $tarOutput, $tarReturn);

    if ($tarReturn !== 0) {
        $errorMessage = 'Compression failed\nOutput:\n' . implode('\n', $tarOutput);
        $logger->warning("makeBackupDatabase aborted with error: $errorMessage}");
        $deleteSqlFileIfExist();
        throw new Exception($errorMessage, 500);
    }

    $deleteSqlFileIfExist();

    $logger->debug("makeBackupDatabase ended successfully");
    return true;
}

function deleteBackupFiles(): string
{
    global $backupFolder, $logger;
    $logger->debug("deleteBackupFiles started");

    if (!isset($_POST['FilenamesWithHashes'])) {
        $errorMessage = 'No FilenamesWithHashes parameter gevonden';
        $logger->warning("deleteBackupFiles aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    $filenamesWithHashes = json_decode($_POST['FilenamesWithHashes'], true);

    if (!is_dir($backupFolder)) {
        $errorMessage = 'Back-up folder not found';
        $logger->warning("deleteBackupFiles aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    if (!is_array($filenamesWithHashes)) {
        $errorMessage = 'Invalid input, expected JSON-array';
        $logger->warning("deleteBackupFiles aborted with error: $errorMessage}");
        throw new Exception($errorMessage, 500);
    }

    foreach ($filenamesWithHashes as $filenameWithHash) {
        if (!isset($filenameWithHash['name'], $filenameWithHash['hash'])) {
            $errorMessage = 'invalid input';
            $logger->warning("deleteBackupFiles aborted with error: $errorMessage}");
            throw new Exception($errorMessage, 500);
        }

        $filename = $backupFolder . '/' . basename($filenameWithHash['name']); // beveiliging: strip path traversal

        if (!is_file($filename)) {
            $errorMessage = 'file not found';
            $logger->warning("deleteBackupFiles aborted with error: $errorMessage}");
            throw new Exception($errorMessage, 500);
        }

        if (!is_writable($filename)) {
            $errorMessage = "{$filename}: Insufficient permissions to delete file";
            $logger->warning("deleteBackupFiles aborted with error: $errorMessage}");
            throw new Exception($errorMessage, 500);
        }

        $currentHash = getHash($filename);

        if ($currentHash !== $filenameWithHash['hash']) {
            $errorMessage = "{$filename}: hash mismatch";
            $logger->warning("deleteBackupFiles aborted with error: $errorMessage}");
            throw new Exception($errorMessage, 500);
            }

        if (!@unlink($filename)) {
                $errorMessage = "{$filename}: delete failed";
                $logger->warning("deleteBackupFiles aborted with error: $errorMessage}");
                throw new Exception($errorMessage, 500);
        }
    }
    $logger->debug("deleteBackupFiles ended successfully");    
    return 'Deletion OK';
}