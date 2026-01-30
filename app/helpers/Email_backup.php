<?php
/**
 * Enhanced Email Service Class
 * Handles sending emails using PHP mail() or SMTP
 * Includes email queue system and bulk notifications
 */

require_once __DIR__ . '/QRCode.php';
require_once __DIR__ . '/../../config/database.php';

class Email {
    private static $from_email;
    private static $from_name;
    private static $use_smtp = true;
    
    /**
     * Initialize email configuration
     */
    public static function init() {
        self::$from_email = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'hitanshpparikh@gmail.com';
        self::$from_name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Evento';
        self::$use_smtp = defined('MAIL_USE_SMTP') ? MAIL_USE_SMTP : true;
    }
    
    /**
     * Send verification email
     */
    public static function sendVerificationEmail($to_email, $to_name, $verification_token, $verification_code) {
        $verification_url = BASE_URL . "/verify-email.php?token=" . urlencode($verification_token);
        
        $subject = "Verify Your Email - " . APP_NAME;
        
        $html_body = self::getEmailTemplate([
            'title' => 'Verify Your Email Address',
            'greeting' => "Hello " . htmlspecialchars($to_name) . ",",
            'content' => '
                <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                    Thank you for registering with ' . APP_NAME . '. To complete your registration and access all features, please verify your email address.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <p style="margin: 10px 0; color: #666; font-size: 14px;">Your verification code:</p>
                    <div style="display: inline-block; padding: 20px 40px; background: #f8f9fa; border: 2px dashed #6366f1; border-radius: 12px; margin: 15px 0;">
                        <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #6366f1; font-family: monospace;">' . $verification_code . '</span>
                    </div>
                    <p style="margin: 10px 0; color: #999; font-size: 13px;">Copy and paste this code on the verification page</p>
                </div>
            ',
            'button_text' => 'Verify Email Address',
            'button_url' => $verification_url,
            'footer_text' => 'This verification code and link will expire in 10 minutes. If you did not create this account, please ignore this email.',
            'alternative_text' => 'Or copy and paste this link into your browser: ' . $verification_url
        ]);
        
        $text_body = self::getTextTemplate([
            'greeting' => "Hello " . $to_name . ",",
            'content' => "Thank you for registering with " . APP_NAME . ". To complete your registration and access all features, please verify your email address.\n\nYour verification code: " . $verification_code . "\n\nOr verify your email by clicking this link:\n" . $verification_url . "\n\nThis verification code and link will expire in 10 minutes. If you did not create this account, please ignore this email.",
        ]);
        
        return self::send($to_email, $subject, $html_body, $text_body);
    }
    
