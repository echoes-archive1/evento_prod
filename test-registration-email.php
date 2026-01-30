<?php
/**
 * Test Registration Email
 * Run this file to test if event registration emails are working
 */

require_once __DIR__ . '/config/config.php';

echo "<!DOCTYPE html><html><head><title>Test Email</title></head><body>";
echo "<h1>Testing Event Registration Email</h1>";

// Test event data
$test_event = [
    'id' => 999,
    'event_name' => 'Test Event - Email Check',
    'event_date' => '2026-01-15 14:30:00',
    'venue' => 'Test Auditorium',
    'department' => 'Computer Science',
    'event_description' => 'This is a test event to verify email functionality'
];

$test_qr = 'TEST-QR-' . time();
$test_email = MAIL_FROM_ADDRESS; // Send to yourself for testing
$test_name = 'Test User';

echo "<p>Sending test email to: <strong>" . htmlspecialchars($test_email) . "</strong></p>";
echo "<p>Event: " . htmlspecialchars($test_event['event_name']) . "</p>";
echo "<p>QR Code: " . htmlspecialchars($test_qr) . "</p>";
echo "<hr>";

// Try sending email
$result = Email::sendEventRegistrationEmail($test_email, $test_name, $test_event, $test_qr);

if ($result) {
    echo "<p style='color: green; font-weight: bold;'>✓ Email sent successfully!</p>";
    echo "<p>Check your inbox at: " . htmlspecialchars($test_email) . "</p>";
    echo "<p>Also check spam/junk folder if not found in inbox.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Email sending failed!</p>";
    echo "<p>Check the error log at: logs/error.log</p>";
    echo "<p>Common issues:</p>";
    echo "<ul>";
    echo "<li>Gmail App Password might be invalid</li>";
    echo "<li>SMTP settings might be incorrect</li>";
    echo "<li>Network/firewall blocking port 587</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
echo "</body></html>";
?>
