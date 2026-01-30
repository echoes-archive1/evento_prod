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

// Don't delete admin or self
if ($user_id == 1 || $user_id == Auth::id()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot delete this user']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();
    
    // Get user name for logging
    $user_sql = "SELECT full_name FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_sql);
    $user_stmt->execute(['user_id' => $user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Delete user roles
    $sql = "DELETE FROM user_roles WHERE user_id = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    
    // Delete event registrations
    $sql = "DELETE FROM event_registrations WHERE user_id = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    
    // Update events created by this user (set created_by to NULL or keep for history)
    $sql = "UPDATE events SET created_by = NULL WHERE created_by = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    
    // Delete user
    $sql = "DELETE FROM users WHERE id = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    
    $db->commit();
    
    // Log audit
    Security::logAudit(Auth::id(), 'user_deleted', 'users', $user_id, "Deleted user: {$user['full_name']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully'
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Delete user error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
}
?>
