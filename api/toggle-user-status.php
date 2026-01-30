<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

header('Content-Type: application/json');

// Require admin role
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$user_id = (int)($input['user_id'] ?? 0);

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Don't deactivate self
if ($user_id == Auth::id()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot deactivate your own account']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get current status
    $status_sql = "SELECT is_active, full_name FROM users WHERE id = :user_id";
    $status_stmt = $db->prepare($status_sql);
    $status_stmt->execute(['user_id' => $user_id]);
    $user = $status_stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Toggle status
    $sql = "UPDATE users SET is_active = NOT is_active WHERE id = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    
    $new_status = $user['is_active'] ? 'deactivated' : 'activated';
    
    // Log audit
    Security::logAudit(Auth::id(), 'user_status_toggle', 'users', $user_id, "User {$new_status}: {$user['full_name']}");
    
    echo json_encode([
        'success' => true,
        'message' => "User {$new_status} successfully"
    ]);
    
} catch (Exception $e) {
    error_log("Toggle user status error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
}
?>
