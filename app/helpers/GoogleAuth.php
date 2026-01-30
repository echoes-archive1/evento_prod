<?php
/**
 * Google OAuth Helper
 * Handles Google Sign-In authentication
 */

class GoogleAuth {
    private static $client_id;
    private static $client_secret;
    private static $redirect_uri;
    private static $allowed_domains = ['charusat.edu.in', 'charusat.ac.in'];
    
    public static function init() {
        self::$client_id = GOOGLE_CLIENT_ID;
        self::$client_secret = GOOGLE_CLIENT_SECRET;
        self::$redirect_uri = BASE_URL . '/api/google-callback.php';
    }
    
    /**
     * Get Google OAuth login URL
     */
    public static function getLoginUrl() {
        self::init();
        
        $params = [
            'client_id' => self::$client_id,
            'redirect_uri' => self::$redirect_uri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'access_type' => 'online',
            'state' => self::generateState()
        ];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Generate and store state token for CSRF protection
     */
    private static function generateState() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $state = bin2hex(random_bytes(32));
        $_SESSION['google_oauth_state'] = $state;
        $_SESSION['google_oauth_timestamp'] = time();
        
        // Log for debugging
        error_log("Generated OAuth state: " . $state);
        error_log("Session ID when generating state: " . session_id());
        error_log("Session save path: " . session_save_path());
        
        return $state;
    }
    
    /**
     * Verify state token
     */
    public static function verifyState($state) {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Log for debugging
        error_log("Verifying state. Received: " . $state . ", Session: " . ($_SESSION['google_oauth_state'] ?? 'NOT SET'));
        
        if (!isset($_SESSION['google_oauth_state'])) {
            error_log("OAuth state not found in session");
            return false;
        }
        
        // Check if state is expired (10 minutes)
        if (isset($_SESSION['google_oauth_timestamp'])) {
            $age = time() - $_SESSION['google_oauth_timestamp'];
            if ($age > 600) { // 10 minutes
                error_log("OAuth state expired (age: {$age} seconds)");
                unset($_SESSION['google_oauth_state']);
                unset($_SESSION['google_oauth_timestamp']);
                return false;
            }
        }
        
        $valid = hash_equals($_SESSION['google_oauth_state'], $state);
        
        // Only clear on success to allow retry on failure
        if ($valid) {
            unset($_SESSION['google_oauth_state']);
            unset($_SESSION['google_oauth_timestamp']);
            error_log("OAuth state verified successfully");
        } else {
            error_log("OAuth state mismatch");
        }
        
        return $valid;
    }
    
    /**
     * Exchange authorization code for access token
     */
    public static function getAccessToken($code) {
        self::init();
        
        $token_url = 'https://oauth2.googleapis.com/token';
        
        $params = [
            'code' => $code,
            'client_id' => self::$client_id,
            'client_secret' => self::$client_secret,
            'redirect_uri' => self::$redirect_uri,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            error_log("Google token exchange failed: " . $response);
            return false;
        }
        
        $data = json_decode($response, true);
        return $data['access_token'] ?? false;
    }
    
    /**
     * Get user info from Google
     */
    public static function getUserInfo($access_token) {
        $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $user_info_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            error_log("Failed to get Google user info: " . $response);
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Validate email domain
     */
    public static function isValidDomain($email) {
        $domain = substr(strrchr($email, "@"), 1);
        return in_array(strtolower($domain), self::$allowed_domains);
    }
    
    /**
     * Clean student ID from name
     * Removes patterns like "23CS054", "24DCS001", etc. from names
     */
    private static function cleanStudentIdFromName($name) {
        // Remove common student ID patterns (e.g., 23CS054, 24DCS001, 21IT099)
        // Pattern: 2 digits + 1-4 uppercase letters + 3 digits
        $name = preg_replace('/\b\d{2}[A-Z]{1,5}\d{3}\b/', '', $name);
        
        // Remove extra spaces and trim
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        
        return $name;
    }
    
    /**
     * Handle Google login/registration
     */
    public static function handleAuthentication($google_user) {
        if (!$google_user || !isset($google_user['email'])) {
            return ['success' => false, 'message' => 'Failed to get user information from Google'];
        }
        
        $email = $google_user['email'];
        $name = $google_user['name'] ?? 'Google User';
        $google_id = $google_user['id'] ?? null;
        
        // Clean student ID from name (e.g., "John Doe 23CS054" -> "John Doe")
        $name = self::cleanStudentIdFromName($name);
        
        // Validate domain
        if (!self::isValidDomain($email)) {
            return [
                'success' => false, 
                'message' => 'Only CHARUSAT email addresses (@charusat.edu.in or @charusat.ac.in) are allowed'
            ];
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if user exists
            $sql = "SELECT id, full_name, email, is_active, email_verified, google_id FROM users WHERE email = :email";
            $stmt = $db->prepare($sql);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // User exists - login
                if (!$user['is_active']) {
                    return ['success' => false, 'message' => 'Your account has been deactivated. Please contact administrator.'];
                }
                
                // Update google_id if not set
                if (empty($user['google_id'])) {
                    $update_sql = "UPDATE users SET google_id = :google_id, email_verified = 1 WHERE id = :user_id";
                    $update_stmt = $db->prepare($update_sql);
                    $update_stmt->execute(['google_id' => $google_id, 'user_id' => $user['id']]);
                }
                
                // Mark email as verified if logged in via Google
                if (!$user['email_verified']) {
                    $verify_sql = "UPDATE users SET email_verified = 1 WHERE id = :user_id";
                    $verify_stmt = $db->prepare($verify_sql);
                    $verify_stmt->execute(['user_id' => $user['id']]);
                }
                
                Auth::login($user['id']);
                Security::logAudit($user['id'], 'login_google', null, null, ['email' => $email]);
                
                return ['success' => true, 'redirect' => 'dashboard'];
                
            } else {
                // New user - redirect to complete profile
                $_SESSION['google_registration'] = [
                    'email' => $email,
                    'name' => $name,
                    'google_id' => $google_id,
                    'picture' => $google_user['picture'] ?? null,
                    'timestamp' => time()
                ];
                
                Security::logAudit(null, 'google_registration_started', null, null, [
                    'email' => $email
                ]);
                
                return ['success' => true, 'redirect' => 'complete-profile'];
            }
            
        } catch (Exception $e) {
            error_log("Google authentication error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Authentication failed. Please try again later.'];
        }
    }
}
