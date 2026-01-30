<?php
/**
 * Global Configuration
 * Security & Application Settings
 */

// Error Reporting (Disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disabled for production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.cookie_samesite', 'Lax'); // Changed from Strict to Lax for OAuth compatibility
ini_set('session.cookie_lifetime', 0); // Session cookie expires when browser closes
ini_set('session.gc_maxlifetime', 3600); // 1 hour

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering to rewrite asset paths for Railway
ob_start();

// Detect if we're in production (Railway or HTTPS) 
$isProduction = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false ||
                strpos($_SERVER['HTTP_HOST'] ?? '', '.up.railway.app') !== false;

// Auto-rewrite old asset paths to new ones if in production
if ($isProduction) {
    register_shutdown_function(function() {
        $output = ob_get_clean();
        // Replace old paths with new ones
        $output = str_replace(
            [BASE_URL . '/public/css/', BASE_URL . '/public/js/'],
            [BASE_URL . '/css/', BASE_URL . '/js/'],
            $output
        );
        echo $output;
    });
} else {
    ob_end_clean();
}

// Application Constants
define('APP_NAME', 'Evento - College Event Management');
define('APP_VERSION', '1.1.0'); // Updated with public landing page + auto-registration flow
define('BASE_URL', $_ENV['BASE_URL'] ?? getenv('BASE_URL') ?: 'http://localhost/evento'); // Use https:// in production
define('UPLOAD_PATH', __DIR__ . '/../public/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Pagination
define('ITEMS_PER_PAGE', 12);

// File Upload Settings
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg', 'image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Email Configuration
define('MAIL_FROM_ADDRESS', 'hitanshpparikh@gmail.com');
define('MAIL_FROM_NAME', 'Evento - Event Management');
define('MAIL_USE_SMTP', true); // true = Use SMTP for better deliverability
define('MAIL_HOST', 'smtp.gmail.com'); // Only used if MAIL_USE_SMTP = true
define('MAIL_PORT', 587); // Only used if MAIL_USE_SMTP = true
define('MAIL_USERNAME', 'hitanshpparikh@gmail.com'); // Only used if MAIL_USE_SMTP = true
define('MAIL_PASSWORD', 'pqjy evfz vfjz gker'); // Gmail App Password (only for SMTP mode)
define('MAIL_ENCRYPTION', 'tls'); // Only used if MAIL_USE_SMTP = true
define('ENABLE_EMAIL_VERIFICATION', true); // Set to false to disable email verification

// Google OAuth Configuration - ENABLED
// Redirect URI: http://localhost/evento/api/google-callback.php
define('GOOGLE_CLIENT_ID', '945308508206-v5633glmm5kpjhd47uj90g1vaof99r5g.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');

// Generate CSRF Token
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Get asset URL (for CSS/JS files)
function assetUrl($path) {
    $base = rtrim(BASE_URL, '/');
    return $base . '/' . ltrim($path, '/');
}

// Verify CSRF Token
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Check session timeout
function checkSessionTimeout() {
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['LAST_ACTIVITY'] = time();
    return true;
}

// Auto-load required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../app/helpers/Security.php';
require_once __DIR__ . '/../app/helpers/Validator.php';
require_once __DIR__ . '/../app/helpers/Email.php';
require_once __DIR__ . '/../app/helpers/GoogleAuth.php';