    /**
     * Send event registration confirmation email
     */
    public static function sendEventRegistrationEmail($to_email, $to_name, $event_details, $qr_code) {
        $subject = "Event Registration Confirmed - " . $event_details['event_name'];
        
        // event_date contains both date and time in DATETIME format
        $event_date = date('l, F j, Y', strtotime($event_details['event_date']));
        $event_time = date('g:i A', strtotime($event_details['event_date']));
        
        // Generate QR code using the dedicated QRCode helper
        $qr_src = QRCode::getDataURL($qr_code, 250);
        
        // If QR generation completely fails, use text fallback
        if (empty($qr_src)) {
            error_log("QR code generation failed completely for code: " . $qr_code);
            $qr_src = 'data:image/svg+xml;base64,' . base64_encode('
                <svg width="250" height="250" xmlns="http://www.w3.org/2000/svg">
                    <rect width="250" height="250" fill="#f8f9fa" stroke="#dee2e6" stroke-width="2" rx="8"/>
                    <text x="125" y="100" text-anchor="middle" font-family="Arial, sans-serif" font-size="16" font-weight="bold" fill="#495057">Registration Code</text>
                    <text x="125" y="130" text-anchor="middle" font-family="monospace" font-size="18" fill="#212529" font-weight="bold">' . htmlspecialchars($qr_code) . '</text>
                    <text x="125" y="160" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" fill="#6c757d">Show this code at the event</text>
                </svg>
            ');
        }
        
        $html_body = self::getEmailTemplate([
            'title' => 'Event Registration Confirmed',
            'greeting' => "Hello " . htmlspecialchars($to_name) . ",",
            'content' => '
                <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                    You have successfully registered for the following event:
                </p>
                <div style="background: #f8f9fa; border-left: 4px solid #6366f1; padding: 20px; margin: 20px 0; border-radius: 8px;">
                    <h2 style="margin: 0 0 15px 0; color: #1a1a1a; font-size: 20px;">' . htmlspecialchars($event_details['event_name']) . '</h2>
                    <p style="margin: 8px 0; color: #666;"><strong>📅 Date:</strong> ' . $event_date . '</p>
                    <p style="margin: 8px 0; color: #666;"><strong>⏰ Time:</strong> ' . $event_time . '</p>
                    <p style="margin: 8px 0; color: #666;"><strong>📍 Venue:</strong> ' . htmlspecialchars($event_details['venue']) . '</p>
                    ' . (isset($event_details['department']) ? '<p style="margin: 8px 0; color: #666;"><strong>🎓 Department:</strong> ' . htmlspecialchars($event_details['department']) . '</p>' : '') . '
                </div>
                <div style="text-align: center; margin: 30px 0;">
                    <p style="margin: 10px 0; color: #666; font-size: 16px; font-weight: 600;">Your Event QR Code</p>
                    <div style="display: inline-block; padding: 20px; background: #ffffff; border: 2px solid #e5e7eb; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                        <img src="' . $qr_src . '" alt="QR Code" style="display: block; width: 200px; height: 200px; margin: 0 auto;" />
                    </div>
                    <p style="margin: 15px 0; color: #666; font-size: 14px;">Registration Code: <strong style="color: #6366f1; font-size: 16px; font-family: monospace;">' . htmlspecialchars($qr_code) . '</strong></p>
                    <p style="margin: 10px 0; color: #999; font-size: 13px;">Present this QR code at the event venue for check-in</p>
                    <p style="margin: 10px 0; color: #999; font-size: 12px; font-style: italic;">If the QR code is not visible, use the registration code above at the event.</p>
                </div>
            ',
            'button_text' => 'View Event Details',
            'button_url' => BASE_URL . '/student/my-events.php',
            'footer_text' => 'If you have any questions, please contact the event organizer.',
        ]);
        
        $text_body = self::getTextTemplate([
            'greeting' => "Hello " . $to_name . ",",
            'content' => "You have successfully registered for the following event:\n\n" .
                "Event: " . $event_details['event_name'] . "\n" .
                "Date: " . $event_date . "\n" .
                "Time: " . $event_time . "\n" .
                "Venue: " . $event_details['venue'] . "\n\n" .
                "Your unique registration code: " . $qr_code . "\n\n" .
                "Please save this email or present your QR code at the event venue for check-in.\n\n" .
                "View event details: " . BASE_URL . "/student/my-events.php",
        ]);
        
        return self::send($to_email, $subject, $html_body, $text_body);
    }
    
    /**
     * Send password reset email
     */
    public static function sendPasswordResetEmail($to_email, $to_name, $reset_token) {
        $reset_url = BASE_URL . "/reset-password.php?token=" . urlencode($reset_token);
        
        $subject = "Reset Your Password - " . APP_NAME;
        
        $html_body = self::getEmailTemplate([
            'title' => 'Reset Your Password',
            'greeting' => "Hello " . htmlspecialchars($to_name) . ",",
            'content' => '
                <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                    We received a request to reset your password. Click the button below to create a new password.
                </p>
            ',
            'button_text' => 'Reset Password',
            'button_url' => $reset_url,
            'footer_text' => 'This link will expire in 1 hour. If you did not request a password reset, please ignore this email.',
            'alternative_text' => 'Or copy and paste this link into your browser: ' . $reset_url
        ]);
        
        $text_body = self::getTextTemplate([
            'greeting' => "Hello " . $to_name . ",",
            'content' => "We received a request to reset your password. Click the link below to create a new password:\n\n" . $reset_url . "\n\nThis link will expire in 1 hour. If you did not request a password reset, please ignore this email.",
        ]);
        
        return self::send($to_email, $subject, $html_body, $text_body);
    }

    /**
     * Send event reminder email (1 day or 1 hour before)
     */
    public static function sendEventReminderEmail($to_email, $to_name, $event_details, $reminder_type = '1_day') {
        $hours_before = $reminder_type === '1_hour' ? 1 : 24;
        $time_text = $reminder_type === '1_hour' ? '1 hour' : '1 day';
        
        $subject = "Event Reminder: " . $event_details['event_name'] . " - Starting in " . $time_text;
        
        $event_date = date('l, F j, Y', strtotime($event_details['event_date']));
        $event_time = date('g:i A', strtotime($event_details['event_date']));
        
        $html_body = self::getEmailTemplate([
            'title' => 'Event Reminder',
            'greeting' => "Hello " . htmlspecialchars($to_name) . ",",
            'content' => '
                <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                    This is a friendly reminder that you are registered for the following event starting in <strong>' . $time_text . '</strong>:
                </p>
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h2 style="margin: 0 0 15px 0; color: #856404; font-size: 20px;">⏰ ' . htmlspecialchars($event_details['event_name']) . '</h2>
                    <p style="margin: 8px 0; color: #856404;"><strong>📅 Date:</strong> ' . $event_date . '</p>
                    <p style="margin: 8px 0; color: #856404;"><strong>⏰ Time:</strong> ' . $event_time . '</p>
                    <p style="margin: 8px 0; color: #856404;"><strong>📍 Venue:</strong> ' . htmlspecialchars($event_details['venue']) . '</p>
                </div>
                <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                    Don\'t forget to bring your registration confirmation or QR code for quick check-in!
                </p>
            ',
            'button_text' => 'View Event Details',
            'button_url' => BASE_URL . '/student/my-events.php',
            'footer_text' => 'We look forward to seeing you at the event!',
        ]);
        
        $text_body = self::getTextTemplate([
            'greeting' => "Hello " . $to_name . ",",
            'content' => "This is a friendly reminder that you are registered for the following event starting in " . $time_text . ":\n\n" .
                "Event: " . $event_details['event_name'] . "\n" .
                "Date: " . $event_date . "\n" .
                "Time: " . $event_time . "\n" .
                "Venue: " . $event_details['venue'] . "\n\n" .
                "Don't forget to bring your registration confirmation or QR code for quick check-in!\n\n" .
                "View event details: " . BASE_URL . "/student/my-events.php",
        ]);
        
        return self::send($to_email, $subject, $html_body, $text_body);
    }
    
    /**
     * Send event cancellation/update notification
     */
    public static function sendEventUpdateNotification($to_email, $to_name, $event_details, $update_type = 'updated', $update_message = '') {
        $is_cancelled = $update_type === 'cancelled';
        $subject = ($is_cancelled ? 'Event Cancelled: ' : 'Event Updated: ') . $event_details['event_name'];
        
        $event_date = date('l, F j, Y', strtotime($event_details['event_date']));
        $event_time = date('g:i A', strtotime($event_details['event_date']));
        
        $html_body = self::getEmailTemplate([
            'title' => $is_cancelled ? 'Event Cancelled' : 'Event Updated',
            'greeting' => "Hello " . htmlspecialchars($to_name) . ",",
            'content' => '
                <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                    ' . ($is_cancelled ? 
                        'We regret to inform you that the following event has been <strong style="color: #dc3545;">cancelled</strong>:' : 
                        'The following event has been <strong style="color: #0066cc;">updated</strong>:'
                    ) . '
                </p>
                <div style="background: ' . ($is_cancelled ? '#f8d7da' : '#cce5ff') . '; border: 1px solid ' . ($is_cancelled ? '#f5c6cb' : '#99ccff') . '; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h2 style="margin: 0 0 15px 0; color: ' . ($is_cancelled ? '#721c24' : '#004080') . '; font-size: 20px;">' . ($is_cancelled ? '❌' : '📝') . ' ' . htmlspecialchars($event_details['event_name']) . '</h2>
                    <p style="margin: 8px 0; color: ' . ($is_cancelled ? '#721c24' : '#004080') . ';"><strong>📅 Date:</strong> ' . $event_date . '</p>
                    <p style="margin: 8px 0; color: ' . ($is_cancelled ? '#721c24' : '#004080') . ';"><strong>⏰ Time:</strong> ' . $event_time . '</p>
                    <p style="margin: 8px 0; color: ' . ($is_cancelled ? '#721c24' : '#004080') . ';"><strong>📍 Venue:</strong> ' . htmlspecialchars($event_details['venue']) . '</p>
                </div>
                ' . ($update_message ? '<div style="background: #f8f9fa; border-left: 4px solid #6366f1; padding: 15px; margin: 20px 0; border-radius: 4px;"><p style="margin: 0; color: #666; font-weight: 600;">Update Details:</p><p style="margin: 10px 0 0 0; color: #666;">' . nl2br(htmlspecialchars($update_message)) . '</p></div>' : '') . '
                <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                    ' . ($is_cancelled ? 
                        'If you have any questions about this cancellation, please contact the event organizer.' : 
                        'Please make note of any changes and check the event details for the latest information.'
                    ) . '
                </p>
            ',
            'button_text' => $is_cancelled ? 'View My Events' : 'View Updated Event',
            'button_url' => BASE_URL . '/student/my-events.php',
            'footer_text' => $is_cancelled ? 'We apologize for any inconvenience caused.' : 'Thank you for your understanding.',
        ]);
        
