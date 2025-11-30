<?php
// 30/11/2025 : Added logger

// Config laden
require_once __DIR__ . '/../config.php';

// PSR-3 interfaces
require_once __DIR__ . '/Psr/Log/LoggerInterface.php';
require_once __DIR__ . '/Psr/Log/LogLevel.php';

// NCAdmin logger
require_once __DIR__ . '/logger.php';

// Logger initialiseren
Logger::init($CONFIG);

// Één gedeeld logger object
$logger = new Logger();
