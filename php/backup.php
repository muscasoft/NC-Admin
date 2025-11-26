<?php
// 06/11/2025 : Content Type in header set to 'application/json' so all functions should return string or array, no JSON
// 06/11/2025 : Require_once added
// 06/11/2025 : Response code changed from 404 to 500
// 26/11/2025 : Removed try/catch from all functions

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/general.php';

function getLatestBackupFile(): array | string
{
    global $backupFolder;

    if (!is_dir($backupFolder)) {
        throw new Exception('Back-up folder not found', 500);
    }

    $files = array_filter(glob("$backupFolder/*"), 'is_file');

    if (!$files) {
        throw new Exception('No files found in back-up folder', 500);
    }

    $latestFile = array_reduce($files, function ($fileA, $fileB) {
        if ($fileA === null) return $fileB;
        return filemtime($fileA) > filemtime($fileB) ? $fileA : $fileB;
    }, null);

    if ($latestFile === null) {
        throw new Exception('No valid files found', 500);
    }

    $mtime = filemtime($latestFile);

    return [
        "latest_file"   => basename($latestFile),
        "last_modified" => date("Y-m-d H:i:s", $mtime),
        "timestamp"     => $mtime
    ];        
}

function listBackupFiles(): array | string
{
    global $backupFolder;
    return listFiles($backupFolder);
}

function makeBackupDatabase(): string
{
    global $configFileName, $backupFolder;
    $CONFIG = getCONFIG();

    if (!is_dir($backupFolder)) {
        if (!mkdir($backupFolder, 0755, true)) {
            throw new Exception('Could not create backup directory', 500);
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
        throw new Exception('Backup failed\nOutput:\n' . implode('\n', $output), 500);
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
        $deleteSqlFileIfExist();
        throw new Exception('Compression failed\nOutput:\n' . implode('\n', $tarOutput), 500);
    }

    $deleteSqlFileIfExist();

    return true;
}

function deleteBackupFiles(): string
{
    global $backupFolder;
    if (!isset($_POST['FilenamesWithHashes'])) {
        throw new Exception('No FilenamesWithHashes parameter gevonden', 500);
    }

    $filenamesWithHashes = json_decode($_POST['FilenamesWithHashes'], true);

    if (!is_dir($backupFolder)) {
        throw new Exception('Back-up folder not found', 500);
    }

    if (!is_array($filenamesWithHashes)) {
        throw new Exception('Invalid input, expected JSON-array', 500);
    }

    foreach ($filenamesWithHashes as $filenameWithHash) {
        if (!isset($filenameWithHash['name'], $filenameWithHash['hash'])) {
            throw new Exception('invalid input', 500);
        }

        $filename = $backupFolder . '/' . basename($filenameWithHash['name']); // beveiliging: strip path traversal

        if (!is_file($filename)) {
            throw new Exception("{$filename}: file not found", 500);
        }

        if (!is_writable($filename)) {
            throw new Exception("{$filename}: Insufficient permissions to delete file", 500);
        }

        $currentHash = getHash($filename);

        if ($currentHash !== $filenameWithHash['hash']) {
            throw new Exception("{$filename}: hash mismatch", 500);
        }

        if (!@unlink($filename)) {
            throw new Exception("{$filename}: delete failed", 500);
        }
    }
    
    return 'Deletion OK';
}