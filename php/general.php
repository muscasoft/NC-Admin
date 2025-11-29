<?php
// 06/11/2025 : Require_once added
// 28/11/2025 : $CONFIG changed into static variable; new loadConfigFile() function
// 29/11/2025 : Moved functions getCONFIG and getStepPattern from general.php to nextcloud.php

function getHash($filename): string
{
    $name = basename($filename);
    $size = filesize($filename);
    $mtime = filemtime($filename);
    return hash('sha256', "$name|$size|$mtime");
}