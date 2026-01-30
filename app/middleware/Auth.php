<?php
/**
 * Authentication Middleware
 * Role-Based Access Control
 */

class Auth {
    
    /**
     * Check if user is logged in
     */
    public static function check() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user ID
     */
    public static function id() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user data
     */
    public static function user() {
        if (!self::check()) {
            return null;
        }
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT u.*, GROUP_CONCAT(r.role_name) as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.id = :user_id AND u.is_active = 1
            GROUP BY u.id
        ");
        $stmt->execute(['user_id' => self::id()]);
        return $stmt->fetch();
    }
    
    /**
     * Check if user has specific role
     */
    public static function hasRole($role) {
        $user = self::user();
        if (!$user || empty($user['roles'])) {
            return false;
        }
        
        $user_roles = explode(',', $user['roles']);
        return in_array($role, $user_roles);
    }
    
    /**
     * Check if user has any of the specified roles
     */
    public static function hasAnyRole($roles) {
        foreach ($roles as $role) {
            if (self::hasRole($role)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    /**
     * Require authentication
     */
    public static function requireAuth() {
        if (!self::check()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
        
        // Check session timeout
        if (!checkSessionTimeout()) {
            self::logout();
            header('Location: ' . BASE_URL . '/login.php?timeout=1');
            exit;
        }
    }
    
    /**
     * Require specific role
     */
    public static function requireRole($role) {
        self::requireAuth();
        
        if (!self::hasRole($role)) {
            http_response_code(403);
            die('Access Denied: Insufficient permissions');
        }
    }
    
    /**
     * Require any of the specified roles
     */
    public static function requireAnyRole($roles) {
        self::requireAuth();
        
        if (!self::hasAnyRole($roles)) {
            http_response_code(403);
            die('Access Denied: Insufficient permissions');
        }
    }
    
    /**
     * Login user
     */
    public static function login($user_id) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user_id;
        $_SESSION['LAST_ACTIVITY'] = time();
        
        // Log login
        Security::logAudit($user_id, 'user_login');
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        if (self::check()) {
            Security::logAudit(self::id(), 'user_logout');
        }
        
        session_unset();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    /**
     * Get user's primary role for dashboard routing
     */
    public static function getPrimaryRole() {
        $user = self::user();
        if (!$user || empty($user['roles'])) {
            return null;
        }
        
        $roles = explode(',', $user['roles']);
        
        // Priority order: admin > club_leader > authority > hod > faculty > student
        $priority = ['admin', 'club_leader', 'authority', 'hod', 'faculty', 'student'];
        
        foreach ($priority as $role) {
            if (in_array($role, $roles)) {
                return $role;
            }
        }
        
        return $roles[0];
    }
    
    /**
     * Redirect to appropriate dashboard
     */
    public static function redirectToDashboard() {
        $role = self::getPrimaryRole();
        
        $dashboards = [
            'admin' => '/admin/dashboard.php',
            'club_leader' => '/club-leader/dashboard.php',
            'authority' => '/faculty/dashboard.php',
            'hod' => '/faculty/dashboard.php',
            'faculty' => '/faculty/dashboard.php',
            'student' => '/student/dashboard.php'
        ];
        
        $redirect = $dashboards[$role] ?? '/student/dashboard.php';
        header('Location: ' . BASE_URL . $redirect);
        exit;
    }
}
