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
$event_id = $input['event_id'] ?? null;
$action = $input['action'] ?? null; // 'approve' or 'reject'
$reason = $input['reason'] ?? null;

if (!$event_id || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

if (!in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $admin_id = Auth::id();
    
    // Get event details
    $event_sql = "SELECT * FROM events WHERE id = :event_id";
    $event_stmt = $db->prepare($event_sql);
    $event_stmt->execute(['event_id' => $event_id]);
    $event = $event_stmt->fetch();
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    
    // Update event status
    $new_status = $action === 'approve' ? 'approved' : 'rejected';
    
    $update_sql = "UPDATE events SET 
                   status = :status, 
                   approved_by = :approved_by,
                   approved_at = NOW(),
                   rejection_reason = :reason
                   WHERE id = :event_id";
    
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->execute([
        'status' => $new_status,
        'approved_by' => $admin_id,
        'reason' => $action === 'reject' ? $reason : null,
        'event_id' => $event_id
    ]);
    
    // Log action
    Security::logAudit($admin_id, "event_{$action}", 'events', $event_id, 
        ['status' => $event['status']], 
        ['status' => $new_status]
    );
    
    $message = $action === 'approve' 
        ? 'Event approved successfully!' 
        : 'Event rejected successfully!';
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    error_log("Event approval error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Action failed. Please try again.']);
}
