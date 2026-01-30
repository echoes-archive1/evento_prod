<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';
require_once __DIR__ . '/../app/helpers/Security.php';

header('Content-Type: application/json');

// Require authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$event_id = $input['event_id'] ?? null;

if (!$event_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $user_id = Auth::id();
    
    // Check if event exists
    $event_sql = "SELECT * FROM events WHERE id = :event_id";
    $event_stmt = $db->prepare($event_sql);
    $event_stmt->execute(['event_id' => $event_id]);
    $event = $event_stmt->fetch();
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    
    // Check if user is registered
    $check_sql = "SELECT id FROM event_registrations WHERE event_id = :event_id AND user_id = :user_id";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute(['event_id' => $event_id, 'user_id' => $user_id]);
    $registration = $check_stmt->fetch();
    
    if (!$registration) {
        echo json_encode(['success' => false, 'message' => 'You are not registered for this event']);
        exit;
    }
    
    // Check if event has already occurred
    if (strtotime($event['event_date']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Cannot unregister from past events']);
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Delete registration
    $delete_sql = "DELETE FROM event_registrations WHERE event_id = :event_id AND user_id = :user_id";
    $delete_stmt = $db->prepare($delete_sql);
    $delete_stmt->execute(['event_id' => $event_id, 'user_id' => $user_id]);
    
    // Decrease event participant count
    $update_sql = "UPDATE events SET current_participants = GREATEST(current_participants - 1, 0) WHERE id = :event_id";
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->execute(['event_id' => $event_id]);
    
    // Log unregistration
    Security::logAudit($user_id, 'event_unregistration', 'event_registrations', $registration['id'], null, [
        'event_id' => $event_id,
        'event_name' => $event['event_name']
    ]);
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully unregistered from the event.'
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Event unregistration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unregistration failed. Please try again.']);
}
