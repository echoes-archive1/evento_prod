<?php
/**
 * Google OAuth Callback Handler
 * Processes Google Sign-In responses
 */

// Start session first, before any includes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers/GoogleAuth.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

// Log callback for debugging
error_log("Google OAuth callback received. Session ID: " . session_id());
error_log("GET params: " . print_r($_GET, true));

// Check for error from Google
if (isset($_GET['error'])) {
    $_SESSION['flash_error'] = 'Google login was cancelled or failed';
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Verify state token for CSRF protection
$state = $_GET['state'] ?? '';
if (!GoogleAuth::verifyState($state)) {
    $_SESSION['flash_error'] = 'Invalid state token. Please try again.';
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Get authorization code
$code = $_GET['code'] ?? '';
if (empty($code)) {
    $_SESSION['flash_error'] = 'No authorization code received';
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

try {
    // Exchange code for access token
    $access_token = GoogleAuth::getAccessToken($code);
    
    if (!$access_token) {
        throw new Exception('Failed to get access token');
    }
    
    // Get user info from Google
    $google_user = GoogleAuth::getUserInfo($access_token);
    
    if (!$google_user) {
        throw new Exception('Failed to get user information');
    }
    
    // Handle authentication (login or registration)
    $result = GoogleAuth::handleAuthentication($google_user);
    
    if ($result['success']) {
        if ($result['redirect'] === 'complete-profile') {
            header('Location: ' . BASE_URL . '/complete-profile.php');
        } else {
            Auth::redirectToDashboard();
        }
    } else {
        $_SESSION['flash_error'] = $result['message'];
        header('Location: ' . BASE_URL . '/login.php');
    }
    
} catch (Exception $e) {
    error_log("Google callback error: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Google login failed. Please try again.';
    header('Location: ' . BASE_URL . '/login.php');
}

exit();
