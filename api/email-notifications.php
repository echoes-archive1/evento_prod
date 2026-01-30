<?php
/**
 * Admin API: Event Notification Management
 * Allows admins to send event updates, cancellations, and bulk notifications
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';
require_once __DIR__ . '/../app/helpers/Email.php';

header('Content-Type: application/json');

// Require admin authentication
if (!Auth::check() || !Auth::isRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($action) {
        case 'send_event_update':
            $event_id = $input['event_id'] ?? null;
            $update_type = $input['update_type'] ?? 'updated'; // 'updated' or 'cancelled'
            $update_message = $input['message'] ?? '';
            
            if (!$event_id) {
                throw new Exception('Event ID is required');
            }
            
            // Get event details
            $event_stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
            $event_stmt->execute([$event_id]);
            $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$event) {
                throw new Exception('Event not found');
            }
            
            // Get all registered users
            $users_stmt = $db->prepare("
                SELECT u.email, u.name 
                FROM users u 
                INNER JOIN event_registrations er ON u.id = er.user_id 
                WHERE er.event_id = ? AND er.status = 'registered'
            ");
            $users_stmt->execute([$event_id]);
            $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $queued = 0;
            foreach ($users as $user) {
                if (Email::sendEventUpdateNotification($user['email'], $user['name'], $event, $update_type, $update_message)) {
                    $queued++;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Queued {$queued} notification emails",
                'queued_emails' => $queued
            ]);
            break;
            
        case 'send_bulk_reminders':
            $event_id = $input['event_id'] ?? null;
            $reminder_type = $input['reminder_type'] ?? '1_day';
            
            if (!$event_id) {
                throw new Exception('Event ID is required');
            }
            
            $result = Email::queueBulkEventReminders($event_id, $reminder_type);
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => "Queued {$result} reminder emails",
                    'queued_emails' => $result
                ]);
            } else {
                throw new Exception('Failed to queue reminder emails');
            }
            break;
            
        case 'send_weekly_digest':
            $result = Email::queueBulkWeeklyDigests();
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => "Queued {$result} weekly digest emails",
                    'queued_emails' => $result
                ]);
            } else {
                throw new Exception('Failed to queue weekly digest emails');
            }
            break;
            
        case 'queue_status':
            // Get email queue statistics
            $stats_stmt = $db->prepare("
                SELECT 
                    status,
                    priority,
                    COUNT(*) as count,
                    MIN(created_at) as oldest,
                    MAX(created_at) as newest
                FROM email_queue 
                GROUP BY status, priority
                ORDER BY status, priority
            ");
            $stats_stmt->execute();
            $stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total counts
            $total_stmt = $db->prepare("SELECT COUNT(*) as total, status FROM email_queue GROUP BY status");
            $total_stmt->execute();
            $totals = $total_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'queue_stats' => $stats,
                'totals' => $totals
            ]);
            break;
            
        case 'process_queue':
            // Manually trigger queue processing
            $limit = $input['limit'] ?? 50;
            $result = Email::processEmailQueue($limit);
            
            echo json_encode([
                'success' => true,
                'message' => "Processed {$result['processed']} emails, {$result['successful']} successful",
                'processed' => $result['processed'],
                'successful' => $result['successful']
            ]);
            break;
            
        case 'get_admin_emails':
            // Get list of admin emails for notifications
            $admin_stmt = $db->prepare("SELECT email, name FROM users WHERE role = 'admin' AND status = 'active'");
            $admin_stmt->execute();
            $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'admin_emails' => $admins
            ]);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    error_log("Email notification API error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>