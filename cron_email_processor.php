<?php
/**
 * Cron Job: Email Queue Processor
 * This script should be run every 5-10 minutes to process the email queue
 * 

 * 
 * For Windows Task Scheduler:
 * php.exe "C:\xampp\htdocs\Evento-1\cron_email_processor.php"
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/cron_email.log');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/Email.php';

// Create log file if it doesn't exist
$log_file = __DIR__ . '/logs/cron_email.log';
if (!file_exists(dirname($log_file))) {
    mkdir(dirname($log_file), 0777, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

logMessage("Email queue processor started");

try {
    // Process email queue
    $result = Email::processEmailQueue(50); // Process up to 50 emails per run
    
    if ($result) {
        logMessage("Processed: {$result['processed']} emails, Successful: {$result['successful']}");
    } else {
        logMessage("Email queue processing failed");
    }
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
}

logMessage("Email queue processor completed");
?>