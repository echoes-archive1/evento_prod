<?php
/**
 * Enhanced Error Logger
 * Provides structured logging with context
 */

class ErrorLogger {
    private static $logFile;
    private static $maxLogSize = 5242880; // 5MB
    
    public static function init() {
        self::$logFile = __DIR__ . '/../logs/error.log';
        
        // Ensure log directory exists
        $logDir = dirname(self::$logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Rotate log if too large
        if (file_exists(self::$logFile) && filesize(self::$logFile) > self::$maxLogSize) {
            self::rotateLogs();
        }
    }
    
    /**
     * Log error with context
     */
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Log warning
     */
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log info
     */
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log debug information
     */
    public static function debug($message, $context = []) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            self::log('DEBUG', $message, $context);
        }
    }
    
    /**
     * Main logging method
     */
    private static function log($level, $message, $context = []) {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $userId = $_SESSION['user_id'] ?? 'guest';
        $ip = self::getClientIP();
        $url = $_SERVER['REQUEST_URI'] ?? 'CLI';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'user_id' => $userId,
            'ip' => $ip,
            'url' => $url,
            'context' => $context
        ];
        
        // Format log entry
        $formattedLog = sprintf(
            "[%s] [%s] [User:%s] [IP:%s] %s\n",
            $timestamp,
            $level,
            $userId,
            $ip,
            $message
        );
        
        // Add context if provided
        if (!empty($context)) {
            $formattedLog .= "Context: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
        }
        
        // Write to file
        error_log($formattedLog, 3, self::$logFile);
        
        // Also log to PHP error log for critical errors
        if ($level === 'ERROR') {
            error_log($message);
        }
    }
    
    /**
     * Rotate log files
     */
    private static function rotateLogs() {
        $backupFile = self::$logFile . '.' . date('Y-m-d-His') . '.bak';
        if (file_exists(self::$logFile)) {
            rename(self::$logFile, $backupFile);
        }
        
        // Keep only last 5 backup files
        $logDir = dirname(self::$logFile);
        $backups = glob($logDir . '/error.log.*.bak');
        if (count($backups) > 5) {
            rsort($backups);
            $toDelete = array_slice($backups, 5);
            foreach ($toDelete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIP() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return 'UNKNOWN';
    }
    
    /**
     * Log database query errors
     */
    public static function logDatabaseError($query, $error, $params = []) {
        self::error('Database Query Failed', [
            'query' => $query,
            'error' => $error,
            'params' => $params
        ]);
    }
    
    /**
     * Log authentication attempts
     */
    public static function logAuthAttempt($email, $success, $reason = '') {
        $level = $success ? 'INFO' : 'WARNING';
        $message = $success ? "Successful login: {$email}" : "Failed login attempt: {$email} - {$reason}";
        self::log($level, $message, ['email' => $email, 'success' => $success]);
    }
    
    /**
     * Get recent logs
     */
    public static function getRecentLogs($lines = 100) {
        self::init();
        
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $file = new SplFileObject(self::$logFile, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key() + 1;
        
        $startLine = max(0, $totalLines - $lines);
        $logs = [];
        
        $file->seek($startLine);
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $logs[] = $line;
            }
            $file->next();
        }
        
        return array_reverse($logs);
    }
}

// Initialize on include
ErrorLogger::init();
