<?php
// 30/11/2025 : Added logger
// 12/12/2025 : Added new functions getInstance() en setInstance and many minor optimizations

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Application-wide PSR-3 compatible logger.
 *
 * Only instantiated once inside bootstrap.php:
 *   Logger::setInstance(new Logger([...]));
 *
 * Afterwards retrieved globally:
 *   Logger::getInstance()->info("...");
 */
class Logger implements LoggerInterface
{
    /** @var Logger|null The global logger instance */
    private static ?Logger $instance = null;

   /** @var bool Whether initialization is complete */
    private bool $initialized = false;

    /** @var bool Enable debug-level logging */
    private bool $debugEnabled = false;

    /** @var string Path to the log file */
    private string $logFile;

    /** @var string Timestamp format for log entries */
    private string $timestampFormat;

    /** @var string Line formatter for text output */
    private string $formatter;

    /** @var string Output mode ('text' or 'json') */
    private string $mode;

    /** @var string Minimum log level to output */
    private string $level;

    /** @var string Unique request ID */
    private string $requestId;

    // ---------------- Timestamp ----------------
    /**
     * Cached DateTimeImmutable object for generating timestamps.
     *
     * @var \DateTimeImmutable
     */
    private \DateTimeImmutable $timestampObj;

    /** 
     * Numeric mapping of PSR-3 log levels
     * @var array<string,int>
     */
    private readonly array $numericLevels;

    /** @var bool Whether to mirror log entries to PHP error_log */
    private bool $mirrorToErrorLog = false;

    // -----------------------------------------------------------------------
    //  Instance registration + access
    // -----------------------------------------------------------------------

    /**
     * Registers a fully initialized Logger instance.
     *
     * Must be called once in bootstrap.php.
     *
     * @param Logger $logger
     */
    public static function setInstance(Logger $logger): void
    {
        self::$instance = $logger;
    }

