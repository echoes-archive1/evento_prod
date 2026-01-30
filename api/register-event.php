<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';
require_once __DIR__ . '/../app/helpers/Email.php';
require_once __DIR__ . '/../app/helpers/Security.php';
require_once __DIR__ . '/../app/helpers/QRCode.php';

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
    
    // Check if event exists and is approved
    $event_sql = "SELECT * FROM events WHERE id = :event_id AND status = 'approved'";
    $event_stmt = $db->prepare($event_sql);
    $event_stmt->execute(['event_id' => $event_id]);
    $event = $event_stmt->fetch();
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found or not available']);
        exit;
    }
    
    // Check if registration deadline has passed
    if (strtotime($event['registration_deadline']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Registration deadline has passed']);
        exit;
    }
    
    // Check if event is full
    if ($event['max_participants'] && $event['current_participants'] >= $event['max_participants']) {
        echo json_encode(['success' => false, 'message' => 'Event is full']);
        exit;
    }
    
    // Check if already registered
    $check_sql = "SELECT id FROM event_registrations WHERE event_id = :event_id AND user_id = :user_id";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute(['event_id' => $event_id, 'user_id' => $user_id]);
    
    if ($check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You are already registered for this event']);
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Register for event
    $register_sql = "INSERT INTO event_registrations (event_id, user_id, qr_code) VALUES (:event_id, :user_id, :qr_code)";
    $register_stmt = $db->prepare($register_sql);
    $qr_code = Security::generateToken(16);
    $register_stmt->execute([
        'event_id' => $event_id,
        'user_id' => $user_id,
        'qr_code' => $qr_code
    ]);
    
    // Update event participant count
    $update_sql = "UPDATE events SET current_participants = current_participants + 1 WHERE id = :event_id";
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->execute(['event_id' => $event_id]);
    
    // Log registration
    Security::logAudit($user_id, 'event_registration', 'event_registrations', $db->lastInsertId(), null, [
        'event_id' => $event_id,
        'event_name' => $event['event_name']
    ]);
    
    // Commit transaction
    $db->commit();
    
    // Queue confirmation email for async processing (non-blocking)
    try {
        $user_sql = "SELECT full_name, email FROM users WHERE id = :user_id";
        $user_stmt = $db->prepare($user_sql);
        $user_stmt->execute(['user_id' => $user_id]);
        $user = $user_stmt->fetch();
        
        if ($user) {
            // Queue email asynchronously - don't wait for it
            Email::sendEventRegistrationEmail(
                $user['email'],
                $user['full_name'],
                $event,
                $qr_code
            );
        }
    } catch (Exception $e) {
        // Log email error but don't fail registration
        error_log("Email queue error: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully registered for the event! Check your email for confirmation.',
        'qr_code' => $qr_code
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Event registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}
