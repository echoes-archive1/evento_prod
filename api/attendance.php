<?php
/**
 * Real-time Attendance Tracking API
 * Provides endpoints for QR code scanning, attendance marking, and stats
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

header('Content-Type: application/json');

// CORS headers for modern web apps
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Require authentication
    Auth::requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $db = Database::getInstance()->getConnection();
    
    switch ($method) {
        case 'GET':
            handleGetRequest($db, $action);
            break;
            
        case 'POST':
            handlePostRequest($db, $action);
            break;
            
        case 'PUT':
            handlePutRequest($db, $action);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode() ?: 500
    ]);
}

/**
 * Handle GET requests - Fetch attendance data
 */
function handleGetRequest($db, $action) {
    switch ($action) {
        case 'verify_qr':
            verifyQRCode($db);
            break;
            
        case 'event_stats':
            getEventStats($db);
            break;
            
        case 'event_attendees':
            getEventAttendees($db);
            break;
            
        case 'attendance_export':
            exportAttendanceData($db);
            break;
            
        case 'scan_history':
            getScanHistory($db);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Handle POST requests - Mark attendance, bulk operations
 */
function handlePostRequest($db, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'mark_attendance':
            markAttendance($db, $input);
            break;
            
        case 'bulk_mark':
            bulkMarkAttendance($db, $input);
            break;
            
        case 'scan_qr':
            scanQRCode($db, $input);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Handle PUT requests - Update attendance status
 */
function handlePutRequest($db, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update_status':
            updateAttendanceStatus($db, $input);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Verify QR Code and return registration details
 */
function verifyQRCode($db) {
    $qr_code = $_GET['qr_code'] ?? '';
    
    if (empty($qr_code)) {
        throw new Exception('QR code is required', 400);
    }
    
    $sql = "SELECT 
                er.*, 
                e.event_name, 
                e.event_date, 
                e.venue,
                e.id as event_id,
                u.full_name, 
                u.email, 
                u.roll_number,
                u.department,
                c.club_name
            FROM event_registrations er 
            JOIN events e ON er.event_id = e.id 
            JOIN users u ON er.user_id = u.id 
            LEFT JOIN clubs c ON e.club_id = c.id
            WHERE er.qr_code = :qr_code";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['qr_code' => $qr_code]);
    $registration = $stmt->fetch();
    
    if (!$registration) {
        throw new Exception('Invalid QR code or registration not found', 404);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'registration_id' => $registration['id'],
            'qr_code' => $registration['qr_code'],
            'attendance_status' => $registration['attendance_status'],
            'registration_date' => $registration['registration_date'],
            'checked_in_at' => $registration['checked_in_at'],
            'participant' => [
                'name' => $registration['full_name'],
                'email' => $registration['email'],
                'roll_number' => $registration['roll_number'],
                'department' => $registration['department']
            ],
            'event' => [
                'id' => $registration['event_id'],
                'name' => $registration['event_name'],
                'date' => $registration['event_date'],
                'venue' => $registration['venue'],
                'club_name' => $registration['club_name']
            ]
        ]
    ]);
}

/**
 * Scan QR Code and mark attendance
 */
function scanQRCode($db, $input) {
    $qr_code = $input['qr_code'] ?? '';
    $scanner_id = $_SESSION['user_id'] ?? null;
    
    if (empty($qr_code)) {
        throw new Exception('QR code is required', 400);
    }
    
    $db->beginTransaction();
    
    try {
        // Find registration
        $sql = "SELECT 
                    er.*, 
                    e.event_name, 
                    e.event_date, 
                    e.venue,
                    u.full_name, 
                    u.email, 
                    u.roll_number
                FROM event_registrations er 
                JOIN events e ON er.event_id = e.id 
                JOIN users u ON er.user_id = u.id 
                WHERE er.qr_code = :qr_code";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['qr_code' => $qr_code]);
        $registration = $stmt->fetch();
        
        if (!$registration) {
            throw new Exception('Invalid QR code or registration not found', 404);
        }
        
        $was_already_attended = $registration['attendance_status'] === 'attended';
        
        // Mark attendance
        $update_sql = "UPDATE event_registrations 
                       SET attendance_status = 'attended', 
                           checked_in_at = NOW() 
                       WHERE qr_code = :qr_code";
        
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->execute(['qr_code' => $qr_code]);
        
        // Log scan activity
        $log_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address) 
                    VALUES (:scanner_id, 'qr_scan_attendance', 'event_registrations', :registration_id, :details, :ip)";
        
        $log_stmt = $db->prepare($log_sql);
        $log_stmt->execute([
            'scanner_id' => $scanner_id,
            'registration_id' => $registration['id'],
            'details' => json_encode([
                'qr_code' => $qr_code,
                'participant' => $registration['full_name'],
                'event' => $registration['event_name'],
                'was_already_attended' => $was_already_attended
            ]),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $was_already_attended ? 'Participant was already checked in' : 'Attendance marked successfully',
            'data' => [
                'registration_id' => $registration['id'],
                'participant_name' => $registration['full_name'],
                'event_name' => $registration['event_name'],
                'checked_in_at' => date('Y-m-d H:i:s'),
                'was_duplicate' => $was_already_attended
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Mark attendance manually
 */
function markAttendance($db, $input) {
    $registration_id = $input['registration_id'] ?? null;
    $status = $input['status'] ?? 'attended';
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$registration_id) {
        throw new Exception('Registration ID is required', 400);
    }
    
    if (!in_array($status, ['registered', 'attended', 'absent'])) {
        throw new Exception('Invalid attendance status', 400);
    }
    
    $db->beginTransaction();
    
    try {
        // Update attendance status
        $sql = "UPDATE event_registrations 
                SET attendance_status = :status, 
                    checked_in_at = CASE WHEN :status = 'attended' THEN NOW() ELSE checked_in_at END
                WHERE id = :registration_id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'status' => $status,
            'registration_id' => $registration_id
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Registration not found or no changes made', 404);
        }
        
        // Log the action
        $log_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address) 
                    VALUES (:user_id, 'manual_attendance_update', 'event_registrations', :registration_id, :details, :ip)";
        
        $log_stmt = $db->prepare($log_sql);
        $log_stmt->execute([
            'user_id' => $user_id,
            'registration_id' => $registration_id,
            'details' => json_encode(['new_status' => $status]),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance status updated successfully',
            'data' => [
                'registration_id' => $registration_id,
                'new_status' => $status
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Get event attendance statistics
 */
function getEventStats($db) {
    $event_id = $_GET['event_id'] ?? null;
    
    if (!$event_id) {
        throw new Exception('Event ID is required', 400);
    }
    
    $sql = "SELECT 
                e.event_name,
                e.event_date,
                e.max_participants,
                COUNT(er.id) as total_registered,
                COUNT(CASE WHEN er.attendance_status = 'attended' THEN 1 END) as total_attended,
                COUNT(CASE WHEN er.attendance_status = 'absent' THEN 1 END) as total_absent,
                COUNT(CASE WHEN er.attendance_status = 'registered' THEN 1 END) as pending_attendance,
                ROUND((COUNT(CASE WHEN er.attendance_status = 'attended' THEN 1 END) / COUNT(er.id)) * 100, 2) as attendance_rate
            FROM events e
            LEFT JOIN event_registrations er ON e.id = er.event_id
            WHERE e.id = :event_id
            GROUP BY e.id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['event_id' => $event_id]);
    $stats = $stmt->fetch();
    
    if (!$stats) {
        throw new Exception('Event not found', 404);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}

/**
 * Get event attendee list
 */
function getEventAttendees($db) {
    $event_id = $_GET['event_id'] ?? null;
    $status_filter = $_GET['status'] ?? null;
    
    if (!$event_id) {
        throw new Exception('Event ID is required', 400);
    }
    
    $where_clause = "";
    $params = ['event_id' => $event_id];
    
    if ($status_filter && in_array($status_filter, ['registered', 'attended', 'absent'])) {
        $where_clause = "AND er.attendance_status = :status";
        $params['status'] = $status_filter;
    }
    
    $sql = "SELECT 
                er.id as registration_id,
                er.qr_code,
                er.attendance_status,
                er.registration_date,
                er.checked_in_at,
                u.full_name,
                u.email,
                u.roll_number,
                u.department,
                u.year
            FROM event_registrations er
            JOIN users u ON er.user_id = u.id
            WHERE er.event_id = :event_id $where_clause
            ORDER BY 
                CASE er.attendance_status 
                    WHEN 'attended' THEN 1 
                    WHEN 'registered' THEN 2 
                    WHEN 'absent' THEN 3 
                END,
                er.checked_in_at DESC,
                u.full_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $attendees = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $attendees
    ]);
}

/**
 * Export attendance data
 */
function exportAttendanceData($db) {
    $event_id = $_GET['event_id'] ?? null;
    $format = $_GET['format'] ?? 'json';
    
    if (!$event_id) {
        throw new Exception('Event ID is required', 400);
    }
    
    // Get event details
    $event_sql = "SELECT event_name, event_date, venue FROM events WHERE id = :event_id";
    $event_stmt = $db->prepare($event_sql);
    $event_stmt->execute(['event_id' => $event_id]);
    $event = $event_stmt->fetch();
    
    if (!$event) {
        throw new Exception('Event not found', 404);
    }
    
    // Get attendance data
    $sql = "SELECT 
                u.full_name,
                u.email,
                u.roll_number,
                u.department,
                u.year,
                u.phone,
                er.attendance_status,
                er.registration_date,
                er.checked_in_at,
                er.qr_code
            FROM event_registrations er
            JOIN users u ON er.user_id = u.id
            WHERE er.event_id = :event_id
            ORDER BY u.full_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['event_id' => $event_id]);
    $attendees = $stmt->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $event['event_name']) . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, [
            'Event Name',
            'Event Date',
            'Venue',
            'Full Name',
            'Email',
            'Roll Number',
            'Department',
            'Year',
            'Phone',
            'Attendance Status',
            'Registration Date',
            'Check-in Time',
            'QR Code'
        ]);
        
        // Data rows
        foreach ($attendees as $attendee) {
            fputcsv($output, [
                $event['event_name'],
                $event['event_date'],
                $event['venue'],
                $attendee['full_name'],
                $attendee['email'],
                $attendee['roll_number'],
                $attendee['department'],
                $attendee['year'],
                $attendee['phone'],
                ucfirst($attendee['attendance_status']),
                $attendee['registration_date'],
                $attendee['checked_in_at'] ?: 'Not checked in',
                $attendee['qr_code']
            ]);
        }
        
        fclose($output);
        exit;
        
    } else {
        // JSON format
        echo json_encode([
            'success' => true,
            'data' => [
                'event' => $event,
                'attendees' => $attendees,
                'summary' => [
                    'total_registered' => count($attendees),
                    'attended' => count(array_filter($attendees, function($a) { return $a['attendance_status'] === 'attended'; })),
                    'absent' => count(array_filter($attendees, function($a) { return $a['attendance_status'] === 'absent'; })),
                    'pending' => count(array_filter($attendees, function($a) { return $a['attendance_status'] === 'registered'; }))
                ]
            ]
        ]);
    }
}

/**
 * Get scan history for auditing
 */
function getScanHistory($db) {
    $limit = min(intval($_GET['limit'] ?? 50), 200); // Max 200 records
    $offset = intval($_GET['offset'] ?? 0);
    $event_id = $_GET['event_id'] ?? null;
    
    $where_clause = "";
    $params = ['limit' => $limit, 'offset' => $offset];
    
    if ($event_id) {
        $where_clause = "AND JSON_EXTRACT(new_values, '$.event_id') = :event_id";
        $params['event_id'] = $event_id;
    }
    
    $sql = "SELECT 
                al.created_at,
                al.ip_address,
                u.full_name as scanner_name,
                al.new_values
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.action = 'qr_scan_attendance' $where_clause
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $history = $stmt->fetchAll();
    
    // Decode JSON values
    foreach ($history as &$record) {
        $record['scan_details'] = json_decode($record['new_values'], true);
        unset($record['new_values']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
}

/**
 * Bulk mark attendance
 */
function bulkMarkAttendance($db, $input) {
    $registration_ids = $input['registration_ids'] ?? [];
    $status = $input['status'] ?? 'attended';
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (empty($registration_ids) || !is_array($registration_ids)) {
        throw new Exception('Registration IDs array is required', 400);
    }
    
    if (!in_array($status, ['registered', 'attended', 'absent'])) {
        throw new Exception('Invalid attendance status', 400);
    }
    
    $db->beginTransaction();
    
    try {
        $placeholders = implode(',', array_fill(0, count($registration_ids), '?'));
        
        $sql = "UPDATE event_registrations 
                SET attendance_status = ?, 
                    checked_in_at = CASE WHEN ? = 'attended' THEN NOW() ELSE checked_in_at END
                WHERE id IN ($placeholders)";
        
        $params = [$status, $status];
        $params = array_merge($params, $registration_ids);
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $affected_rows = $stmt->rowCount();
        
        // Log bulk operation
        $log_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address) 
                    VALUES (?, 'bulk_attendance_update', 'event_registrations', 0, ?, ?)";
        
        $log_stmt = $db->prepare($log_sql);
        $log_stmt->execute([
            $user_id,
            json_encode([
                'registration_ids' => $registration_ids,
                'new_status' => $status,
                'affected_rows' => $affected_rows
            ]),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully updated $affected_rows registrations",
            'data' => [
                'updated_count' => $affected_rows,
                'new_status' => $status
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Update attendance status
 */
function updateAttendanceStatus($db, $input) {
    markAttendance($db, $input); // Same functionality as mark_attendance
}