<?php
/**
 * Automatic Semester Promotion Cron Job
 * This file should be run via cron job or task scheduler
 * 
 * Recommended schedule:
 * - Run daily at 6:00 AM
 * - Linux/Mac: 0 6 * * * /usr/bin/php /path/to/your/project/cron_promote_students.php
 * - Windows: Create a scheduled task to run this file daily
 */

// Set execution time limit for bulk operations
set_time_limit(300); // 5 minutes

// Include required files
require_once __DIR__ . '/config/config.php';

// Log cron execution
$log_file = __DIR__ . '/logs/promotion_cron.log';
$log_entry = "[" . date('Y-m-d H:i:s') . "] Starting automatic promotion check\n";

// Ensure logs directory exists
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

try {
    // Check if promotion should occur
    $promotion_check = StudentRegistrationHelper::checkAutomaticPromotion();
    
    $log_entry = "[" . date('Y-m-d H:i:s') . "] Promotion check result: " . json_encode($promotion_check) . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    if ($promotion_check['should_promote']) {
        // Run automatic promotion
        $log_entry = "[" . date('Y-m-d H:i:s') . "] Starting automatic promotion - Type: {$promotion_check['promotion_type']}\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        $result = StudentRegistrationHelper::promoteStudents();
        
        if ($result['success']) {
            $log_entry = "[" . date('Y-m-d H:i:s') . "] SUCCESS: Promoted {$result['promoted_count']} students\n";
            
            // Send email notification to admins if configured
            if (defined('ENABLE_PROMOTION_NOTIFICATIONS') && ENABLE_PROMOTION_NOTIFICATIONS) {
                sendPromotionNotification($result);
            }
            
        } else {
            $log_entry = "[" . date('Y-m-d H:i:s') . "] ERROR: Promotion failed - {$result['error']}\n";
        }
        
        // Log any errors
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $log_entry .= "[" . date('Y-m-d H:i:s') . "] WARNING: $error\n";
            }
        }
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
    } else {
        $reason = $promotion_check['reason'] ?? 'No promotion needed at this time';
        $log_entry = "[" . date('Y-m-d H:i:s') . "] No promotion required: $reason\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
} catch (Exception $e) {
    $error_message = "FATAL ERROR in promotion cron: " . $e->getMessage();
    $log_entry = "[" . date('Y-m-d H:i:s') . "] $error_message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Log to PHP error log as well
    error_log($error_message);
    
    // Send error notification if email is configured
    if (defined('MAIL_FROM_ADDRESS') && defined('ENABLE_PROMOTION_NOTIFICATIONS') && ENABLE_PROMOTION_NOTIFICATIONS) {
        try {
            $admin_email = 'admin@college.edu'; // Change this to your admin email
            $subject = 'Automatic Promotion Cron Error';
            $message = "The automatic promotion cron job encountered an error:\n\n" . $error_message;
            
            mail($admin_email, $subject, $message);
        } catch (Exception $mail_error) {
            error_log("Failed to send error notification email: " . $mail_error->getMessage());
        }
    }
}

$log_entry = "[" . date('Y-m-d H:i:s') . "] Promotion cron completed\n\n";
file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

/**
 * Send promotion notification to admins
 */
function sendPromotionNotification($result) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get admin emails
        $admin_sql = "SELECT DISTINCT u.email, u.full_name 
                      FROM users u 
                      JOIN user_roles ur ON u.id = ur.user_id 
                      JOIN roles r ON ur.role_id = r.id 
                      WHERE r.role_name = 'admin' AND u.is_active = 1 AND u.email_verified = 1";
        
        $admin_stmt = $db->query($admin_sql);
        $admins = $admin_stmt->fetchAll();
        
        if (!empty($admins)) {
            $subject = 'Automatic Student Promotion Completed';
            $message = "The automatic student promotion process has completed successfully.\n\n";
            $message .= "Summary:\n";
            $message .= "- Students promoted: {$result['promoted_count']}\n";
            $message .= "- Date: " . date('Y-m-d H:i:s') . "\n\n";
            
            if (!empty($result['errors'])) {
                $message .= "Errors encountered:\n";
                foreach (array_slice($result['errors'], 0, 10) as $error) {
                    $message .= "- $error\n";
                }
            }
            
            $message .= "\nYou can view detailed promotion history in the admin panel.\n";
            
            foreach ($admins as $admin) {
                mail($admin['email'], $subject, $message);
            }
        }
        
    } catch (Exception $e) {
        error_log("Failed to send promotion notification: " . $e->getMessage());
    }
}

echo "Automatic promotion cron job completed. Check logs for details.\n";
?>