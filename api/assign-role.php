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
$role_id = (int)($input['role_id'] ?? 0);

if (!$user_id || !$role_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if user exists
    $user_sql = "SELECT full_name FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_sql);
    $user_stmt->execute(['user_id' => $user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Check if role exists
    $role_sql = "SELECT role_name FROM roles WHERE id = :role_id";
    $role_stmt = $db->prepare($role_sql);
    $role_stmt->execute(['role_id' => $role_id]);
    $role = $role_stmt->fetch();
    
    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'Role not found']);
        exit;
    }
    
    // Check if already assigned
    $check_sql = "SELECT COUNT(*) as count FROM user_roles WHERE user_id = :user_id AND role_id = :role_id";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute(['user_id' => $user_id, 'role_id' => $role_id]);
    $exists = $check_stmt->fetch()['count'];
    
    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'Role already assigned to this user']);
        exit;
    }
    
    // Assign role
    $sql = "INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (:user_id, :role_id, :admin_id)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'user_id' => $user_id,
        'role_id' => $role_id,
        'admin_id' => Auth::id()
    ]);
    
    // Log audit
    Security::logAudit(Auth::id(), 'role_assigned', 'user_roles', $user_id, "Assigned role '{$role['role_name']}' to {$user['full_name']}");
    
    echo json_encode([
        'success' => true,
        'message' => "Role '{$role['role_name']}' assigned successfully"
    ]);
    
} catch (Exception $e) {
    error_log("Assign role error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to assign role']);
}
?>
