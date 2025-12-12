<?php
// 30/11/2025 : Added logger

namespace Psr\Log;

interface LoggerInterface
{
    public function emergency(string $message, array $context = []);
    public function alert(string $message, array $context = []);
    public function critical(string $message, array $context = []);
    public function error(string $message, array $context = []);
    public function warning(string $message, array $context = []);
    public function notice(string $message, array $context = []);
    public function info(string $message, array $context = []);
    public function debug(string $message, array $context = []);
    public function log(string $level, string $message, array $context = []);
}
