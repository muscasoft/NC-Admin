<?php
// 30/11/2025 : Added logger
// 12/12/2025 : Changed in initializing logger

// Config laden
require_once __DIR__ . '/../config.php';

// PSR-3 interfaces
require_once __DIR__ . '/Psr/Log/LoggerInterface.php';
require_once __DIR__ . '/Psr/Log/LogLevel.php';

// NCAdmin logger
require_once __DIR__ . '/logger.php';

// Logger initialize
Logger::setInstance(
    new Logger($CONFIG)
);

$logger = Logger::getInstance();
