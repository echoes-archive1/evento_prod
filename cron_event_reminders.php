<?php
/**
 * Cron Job: Event Reminder Sender
 * This script sends automatic event reminders (1 day and 1 hour before events)
 * 
 * To set up cron job, add these lines to your crontab:
 * 0 /usr/bin/php /path/to/your/projeto/cron_event_reminders.php
 * 
 * For Windows Task Scheduler, run every hour:
 * php.exe "C:\xampp\htdocs\Evento-1\cron_event_reminders.php"
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/cron_reminders.log');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/Email.php';

// Create log file if it doesn't exist
$log_file = __DIR__ . '/logs/cron_reminders.log';
if (!file_exists(dirname($log_file))) {
    mkdir(dirname($log_file), 0777, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

logMessage("Event reminder processor started");

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Find events that need 1-day reminders (24 hours before event)
    $day_reminder_stmt = $conn->prepare("
        SELECT e.*, er.reminder_1day_sent 
        FROM events e
        INNER JOIN event_registrations er ON e.id = er.event_id
        WHERE e.status = 'approved' 
        AND e.event_date BETWEEN DATE_ADD(NOW(), INTERVAL 23 HOUR) AND DATE_ADD(NOW(), INTERVAL 25 HOUR)
        AND (er.reminder_1day_sent IS NULL OR er.reminder_1day_sent = 0)
        GROUP BY e.id
    ");
    $day_reminder_stmt->execute();
    $day_reminder_events = $day_reminder_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($day_reminder_events as $event) {
        logMessage("Processing 1-day reminder for event: " . $event['event_name']);
        $result = Email::queueBulkEventReminders($event['id'], '1_day');
        
        if ($result) {
            // Mark as sent to avoid sending again
            $update_stmt = $conn->prepare("UPDATE event_registrations SET reminder_1day_sent = 1 WHERE event_id = ?");
            $update_stmt->execute([$event['id']]);
            logMessage("Queued {$result} 1-day reminder emails for event: " . $event['event_name']);
        }
    }
    
    // Find events that need 1-hour reminders (1 hour before event)
    $hour_reminder_stmt = $conn->prepare("
        SELECT e.*, er.reminder_1hour_sent 
        FROM events e
        INNER JOIN event_registrations er ON e.id = er.event_id
        WHERE e.status = 'approved' 
        AND e.event_date BETWEEN DATE_ADD(NOW(), INTERVAL 30 MINUTE) AND DATE_ADD(NOW(), INTERVAL 90 MINUTE)
        AND (er.reminder_1hour_sent IS NULL OR er.reminder_1hour_sent = 0)
        GROUP BY e.id
    ");
    $hour_reminder_stmt->execute();
    $hour_reminder_events = $hour_reminder_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($hour_reminder_events as $event) {
        logMessage("Processing 1-hour reminder for event: " . $event['event_name']);
        $result = Email::queueBulkEventReminders($event['id'], '1_hour');
        
        if ($result) {
            // Mark as sent to avoid sending again
            $update_stmt = $conn->prepare("UPDATE event_registrations SET reminder_1hour_sent = 1 WHERE event_id = ?");
            $update_stmt->execute([$event['id']]);
            logMessage("Queued {$result} 1-hour reminder emails for event: " . $event['event_name']);
        }
    }
    
    // Check if it's time for weekly digest (every Sunday at 9 AM)
    if (date('w') == 0 && date('H') == 9) {
        logMessage("Processing weekly digest emails");
        $digest_result = Email::queueBulkWeeklyDigests();
        if ($digest_result) {
            logMessage("Queued {$digest_result} weekly digest emails");
        }
    }
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
}

logMessage("Event reminder processor completed");
?>