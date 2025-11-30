<?php
// 30/11/2025 : Added logger

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Logger implements LoggerInterface
{
    private static bool $initialized = false;
    private static bool $debugEnabled = false;
    private static string $logFile;
    private static string $timestampFormat;
    private static string $formatter;
    private static string $mode;
    private static string $level;
    private static string $requestId;

    private static bool $mirrorToErrorLog = false;

    /**
     * Initialize the logger with configuration.
     */
    public static function init(array $config = []): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        self::$debugEnabled = !empty($config['debug']);

        $prefix = $config['requestIdPrefix'] ?? '';
        self::$requestId = $prefix . substr(sha1(microtime(true) . rand()), 0, 8);

        self::$logFile = $config['logFile'] ?? (__DIR__ . '/../logs/ncadmin.log');
        self::$timestampFormat = $config['timestampFormat'] ?? 'Y-m-d H:i:s';
        self::$formatter = $config['formatter'] 
            ?? '[{timestamp}] [{requestId}] [{level}] {message}{context}';
        self::$level = strtolower($config['level'] ?? 'info');
        self::$mode = strtolower($config['mode'] ?? 'text');
        self::$mirrorToErrorLog = !empty($config['mirrorToErrorLog']);

        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    /**
     * PSR-3 core log function
     */
    public function log($level, $message, array $context = []): void
    {
        if (!self::$initialized) {
            self::init([]);
        }

        $level = strtolower($level);

        if (!in_array($level, array_keys(self::levels()))) {
            throw new \InvalidArgumentException("Invalid log level: $level");
        }

        if (!$this->shouldLogLevel($level)) {
            return;
        }

        if ($level === LogLevel::DEBUG && !self::$debugEnabled) {
            return;
        }

        self::rotateIfNeeded(self::$logFile);

        // interpolate message (PSR-3 compliant)
        $message = $this->interpolate($message, $context);

        $timestamp = date(self::$timestampFormat);
        $requestId = self::$requestId;

        if (self::$mode === 'json') {
            $entry = [
                'timestamp' => $timestamp,
                'requestId' => $requestId,
                'level'     => $level,
                'message'   => $message,
                'context'   => $context ?: null,
            ];

            $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";

        } else {
            $ctx = empty($context) ? "" : " " . json_encode($context, JSON_UNESCAPED_SLASHES);

            $line = str_replace(
                ['{timestamp}', '{requestId}', '{level}', '{message}', '{context}'],
                [$timestamp,   $requestId,      $level,   $message,    $ctx],
                self::$formatter
            );
            $line .= "\n";
        }

        file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);

        if (self::$mirrorToErrorLog) {
            error_log(trim($line));
        }
    }

    /**
     * Interpolate placeholders with context (PSR-3 compliant)
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $value) {

            // 1. Exceptions → gebruik alleen message
            if ($value instanceof \Throwable) {
                $replace['{' . $key . '}'] = $value->getMessage();
                continue;
            }

            // 2. Scalars, null, object met __toString
            if (is_null($value) ||
                is_scalar($value) ||
                (is_object($value) && method_exists($value, '__toString'))
            ) {
                $replace['{' . $key . '}'] = (string)$value;
                continue;
            }

            // 3. arrays en andere objecten → niet interpoleren
        }

        return strtr($message, $replace);
    }

    /**
     * Minimum loglevel comparison
     */
    private function shouldLogLevel(string $level): bool
    {
        $levels = self::levels();
        return $levels[$level] >= $levels[self::$level];
    }

    /**
     * PSR-3 loglevel rankings
     */
    private static function levels(): array
    {
        return [
            LogLevel::DEBUG     => 0,
            LogLevel::INFO      => 1,
            LogLevel::NOTICE    => 2,
            LogLevel::WARNING   => 3,
            LogLevel::ERROR     => 4,
            LogLevel::CRITICAL  => 5,
            LogLevel::ALERT     => 6,
            LogLevel::EMERGENCY => 7,
        ];
    }

    /**
     * Simple max-size rotation (5MB)
     */
    private static function rotateIfNeeded(string $file): void
    {
        $maxSize = 5 * 1024 * 1024;
        if (file_exists($file) && filesize($file) > $maxSize) {
            $backup = $file . '.' . date('Ymd_His');
            rename($file, $backup);
        }
    }

    // PSR-3 loglevel shortcuts
    public function emergency($msg, array $ctx = []) { $this->log(LogLevel::EMERGENCY, $msg, $ctx); }
    public function alert($msg, array $ctx = [])     { $this->log(LogLevel::ALERT,     $msg, $ctx); }
    public function critical($msg, array $ctx = [])  { $this->log(LogLevel::CRITICAL,  $msg, $ctx); }
    public function error($msg, array $ctx = [])     { $this->log(LogLevel::ERROR,     $msg, $ctx); }
    public function warning($msg, array $ctx = [])   { $this->log(LogLevel::WARNING,   $msg, $ctx); }
    public function notice($msg, array $ctx = [])    { $this->log(LogLevel::NOTICE,    $msg, $ctx); }
    public function info($msg, array $ctx = [])      { $this->log(LogLevel::INFO,      $msg, $ctx); }
    public function debug($msg, array $ctx = [])     { $this->log(LogLevel::DEBUG,     $msg, $ctx); }
}