        $text_body = self::getTextTemplate([
            'greeting' => "Hello " . $to_name . ",",
            'content' => ($is_cancelled ? 
                "We regret to inform you that the following event has been cancelled:\n\n" :
                "The following event has been updated:\n\n"
            ) .
                "Event: " . $event_details['event_name'] . "\n" .
                "Date: " . $event_date . "\n" .
                "Time: " . $event_time . "\n" .
                "Venue: " . $event_details['venue'] . "\n\n" .
                ($update_message ? "Update Details: " . $update_message . "\n\n" : '') .
                ($is_cancelled ? 
                    "If you have any questions about this cancellation, please contact the event organizer." :
                    "Please make note of any changes and check the event details for the latest information."
                ) . "\n\n" .
                "View events: " . BASE_URL . "/student/my-events.php",
        ]);
        
        return self::send($to_email, $subject, $html_body, $text_body);
    }
    
    /**
     * Send weekly digest email for upcoming events
     */
    public static function sendWeeklyDigestEmail($to_email, $to_name, $upcoming_events) {
        if (empty($upcoming_events)) {
            return true; // No events to send
        }
        
        $subject = "Weekly Events Digest - " . count($upcoming_events) . " Upcoming Events";
        
        $events_html = '';
        $events_text = "";
        
        foreach ($upcoming_events as $event) {
            $event_date = date('M j', strtotime($event['event_date']));
            $event_time = date('g:i A', strtotime($event['event_date']));
            
            $events_html .= '
                <div style="background: #f8f9fa; border-left: 4px solid #6366f1; padding: 15px; margin: 15px 0; border-radius: 4px;">
                    <h3 style="margin: 0 0 8px 0; color: #1a1a1a; font-size: 16px;">' . htmlspecialchars($event['event_name']) . '</h3>
                    <p style="margin: 4px 0; color: #666; font-size: 14px;"><strong>📅</strong> ' . $event_date . ' at ' . $event_time . '</p>
                    <p style="margin: 4px 0; color: #666; font-size: 14px;"><strong>📍</strong> ' . htmlspecialchars($event['venue']) . '</p>
                    ' . (isset($event['description']) && $event['description'] ? '<p style="margin: 8px 0 4px 0; color: #666; font-size: 14px;">' . htmlspecialchars(substr($event['description'], 0, 100)) . (strlen($event['description']) > 100 ? '...' : '') . '</p>' : '') . '
                </div>
            ';
            
            $events_text .= "\n" . $event['event_name'] . "\n";
            $events_text .= "Date: " . $event_date . " at " . $event_time . "\n";
            $events_text .= "Venue: " . $event['venue'] . "\n";
            if (isset($event['description']) && $event['description']) {
                $events_text .= "Description: " . substr($event['description'], 0, 100) . (strlen($event['description']) > 100 ? '...' : '') . "\n";
            }
            $events_text .= "---\n";
        }
        
        $html_body = self::getEmailTemplate([
            'title' => 'Weekly Events Digest',
            'greeting' => "Hello " . htmlspecialchars($to_name) . ",",
            'content' => '
                <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                    Here are the upcoming events for this week that might interest you:
                </p>
                ' . $events_html . '
                <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                    Don\'t miss out on these exciting events! Register now to secure your spot.
                </p>
            ',
            'button_text' => 'View All Events',
            'button_url' => BASE_URL . '/student/events.php',
            'footer_text' => 'You can unsubscribe from these digest emails in your profile settings.',
        ]);
        
        $text_body = self::getTextTemplate([
            'greeting' => "Hello " . $to_name . ",",
            'content' => "Here are the upcoming events for this week that might interest you:\n" . $events_text . "\n" .
                "Don't miss out on these exciting events! Register now to secure your spot.\n\n" .
                "View all events: " . BASE_URL . "/student/events.php",
        ]);
        
        return self::send($to_email, $subject, $html_body, $text_body);
    }
    
    /**
     * Send admin notification for new event submission
     */
    public static function sendAdminEventSubmissionNotification($admin_emails, $event_details, $submitted_by) {
        if (empty($admin_emails)) {
            return false;
        }
        
        $subject = "New Event Submission: " . $event_details['event_name'];
        
        $event_date = date('l, F j, Y', strtotime($event_details['event_date']));
        $event_time = date('g:i A', strtotime($event_details['event_date']));
        
        $html_body = self::getEmailTemplate([
            'title' => 'New Event Submission',
            'greeting' => "Hello Admin,",
            'content' => '
                <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                    A new event has been submitted for approval:
                </p>
                <div style="background: #e3f2fd; border: 1px solid #bbdefb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h2 style="margin: 0 0 15px 0; color: #1565c0; font-size: 20px;">📅 ' . htmlspecialchars($event_details['event_name']) . '</h2>
                    <p style="margin: 8px 0; color: #1565c0;"><strong>👤 Submitted by:</strong> ' . htmlspecialchars($submitted_by['name']) . ' (' . htmlspecialchars($submitted_by['email']) . ')</p>
                    <p style="margin: 8px 0; color: #1565c0;"><strong>📅 Date:</strong> ' . $event_date . '</p>
                    <p style="margin: 8px 0; color: #1565c0;"><strong>⏰ Time:</strong> ' . $event_time . '</p>
                    <p style="margin: 8px 0; color: #1565c0;"><strong>📍 Venue:</strong> ' . htmlspecialchars($event_details['venue']) . '</p>
                    <p style="margin: 8px 0; color: #1565c0;"><strong>🎓 Department:</strong> ' . htmlspecialchars($event_details['department'] ?? 'N/A') . '</p>
                    ' . (isset($event_details['max_participants']) ? '<p style="margin: 8px 0; color: #1565c0;"><strong>👥 Max Participants:</strong> ' . $event_details['max_participants'] . '</p>' : '') . '
                    ' . (isset($event_details['description']) && $event_details['description'] ? '<p style="margin: 12px 0 0 0; color: #1565c0;"><strong>Description:</strong><br>' . nl2br(htmlspecialchars($event_details['description'])) . '</p>' : '') . '
                </div>
                <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                    Please review and approve/reject this event submission.
                </p>
            ',
            'button_text' => 'Review Event',
            'button_url' => BASE_URL . '/admin/events.php',
            'footer_text' => 'This event is pending approval and will not be visible to students until approved.',
        ]);
        
        $text_body = self::getTextTemplate([
            'greeting' => "Hello Admin,",
            'content' => "A new event has been submitted for approval:\n\n" .
                "Event: " . $event_details['event_name'] . "\n" .
                "Submitted by: " . $submitted_by['name'] . " (" . $submitted_by['email'] . ")\n" .
                "Date: " . $event_date . "\n" .
                "Time: " . $event_time . "\n" .
                "Venue: " . $event_details['venue'] . "\n" .
                "Department: " . ($event_details['department'] ?? 'N/A') . "\n" .
                (isset($event_details['max_participants']) ? "Max Participants: " . $event_details['max_participants'] . "\n" : '') .
                (isset($event_details['description']) && $event_details['description'] ? "Description: " . $event_details['description'] . "\n" : '') .
                "\nPlease review and approve/reject this event submission.\n\n" .
                "Review event: " . BASE_URL . "/admin/events.php",
        ]);
        
        // Send to multiple admins
        $success = true;
        foreach ($admin_emails as $admin_email) {
            if (!self::send($admin_email, $subject, $html_body, $text_body)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Core send function
     */
    private static function send($to, $subject, $html_body, $text_body = null) {
        self::init();
        
        try {
            // Use SMTP if configured, otherwise fall back to PHP mail()
            if (self::$use_smtp && defined('MAIL_HOST')) {
                return self::sendViaSMTP($to, $subject, $html_body, $text_body);
            } else {
                return self::sendViaMail($to, $subject, $html_body, $text_body);
            }
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add email to queue for bulk processing
     */
    public static function queueEmail($to, $subject, $html_body, $text_body = null, $priority = 'normal', $send_at = null) {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Create email_queue table if it doesn't exist
            self::createEmailQueueTable($conn);
            
            $stmt = $conn->prepare("INSERT INTO email_queue (to_email, subject, html_body, text_body, priority, send_at, created_at, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')");
            
            $send_at = $send_at ?? date('Y-m-d H:i:s');
            
            $result = $stmt->execute([$to, $subject, $html_body, $text_body, $priority, $send_at]);
            
            if ($result) {
                error_log("Email queued successfully for: " . $to);
                return true;
            } else {
                error_log("Failed to queue email for: " . $to);
                return false;
            }
        } catch (Exception $e) {
            error_log("Email queue error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process email queue (run this via cron job)
     */
    public static function processEmailQueue($limit = 50) {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Get pending emails that are ready to be sent
            $stmt = $conn->prepare("SELECT * FROM email_queue WHERE status = 'pending' AND send_at <= NOW() ORDER BY priority = 'high' DESC, priority = 'normal' DESC, created_at ASC LIMIT ?");
            $stmt->execute([$limit]);
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $processed = 0;
            $successful = 0;            
            foreach ($emails as $email) {
                $processed++;
                
                // Update status to processing
                $update_stmt = $conn->prepare("UPDATE email_queue SET status = 'processing', processed_at = NOW() WHERE id = ?");
                $update_stmt->execute([$email['id']]);
                
                // Try to send the email
                $sent = self::send($email['to_email'], $email['subject'], $email['html_body'], $email['text_body']);
                
                if ($sent) {
                    // Mark as sent
                    $update_stmt = $conn->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
                    $update_stmt->execute([$email['id']]);
                    $successful++;
                } else {
                    // Mark as failed and increment retry count
                    $update_stmt = $conn->prepare("UPDATE email_queue SET status = 'failed', retry_count = retry_count + 1, last_error = ? WHERE id = ?");
                    $error_msg = "Email sending failed at " . date('Y-m-d H:i:s');
                    $update_stmt->execute([$error_msg, $email['id']]);
                    
                    // If retry count is less than 3, reset to pending for retry later
                    if ($email['retry_count'] < 2) {
                        $retry_stmt = $conn->prepare("UPDATE email_queue SET status = 'pending', send_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?");
                        $retry_stmt->execute([$email['id']]);
                    }
                }
                
                // Add small delay to avoid overwhelming email server
                usleep(250000); // 250ms delay
            }
            
            error_log("Email queue processed: {$processed} emails, {$successful} successful");
            return ['processed' => $processed, 'successful' => $successful];
            
        } catch (Exception $e) {
            error_log("Email queue processing error: " . $e->getMessage());
            return ['processed' => 0, 'successful' => 0];
        }
    }
    
    /**
     * Bulk email functions
     */
    public static function queueBulkEventReminders($event_id, $reminder_type = '1_day') {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Get event details
            $event_stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
            $event_stmt->execute([$event_id]);
            $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$event) {
                return false;
            }
            
            // Get all registered users for this event
            $users_stmt = $conn->prepare("
                SELECT u.email, u.name 
                FROM users u 
                INNER JOIN event_registrations er ON u.id = er.user_id 
                WHERE er.event_id = ? AND er.status = 'registered'
            ");
            $users_stmt->execute([$event_id]);
            $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $queued = 0;
            foreach ($users as $user) {
                $html_body = '';
                $text_body = '';
                
                // Generate email content
                ob_start();
                $subject = "Event Reminder: " . $event['event_name'] . " - Starting in " . ($reminder_type === '1_hour' ? '1 hour' : '1 day');                
                $event_date = date('l, F j, Y', strtotime($event['event_date']));
                $event_time = date('g:i A', strtotime($event['event_date']));
                
                $html_body = self::getEmailTemplate([\n                    'title' => 'Event Reminder',\n                    'greeting' => \"Hello \" . htmlspecialchars($user['name']) . \",\",\n                    'content' => '\n                        <p style=\"margin: 20px 0; color: #666; line-height: 1.6;\">\n                            This is a friendly reminder that you are registered for the following event starting in <strong>' . ($reminder_type === '1_hour' ? '1 hour' : '1 day') . '</strong>:\n                        </p>\n                        <div style=\"background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 20px 0;\">\n                            <h2 style=\"margin: 0 0 15px 0; color: #856404; font-size: 20px;\">⏰ ' . htmlspecialchars($event['event_name']) . '</h2>\n                            <p style=\"margin: 8px 0; color: #856404;\"><strong>📅 Date:</strong> ' . $event_date . '</p>\n                            <p style=\"margin: 8px 0; color: #856404;\"><strong>⏰ Time:</strong> ' . $event_time . '</p>\n                            <p style=\"margin: 8px 0; color: #856404;\"><strong>📍 Venue:</strong> ' . htmlspecialchars($event['venue']) . '</p>\n                        </div>\n                    ',\n                    'button_text' => 'View Event Details',\n                    'button_url' => BASE_URL . '/student/my-events.php',\n                ]);\n                \n                $text_body = self::getTextTemplate([\n                    'greeting' => \"Hello \" . $user['name'] . \",\",\n                    'content' => \"Reminder: Event starting in \" . ($reminder_type === '1_hour' ? '1 hour' : '1 day') . \":\\n\\n\" .\n                        \"Event: \" . $event['event_name'] . \"\\n\" .\n                        \"Date: \" . $event_date . \"\\n\" .\n                        \"Time: \" . $event_time . \"\\n\" .\n                        \"Venue: \" . $event['venue'],\n                ]);\n                
                if (self::queueEmail($user['email'], $subject, $html_body, $text_body, 'high')) {
                    $queued++;
                }
            }\n            \n            error_log(\"Queued {$queued} reminder emails for event: {$event['event_name']}\");\n            return $queued;\n            \n        } catch (Exception $e) {\n            error_log(\"Bulk reminder queue error: \" . $e->getMessage());\n            return false;\n        }\n    }\n    \n    public static function queueBulkWeeklyDigests() {\n        try {\n            $db = Database::getInstance();\n            $conn = $db->getConnection();\n            \n            // Get all active users who haven't opted out of digest emails\n            $users_stmt = $conn->prepare("SELECT email, name FROM users WHERE status = 'active' AND email_notifications = 1");\n            $users_stmt->execute();\n            $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);\n            \n            // Get upcoming events for the week\n            $events_stmt = $conn->prepare("\n                SELECT event_name, event_date, venue, description \n                FROM events \n                WHERE status = 'approved' \n                AND event_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)\n                ORDER BY event_date ASC\n            ");\n            $events_stmt->execute();\n            $upcoming_events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);\n            \n            if (empty($upcoming_events)) {\n                error_log("No upcoming events for weekly digest");\n                return 0;\n            }\n            \n            $queued = 0;\n            foreach ($users as $user) {\n                $subject = \"Weekly Events Digest - \" . count($upcoming_events) . \" Upcoming Events\";\n                \n                $events_html = '';\n                $events_text = \"\";\n                \n                foreach ($upcoming_events as $event) {\n                    $event_date = date('M j', strtotime($event['event_date']));\n                    $event_time = date('g:i A', strtotime($event['event_date']));\n                    \n                    $events_html .= '\n                        <div style=\"background: #f8f9fa; border-left: 4px solid #6366f1; padding: 15px; margin: 15px 0; border-radius: 4px;\">\n                            <h3 style=\"margin: 0 0 8px 0; color: #1a1a1a; font-size: 16px;\">' . htmlspecialchars($event['event_name']) . '</h3>\n                            <p style=\"margin: 4px 0; color: #666; font-size: 14px;\"><strong>📅</strong> ' . $event_date . ' at ' . $event_time . '</p>\n                            <p style=\"margin: 4px 0; color: #666; font-size: 14px;\"><strong>📍</strong> ' . htmlspecialchars($event['venue']) . '</p>\n                        </div>\n                    ';\n                    \n                    $events_text .= \"\\n\" . $event['event_name'] . \"\\n\";\n                    $events_text .= \"Date: \" . $event_date . \" at \" . $event_time . \"\\n\";\n                    $events_text .= \"Venue: \" . $event['venue'] . \"\\n---\\n\";\n                }\n                \n                $html_body = self::getEmailTemplate([\n                    'title' => 'Weekly Events Digest',\n                    'greeting' => \"Hello \" . htmlspecialchars($user['name']) . \",\",\n                    'content' => '\n                        <p style=\"margin: 20px 0; color: #666; line-height: 1.6;\">\n                            Here are the upcoming events for this week:\n                        </p>\n                        ' . $events_html . '\n                    ',\n                    'button_text' => 'View All Events',\n                    'button_url' => BASE_URL . '/student/events.php',\n                ]);\n                \n                $text_body = self::getTextTemplate([\n                    'greeting' => \"Hello \" . $user['name'] . \",\",\n                    'content' => \"Here are the upcoming events for this week:\\n\" . $events_text,\n                ]);\n                \n                if (self::queueEmail($user['email'], $subject, $html_body, $text_body, 'low')) {\n                    $queued++;\n                }\n            }\n            \n            error_log(\"Queued {$queued} weekly digest emails\");\n            return $queued;\n            \n        } catch (Exception $e) {\n            error_log(\"Weekly digest queue error: \" . $e->getMessage());\n            return false;\n        }\n    }\n    \n    /**\n     * Create email queue table if it doesn't exist\n     */\n    private static function createEmailQueueTable($conn) {\n        try {\n            $sql = \"CREATE TABLE IF NOT EXISTS email_queue (\n                id INT AUTO_INCREMENT PRIMARY KEY,\n                to_email VARCHAR(255) NOT NULL,\n                subject VARCHAR(500) NOT NULL,\n                html_body LONGTEXT NOT NULL,\n                text_body LONGTEXT,\n                priority ENUM('low', 'normal', 'high') DEFAULT 'normal',\n                status ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',\n                retry_count INT DEFAULT 0,\n                last_error TEXT,\n                send_at DATETIME NOT NULL,\n                created_at DATETIME NOT NULL,\n                processed_at DATETIME NULL,\n                sent_at DATETIME NULL,\n                INDEX idx_status_send_at (status, send_at),\n                INDEX idx_priority (priority),\n                INDEX idx_created_at (created_at)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\";\n            \n            $conn->exec($sql);\n            return true;\n        } catch (Exception $e) {\n            error_log(\"Failed to create email_queue table: \" . $e->getMessage());\n            return false;\n        }\n    }
    
    /**
     * Send email using PHP mail() function
     */
    private static function sendViaMail($to, $subject, $html_body, $text_body = null) {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'From: ' . self::$from_name . ' <' . self::$from_email . '>';
        $headers[] = 'Reply-To: ' . self::$from_email;
        $headers[] = 'Return-Path: ' . self::$from_email;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        $headers[] = 'X-Priority: 3';
        $headers[] = 'X-MSMail-Priority: Normal';
        $headers[] = 'Importance: Normal';
        $headers[] = 'Message-ID: <' . time() . '.' . md5($to . microtime()) . '@' . $_SERVER['HTTP_HOST'] . '>';
        
        // Multipart email
        $boundary = md5(time());
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        
        $message = "--" . $boundary . "\r\n";
        
        // Plain text version
        if ($text_body) {
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $text_body . "\r\n\r\n";
            $message .= "--" . $boundary . "\r\n";
        }
        
        // HTML version
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $html_body . "\r\n\r\n";
        $message .= "--" . $boundary . "--";
        
        $result = mail($to, $subject, $message, implode("\r\n", $headers));
        
        if ($result) {
            error_log("Email sent successfully to: " . $to);
            return true;
        } else {
            error_log("Failed to send email to: " . $to);
            return false;
        }
    }
    
    /**
     * Send email using SMTP (for production)
     * This uses fsockopen to create a basic SMTP client
     */
    private static function sendViaSMTP($to, $subject, $html_body, $text_body = null) {
        $host = MAIL_HOST;
        $port = MAIL_PORT;
        $username = MAIL_USERNAME;
        $password = MAIL_PASSWORD;
        $encryption = defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'tls';
        
        // Create socket connection
        if ($encryption === 'ssl') {
            $socket = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 30);
        } else {
            $socket = @fsockopen($host, $port, $errno, $errstr, 30);
        }
        
        if (!$socket) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }
        
        // Read server response
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP Error: " . $response);
            fclose($socket);
            return false;
        }
        
        // Send EHLO
        fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        
        // Read all EHLO response lines (multi-line response)
        do {
            $response = fgets($socket, 515);
        } while ($response && $response[3] == '-');
        
        // Start TLS if needed
        if ($encryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            
            if (substr($response, 0, 3) != '220') {
                error_log("STARTTLS failed: " . $response);
                fclose($socket);
                return false;
            }
            
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("TLS encryption failed");
                fclose($socket);
                return false;
            }
            
            // Send EHLO again after TLS
            fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            
            // Read all EHLO response lines again
            do {
                $response = fgets($socket, 515);
            } while ($response && $response[3] == '-');
        }
        
        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        
        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 515);
        
        fputs($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '235') {
            error_log("SMTP Authentication failed: " . $response);
            fclose($socket);
            return false;
        }
        
        // Send email
        fputs($socket, "MAIL FROM: <" . self::$from_email . ">\r\n");
        $response = fgets($socket, 515);
        
        fputs($socket, "RCPT TO: <" . $to . ">\r\n");
        $response = fgets($socket, 515);
        
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        
        // Prepare message
        $boundary = md5(time());
        $message = "From: " . self::$from_name . " <" . self::$from_email . ">\r\n";
        $message .= "To: <" . $to . ">\r\n";
        $message .= "Subject: " . $subject . "\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n\r\n";
        
        $message .= "--" . $boundary . "\r\n";
        if ($text_body) {
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $text_body . "\r\n\r\n";
            $message .= "--" . $boundary . "\r\n";
        }
        
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $html_body . "\r\n\r\n";
        $message .= "--" . $boundary . "--\r\n";
        $message .= ".\r\n";
        
        fputs($socket, $message);
        $response = fgets($socket, 515);
        
        // Quit
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        if (substr($response, 0, 3) == '250') {
            error_log("Email sent successfully via SMTP to: " . $to);
            return true;
        } else {
            error_log("SMTP send failed: " . $response);
            return false;
        }
    }
    
    /**
     * Get HTML email template
     */
    private static function getEmailTemplate($data) {
        $title = $data['title'] ?? 'Notification';
        $greeting = $data['greeting'] ?? 'Hello,';
        $content = $data['content'] ?? '';
        $button_text = $data['button_text'] ?? '';
        $button_url = $data['button_url'] ?? '';
        $footer_text = $data['footer_text'] ?? '';
        $alternative_text = $data['alternative_text'] ?? '';
        
        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 20px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px 16px 0 0; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">' . APP_NAME . '</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px 0; color: #1a1a1a; font-size: 24px; font-weight: 600;">' . $title . '</h2>
                            <p style="margin: 0 0 20px 0; color: #666; font-size: 16px; line-height: 1.6;">' . $greeting . '</p>
                            ' . $content . '
                            ' . ($button_url ? '
                            <table role="presentation" style="margin: 30px 0;">
                                <tr>
                                    <td style="border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <a href="' . htmlspecialchars($button_url) . '" style="display: inline-block; padding: 16px 40px; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px;">' . htmlspecialchars($button_text) . '</a>
                                    </td>
                                </tr>
                            </table>
                            ' : '') . '
                            ' . ($alternative_text ? '<p style="margin: 20px 0; color: #999; font-size: 14px; line-height: 1.6;">' . htmlspecialchars($alternative_text) . '</p>' : '') . '
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f8f9fa; border-radius: 0 0 16px 16px;">
                            ' . ($footer_text ? '<p style="margin: 0 0 15px 0; color: #666; font-size: 14px; line-height: 1.6;">' . htmlspecialchars($footer_text) . '</p>' : '') . '
                            <p style="margin: 0; color: #999; font-size: 13px; line-height: 1.5;">
                                &copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.<br>
                                This is an automated email, please do not reply.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
    
    /**
     * Get plain text email template
     */
    private static function getTextTemplate($data) {
        $greeting = $data['greeting'] ?? 'Hello,';
        $content = $data['content'] ?? '';
        
        return $greeting . "\n\n" . 
               $content . "\n\n" .
               "---\n" .
               APP_NAME . "\n" .
               "© " . date('Y') . " All rights reserved.\n" .
               "This is an automated email, please do not reply.";
    }
}
