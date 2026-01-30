<?php
/**
 * Simple QR System Database Migration Script
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Starting QR Code System Enhancement database updates...\n\n";
    
    // Individual SQL statements for better error handling
    $updates = [
        // Add QR code columns
        "ALTER TABLE event_registrations ADD COLUMN qr_code VARCHAR(255) UNIQUE",
        "ALTER TABLE event_registrations ADD COLUMN checked_in_at TIMESTAMP NULL",
        "ALTER TABLE event_registrations ADD COLUMN attendance_marked_at TIMESTAMP NULL",
        
        // Add indexes
        "ALTER TABLE event_registrations ADD INDEX idx_qr_code (qr_code)",
        "ALTER TABLE event_registrations ADD INDEX idx_attendance_status (attendance_status)",
        "ALTER TABLE event_registrations ADD INDEX idx_event_attendance (event_id, attendance_status)",
        "ALTER TABLE event_registrations ADD INDEX idx_checked_in_at (checked_in_at)",
        "ALTER TABLE event_registrations ADD INDEX idx_event_user (event_id, user_id)",
        "ALTER TABLE event_registrations ADD INDEX idx_user_attendance (user_id, attendance_status)",
        
        // Add event tracking columns
        "ALTER TABLE events ADD COLUMN qr_scanner_enabled TINYINT(1) DEFAULT 1",
        "ALTER TABLE events ADD COLUMN attendance_tracking_enabled TINYINT(1) DEFAULT 1", 
        "ALTER TABLE events ADD COLUMN check_in_start_time DATETIME NULL",
        "ALTER TABLE events ADD COLUMN check_in_end_time DATETIME NULL",
        
        // Add user preferences
        "ALTER TABLE users ADD COLUMN attendance_reminders TINYINT(1) DEFAULT 1",
        "ALTER TABLE users ADD COLUMN qr_code_email TINYINT(1) DEFAULT 1",
        
        // Add audit log indexes
        "ALTER TABLE audit_logs ADD INDEX idx_action_table (action, table_name)",
        "ALTER TABLE audit_logs ADD INDEX idx_user_created (user_id, created_at)"
    ];
    
    // Create QR scan logs table
    $qr_logs_table = "
        CREATE TABLE IF NOT EXISTS qr_scan_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_registration_id INT UNSIGNED NOT NULL,
            scanner_user_id INT UNSIGNED NULL,
            qr_code VARCHAR(255) NOT NULL,
            scan_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            scan_result ENUM('success', 'duplicate', 'invalid', 'expired') NOT NULL,
            scan_method ENUM('camera', 'manual', 'api') DEFAULT 'camera',
            ip_address VARCHAR(45),
            user_agent TEXT,
            location_lat DECIMAL(10, 8) NULL,
            location_lng DECIMAL(11, 8) NULL,
            INDEX idx_registration_id (event_registration_id),
            INDEX idx_scanner_user (scanner_user_id),
            INDEX idx_qr_code (qr_code),
            INDEX idx_scan_timestamp (scan_timestamp),
            INDEX idx_scan_result (scan_result)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $success_count = 0;
    $error_count = 0;
    $warnings = [];
    
    // Execute table creation first
    try {
        $db->exec($qr_logs_table);
        echo "✅ Created qr_scan_logs table\n";
        $success_count++;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠️  qr_scan_logs table already exists\n";
            $warnings[] = "qr_scan_logs table already exists";
        } else {
            echo "❌ Error creating qr_scan_logs table: " . $e->getMessage() . "\n";
            $error_count++;
        }
    }
    
    // Execute other updates
    foreach ($updates as $update) {
        try {
            $db->exec($update);
            $preview = substr(trim($update), 0, 60) . (strlen($update) > 60 ? '...' : '');
            echo "✅ Executed: $preview\n";
            $success_count++;
        } catch (Exception $e) {
            $preview = substr(trim($update), 0, 60) . (strlen($update) > 60 ? '...' : '');
            
            // Check for common "already exists" errors
            if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
                strpos($e->getMessage(), 'Duplicate key name') !== false ||
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠️  Already exists: $preview\n";
                $warnings[] = $preview;
            } else {
                echo "❌ Error in: $preview\n";
                echo "   Error: " . $e->getMessage() . "\n";
                $error_count++;
            }
        }
    }
    
    // Update existing registrations with QR codes
    try {
        $db->exec("
            UPDATE event_registrations 
            SET qr_code = CONCAT('EVENT_', event_id, '_', user_id, '_', UNIX_TIMESTAMP(registration_date), '_', 
                                 SUBSTRING(MD5(CONCAT(id, event_id, user_id, registration_date)), 1, 8))
            WHERE qr_code IS NULL OR qr_code = ''
        ");
        echo "✅ Updated existing registrations with QR codes\n";
        $success_count++;
    } catch (Exception $e) {
        echo "❌ Error updating QR codes: " . $e->getMessage() . "\n";
        $error_count++;
    }
    
    echo "\n=== QR System Database Update Summary ===\n";
    echo "✅ Successful operations: $success_count\n";
    echo "⚠️  Warnings (already exists): " . count($warnings) . "\n";
    echo "❌ Errors: $error_count\n";
    
    if ($error_count === 0) {
        echo "\n🎉 QR Code System Enhancement database setup completed!\n\n";
        echo "📋 Available Features:\n";
        echo "- Enhanced QR code generation with event tickets\n";
        echo "- Real-time attendance tracking API (/api/attendance.php)\n";
        echo "- Mobile-responsive QR scanner (/qr-scanner.php, /mobile-scanner.php)\n";
        echo "- Comprehensive attendance dashboard (/attendance-dashboard.php)\n";
        echo "- Export attendance reports (CSV/JSON)\n";
        echo "- Audit logs and scan history tracking\n";
        echo "- Bulk attendance operations\n\n";
        
        echo "🔗 Quick Access URLs:\n";
        echo "- QR Scanner: " . (defined('BASE_URL') ? BASE_URL : 'http://localhost:4060/Evento-1') . "/qr-scanner.php\n";
        echo "- Mobile Scanner: " . (defined('BASE_URL') ? BASE_URL : 'http://localhost:4060/Evento-1') . "/mobile-scanner.php\n";
        echo "- Attendance Dashboard: " . (defined('BASE_URL') ? BASE_URL : 'http://localhost:4060/Evento-1') . "/attendance-dashboard.php\n";
        echo "- API Documentation: Available in /api/attendance.php\n";
        
    } else {
        echo "\n⚠️  Some errors occurred. The system may still work with reduced functionality.\n";
    }
    
    if (!empty($warnings)) {
        echo "\n📝 Note: Some warnings indicate that database elements already exist, which is normal for updates.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>