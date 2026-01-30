<?php
/**
 * Database Configuration
 * Production-Ready with Error Handling
 */

// Railway Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'evento_db');
define('DB_PORT', $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');
class Database {
    private static $instance = null;
    private $connection;
    private $reconnectAttempts = 0;
    private const MAX_RECONNECT_ATTEMPTS = 3;
    
    private function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::ATTR_TIMEOUT => 5, // 5 seconds connection timeout
                PDO::ATTR_PERSISTENT => false, // Disable persistent connections for better stability
                PDO::MYSQL_ATTR_FOUND_ROWS => true
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->reconnectAttempts = 0; // Reset on successful connection
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            
            // Attempt reconnection for specific errors
            if ($this->shouldRetry($e) && $this->reconnectAttempts < self::MAX_RECONNECT_ATTEMPTS) {
                $this->reconnectAttempts++;
                sleep(1); // Wait 1 second before retry
                error_log("Attempting database reconnection (Attempt {$this->reconnectAttempts}/" . self::MAX_RECONNECT_ATTEMPTS . ")");
                $this->connect();
            } else {
                // Show user-friendly error message
                $this->displayDatabaseError($e);
            }
        }
    }
    
    private function shouldRetry($exception) {
        $retryableCodes = [2002, 2006]; // Connection refused, MySQL server has gone away
        foreach ($retryableCodes as $code) {
            if (strpos($exception->getMessage(), (string)$code) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function displayDatabaseError($exception) {
        // Check if we're in API endpoint
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json');
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please try again later.'
            ]);
            exit;
        }
        
        // For web pages, show friendly error
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Database Connection Error - Evento</title>
            <style>
                body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
                .error-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; max-width: 500px; }
                h1 { color: #dc3545; margin-bottom: 20px; }
                p { color: #666; line-height: 1.6; }
                .btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; }
                .btn:hover { background: #5568d3; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1>🔌 Database Connection Error</h1>
                <p>We're having trouble connecting to the database. This might be temporary.</p>
                <p><strong>Please try again in a few moments.</strong></p>
                <a href="javascript:location.reload()" class="btn">Retry Connection</a>
                <p style="margin-top: 20px; font-size: 0.9em; color: #999;">If the problem persists, please contact support.</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        // Check if connection is still alive
        try {
            $this->connection->query('SELECT 1');
        } catch (PDOException $e) {
            // Connection lost, attempt to reconnect
            error_log("Database connection lost, attempting to reconnect...");
            $this->reconnectAttempts = 0;
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * Test database connection
     * @return bool
     */
    public function testConnection() {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            error_log("Database connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get database statistics
     * @return array
     */
    public function getStats() {
        try {
            $stmt = $this->connection->query("SHOW STATUS WHERE Variable_name IN ('Threads_connected', 'Max_used_connections', 'Uptime')");
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            error_log("Failed to get database stats: " . $e->getMessage());
            return [];
        }
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
