<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Get user ID from query string
$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(['error' => 'Missing user ID']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check user verification status
    $sql = "SELECT email_verified, email_verified_at, token_expiry FROM users WHERE id = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode([
            'verified' => false,
            'expired' => true,
            'message' => 'User not found'
        ]);
        exit();
    }
    
    // Check if verified
    if ($user['email_verified'] == 1) {
        echo json_encode([
            'verified' => true,
            'expired' => false,
            'verified_at' => $user['email_verified_at']
        ]);
        exit();
    }
    
    // Check if token expired
    $token_expiry = strtotime($user['token_expiry']);
    $now = time();
    
    if ($now > $token_expiry) {
        // Token expired, delete the user
        $delete_sql = "DELETE FROM users WHERE id = :user_id AND email_verified = 0";
        $delete_stmt = $db->prepare($delete_sql);
        $delete_stmt->execute(['user_id' => $user_id]);
        
        echo json_encode([
            'verified' => false,
            'expired' => true,
            'message' => 'Verification token expired'
        ]);
        exit();
    }
    
    // Still waiting for verification
    echo json_encode([
        'verified' => false,
        'expired' => false,
        'time_remaining' => $token_expiry - $now
    ]);
    
} catch (Exception $e) {
    error_log("Check verification error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Server error',
        'message' => 'Failed to check verification status'
    ]);
}