    /**
     * Returns the globally registered logger instance.
     *
     * @return Logger
     * @throws \RuntimeException If bootstrap forgot to call setInstance()
     */
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            throw new \RuntimeException(
                "Logger not initialized. Call Logger::setInstance() in bootstrap.php."
            );
        }
        return self::$instance;
    }

    // -----------------------------------------------------------------------
    //  Construction + initialization
    // -----------------------------------------------------------------------

    /**
     * Public constructor so bootstrap.php can create the logger once.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->init($config);
    }

    /** Private clone to prevent duplication */
    private function __clone() {}

    // ---------------- Initialization ----------------

    /**
     * Initializes logger configuration.
     *
     * @param array $config Configuration options (see getInstance)
     * @return void
     */
    public function init(array $config = []): void
    {
        if ($this->initialized) return;

        $this->initialized = true;
        $this->debugEnabled = !empty($config['debug']);

        $prefix = $config['requestIdPrefix'] ?? '';

        $this->requestId = $prefix . substr(sha1(microtime(true) . bin2hex(random_bytes(4))), 0, 8);
        $this->logFile = $config['logFile'] ?? (__DIR__ . '/../logs/ncadmin.log');
        $this->timestampFormat = $config['timestampFormat'] ?? 'Y-m-d H:i:s';
        $this->formatter = $config['formatter'] 
            ?? '[{timestamp}] [{requestId}] [{level}] {message}{context}';
        $this->level = strtolower($config['level'] ?? 'info');
        $this->mode = strtolower($config['mode'] ?? 'text');
        $this->timestampObj = new \DateTimeImmutable();
        $this->numericLevels = self::levels();
        $this->mirrorToErrorLog = !empty($config['mirrorToErrorLog']);

        $dir = dirname($this->logFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Unable to create log directory: $dir");
        }

        $this->rotateIfNeeded();
    }

    // ---------------- Logging ----------------

    /**
     * Logs a message at the given level.
     *
     * Interpolates context values according to PSR-3.
     *
     * @param string $level Log level (debug, info, notice, warning, error, etc.)
     * @param string $message Message to log
     * @param array $context Optional array of context values
     * @return void
     * @throws \InvalidArgumentException if invalid log level
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->initialized) {
            $this->init([]);
        }

        $level = strtolower($level);

        if (!isset(self::levels()[$level])) {
            throw new \InvalidArgumentException("Invalid log level: $level");
        }
        
        if (!$this->isLoggable($level)) return;

        // interpolate message (PSR-3 compliant)
        $message = $this->interpolate($message, $context);

        $this->timestampObj = $this->timestampObj ->setTimestamp(time());
        $timestamp = $this->timestampObj->format($this->timestampFormat);
        $line = $this->formatLine($timestamp, $level, $message, $context);

        if (file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log("Logger failed to write to file {$this->logFile}. Original message: $line");
        }

        if ($this->mirrorToErrorLog) {
            error_log(trim($line));
        }
    }

    // ---------------- Formatting ----------------

    /**
     * Formats a log entry as text or JSON.
     *
     * @param string $timestamp Formatted timestamp
     * @param string $level Log level
     * @param string $message Interpolated message
     * @param array $context Context array
     * @return string Formatted log line
     */
    private function formatLine(string $timestamp, string $level, string $message, array $context): string
    {
        if ($this->mode === 'json') {
            return json_encode([
                'timestamp' => $timestamp,
                'requestId' => $this->requestId,
                'level'     => $level,
                'message'   => $message,
                'context'   => $context ?: null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }

        $ctx = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);

        return strtr($this->formatter, [
            '{timestamp}' => $timestamp,
            '{requestId}' => $this->requestId,
            '{level}'     => $level,
            '{message}'   => $message,
            '{context}'   => $ctx,
        ]) . PHP_EOL;
    }

    // ---------------- Interpolation ----------------

    /**
     * Replaces placeholders in the message with context values.
     *
     * @param string $message Message with placeholders
     * @param array $context Context values
     * @return string Interpolated message
     */
    private function interpolate(string $message, array $context): string
    {
        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable) $context[$key] = $value->getMessage();
            elseif (is_array($value) || is_object($value)) {
                try {
                    $context[$key] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                } catch (\Throwable $e) {
                    $context[$key] = '[unencodable]';
                }
            }
        }
        return strtr($message, array_map(fn($v) => (string)$v, $context));
    }


    // ---------------- Level Checking ----------------
    /**
     * Determines if a given log level should be written according to configuration.
     *
     * Combines two checks:
     * 1. Ensures the log level is enabled according to the minimum configured level.
     * 2. If the level is DEBUG, ensures debug logging is enabled.
     *
     * @param string $level The PSR-3 log level to check (e.g., 'debug', 'info', 'error').
     * @return bool True if the message should be logged, false otherwise.
     */
    private function isLoggable(string $level): bool {
        if (!isset($this->numericLevels[$level])) return false;
        if ($level === LogLevel::DEBUG && !$this->debugEnabled) return false;
        return $this->numericLevels[$level] >= $this->numericLevels[$this->level];
    }

    /**
     * Returns numeric mapping of PSR-3 log levels.
     *
     * @return array<string,int> Level => weight
     */
    private static function levels(): array
    {
        static $levels = [
            LogLevel::DEBUG     => 0,
            LogLevel::INFO      => 1,
            LogLevel::NOTICE    => 2,
            LogLevel::WARNING   => 3,
            LogLevel::ERROR     => 4,
            LogLevel::CRITICAL  => 5,
            LogLevel::ALERT     => 6,
            LogLevel::EMERGENCY => 7,
        ];
        return $levels;
    }

    // ---------------- Log Rotation ----------------

    /**
     * Rotates the log file if it exceeds the maximum size (5MB).
     *
     * @return void
     */
    private function rotateIfNeeded(): void
    {
        $maxSize = 5 * 1024 * 1024;
        if (file_exists($this->logFile) && is_writable($this->logFile) && filesize($this->logFile) > $maxSize) {
            $backup = $this->logFile . '.' . date('Ymd_His');
            rename($this->logFile, $backup);
            foreach (glob($this->logFile . '.*') as $file) {
                if (filemtime($file) < strtotime('-30 days')) unlink($file);
            }
        }

    }

    // ---------------- Shortcut Methods ----------------

    /**
     * Convenience methods for each PSR-3 level
     */
    public function emergency(string $msg, array $ctx = []) { $this->log(LogLevel::EMERGENCY, $msg, $ctx); }
    public function alert(string $msg, array $ctx = [])     { $this->log(LogLevel::ALERT,     $msg, $ctx); }
    public function critical(string $msg, array $ctx = [])  { $this->log(LogLevel::CRITICAL,  $msg, $ctx); }
    public function error(string $msg, array $ctx = [])     { $this->log(LogLevel::ERROR,     $msg, $ctx); }
    public function warning(string $msg, array $ctx = [])   { $this->log(LogLevel::WARNING,   $msg, $ctx); }
    public function notice(string $msg, array $ctx = [])    { $this->log(LogLevel::NOTICE,    $msg, $ctx); }
    public function info(string $msg, array $ctx = [])      { $this->log(LogLevel::INFO,      $msg, $ctx); }
    public function debug(string $msg, array $ctx = [])     { $this->log(LogLevel::DEBUG,     $msg, $ctx); }
}