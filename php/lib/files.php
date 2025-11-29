<?php
// 06/11/2025 : Content Type in header set to 'application/json' so all functions should return string or array, no JSON
// 06/11/2025 : Require_once added
// 06/11/2025 : Response code changed from 404 to 500
// 26/11/2025 : Removed try/catch from all functions
// 29/11/2025 : Moved php to lib

require_once __DIR__ . '/general.php';

function removeFile($filename): string {
    if (!is_file($filename)) {
        throw new Exception('File is not a valid file', 500);
    }

    if (!is_writable($filename)) {
        throw new Exception('Insufficient permissions to delete file', 500);
    }

    if (!unlink($filename)) {
        throw new Exception('Failed to delete file', 500);
    }

    return true;
}

function listFiles($folder): array | string
{
    if (!is_dir($folder)) {
        throw new Exception('Folder not found', 500);
    }

    $filenames = array_filter(glob("$folder/*"), 'is_file');
    
    $result = array_map(fn($filename) => [
        'name' => basename($filename),
        'hash' => getHash($filename),
    ], $filenames);

    return $result;
}