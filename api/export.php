<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

// Require admin role
Auth::requireRole('admin');

$type = $_GET['type'] ?? 'users';

try {
    $db = Database::getInstance()->getConnection();
    $filename = '';
    $data = [];
    $headers = [];
    
    switch ($type) {
        case 'users':
            $filename = 'users_export_' . date('Y-m-d_His') . '.csv';
            $sql = "SELECT u.id, u.full_name, u.roll_number, u.email, u.department, u.year, u.phone, 
                           u.is_active, u.email_verified, u.created_at, GROUP_CONCAT(r.role_name) as roles
                    FROM users u
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    GROUP BY u.id
                    ORDER BY u.created_at DESC";
            $headers = ['ID', 'Full Name', 'Roll Number', 'Email', 'Department', 'Year', 'Phone', 'Active', 'Email Verified', 'Created At', 'Roles'];
            break;
            
        case 'events':
            $filename = 'events_export_' . date('Y-m-d_His') . '.csv';
            $sql = "SELECT e.id, e.event_name, e.event_date, e.venue, e.status, c.club_name, 
                           u.full_name as creator, e.created_at,
                           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registrations
                    FROM events e
                    LEFT JOIN clubs c ON e.club_id = c.id
                    LEFT JOIN users u ON e.created_by = u.id
                    ORDER BY e.created_at DESC";
            $headers = ['ID', 'Event Name', 'Event Date', 'Venue', 'Status', 'Club', 'Created By', 'Created At', 'Registrations'];
            break;
            
        case 'clubs':
            $filename = 'clubs_export_' . date('Y-m-d_His') . '.csv';
            $sql = "SELECT c.id, c.club_name, c.description, u.full_name as leader, c.is_active, c.created_at,
                           (SELECT COUNT(*) FROM events WHERE club_id = c.id) as total_events
                    FROM clubs c
                    LEFT JOIN users u ON c.leader_id = u.id
                    ORDER BY c.created_at DESC";
            $headers = ['ID', 'Club Name', 'Description', 'Leader', 'Active', 'Created At', 'Total Events'];
            break;
            
        case 'registrations':
            $filename = 'registrations_export_' . date('Y-m-d_His') . '.csv';
            $sql = "SELECT er.id, e.event_name, u.full_name, u.email, u.roll_number, er.registration_status, er.registered_at
                    FROM event_registrations er
                    JOIN events e ON er.event_id = e.id
                    JOIN users u ON er.user_id = u.id
                    ORDER BY er.registered_at DESC";
            $headers = ['ID', 'Event Name', 'Student Name', 'Email', 'Roll Number', 'Status', 'Registered At'];
            break;
            
        case 'audit':
            $filename = 'audit_logs_export_' . date('Y-m-d_His') . '.csv';
            $sql = "SELECT al.id, u.full_name as user, al.action, al.table_name, al.record_id, al.details, al.created_at
                    FROM audit_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    ORDER BY al.created_at DESC
                    LIMIT 10000";
            $headers = ['ID', 'User', 'Action', 'Table', 'Record ID', 'Details', 'Timestamp'];
            break;
            
        default:
            http_response_code(400);
            die('Invalid export type');
    }
    
    $stmt = $db->query($sql);
    $data = $stmt->fetchAll();
    
    // Log export action
    Security::logAudit(Auth::id(), 'data_export', $type, 0, "Exported {$type} data");
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, array_values($row));
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    http_response_code(500);
    die('Export failed');
}
?>
