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
                <p style="margin: 20px 0; color: rgba(255, 255, 255, 0.85); line-height: 1.6;">
                    Thank you for registering with ' . APP_NAME . '. To complete your registration and access all features, please verify your email address.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <p style="margin: 10px 0; color: rgba(255, 255, 255, 0.7); font-size: 14px; font-weight: 600;">Your verification code:</p>
                    <div style="display: inline-block; padding: 20px 40px; background: rgba(99, 102, 241, 0.1); border: 2px dashed #6366f1; border-radius: 12px; margin: 15px 0; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.2);">
                        <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #a78bfa; font-family: monospace; text-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);">' . $verification_code . '</span>
                    </div>
                    <p style="margin: 10px 0; color: rgba(255, 255, 255, 0.5); font-size: 13px;">Copy and paste this code on the verification page</p>
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
        
        // Try to generate an event ticket if we have enough details
        $ticket_src = '';
        if (isset($event_details['user_name']) && isset($event_details['roll_number'])) {
            $participant_details = [
                'full_name' => $event_details['user_name'],
                'roll_number' => $event_details['roll_number'],
                'department' => $event_details['department'] ?? ''
            ];
            
            $ticket_result = QRCode::generateEventTicket($event_details, $participant_details, $qr_code);
            if ($ticket_result['success']) {
                $ticket_src = 'data:image/svg+xml;base64,' . $ticket_result['base64'];
            }
        }
        
        $html_body = self::getEmailTemplate([
            'title' => 'Event Registration Confirmed',
            'greeting' => "Hello " . htmlspecialchars($to_name) . ",",
            'content' => '
                <p style="margin: 20px 0; color: rgba(255, 255, 255, 0.85); line-height: 1.6;">
                    You have successfully registered for the following event:
                </p>
                <div style="background: rgba(14, 165, 233, 0.1); backdrop-filter: blur(12px); border-left: 4px solid #0ea5e9; padding: 24px; margin: 20px 0; border-radius: 14px; box-shadow: 0 4px 16px rgba(14, 165, 233, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.05); border: 1px solid rgba(14, 165, 233, 0.15);">
                    <h2 style="margin: 0 0 18px 0; color: #ffffff; font-size: 21px; font-weight: 700; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">' . htmlspecialchars($event_details['event_name']) . '</h2>
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td style="padding: 10px 0;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right: 12px; vertical-align: top;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="display: block;">
                                                <rect x="3" y="6" width="18" height="15" rx="2" stroke="#0ea5e9" stroke-width="2" fill="rgba(14, 165, 233, 0.15)"/>
                                                <path d="M3 10h18M8 3v4M16 3v4" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round"/>
                                                <circle cx="8" cy="14" r="1" fill="#06b6d4"/>
                                                <circle cx="12" cy="14" r="1" fill="#06b6d4"/>
                                                <circle cx="16" cy="14" r="1" fill="#06b6d4"/>
                                            </svg>
                                        </td>
                                        <td style="color: rgba(255, 255, 255, 0.85); font-size: 15px; line-height: 1.5;"><strong style="color: rgba(255, 255, 255, 0.95);">Date:</strong> ' . $event_date . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right: 12px; vertical-align: top;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="display: block;">
                                                <circle cx="12" cy="12" r="9" stroke="#0ea5e9" stroke-width="2" fill="rgba(14, 165, 233, 0.15)"/>
                                                <path d="M12 6v6l4 2" stroke="#06b6d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </td>
                                        <td style="color: rgba(255, 255, 255, 0.85); font-size: 15px; line-height: 1.5;"><strong style="color: rgba(255, 255, 255, 0.95);">Time:</strong> ' . $event_time . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right: 12px; vertical-align: top;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="display: block;">
                                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" stroke="#0ea5e9" stroke-width="2" fill="rgba(14, 165, 233, 0.15)" stroke-linecap="round" stroke-linejoin="round"/>
                                                <circle cx="12" cy="9" r="2.5" fill="#06b6d4"/>
                                            </svg>
                                        </td>
                                        <td style="color: rgba(255, 255, 255, 0.85); font-size: 15px; line-height: 1.5;"><strong style="color: rgba(255, 255, 255, 0.95);">Venue:</strong> ' . htmlspecialchars($event_details['venue']) . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        ' . (isset($event_details['department']) ? '
                        <tr>
                            <td style="padding: 10px 0;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right: 12px; vertical-align: top;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="display: block;">
                                                <path d="M12 3L2 9l10 6 10-6-10-6z" stroke="#0ea5e9" stroke-width="2" fill="rgba(14, 165, 233, 0.15)" stroke-linejoin="round"/>
                                                <path d="M2 17l10 6 10-6M2 13l10 6 10-6" stroke="#06b6d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </td>
                                        <td style="color: rgba(255, 255, 255, 0.85); font-size: 15px; line-height: 1.5;"><strong style="color: rgba(255, 255, 255, 0.95);">Department:</strong> ' . htmlspecialchars($event_details['department']) . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        ' : '') . '
                    </table>
                </div>
                
                ' . ($ticket_src ? '
                <div style="text-align: center; margin: 32px 0;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td align="center">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right: 10px; vertical-align: middle;">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="display: block;">
                                                <rect x="2" y="6" width="20" height="12" rx="2" stroke="#0ea5e9" stroke-width="2" fill="rgba(14, 165, 233, 0.15)"/>
                                                <path d="M2 10h20" stroke="#06b6d4" stroke-width="2"/>
                                                <circle cx="8" cy="14" r="1" fill="#38bdf8"/>
                                                <circle cx="12" cy="14" r="1" fill="#38bdf8"/>
                                                <circle cx="16" cy="14" r="1" fill="#38bdf8"/>
                                            </svg>
                                        </td>
                                        <td>
                                            <p style="margin: 0; color: rgba(255, 255, 255, 0.95); font-size: 17px; font-weight: 700; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">Your Event Ticket</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                    <div style="display: inline-block; margin: 24px 0; padding: 8px; background: rgba(14, 165, 233, 0.1); backdrop-filter: blur(16px); border-radius: 16px; border: 1px solid rgba(14, 165, 233, 0.2); box-shadow: 0 8px 32px rgba(14, 165, 233, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.08);">
                        <img src="' . $ticket_src . '" alt="Event Ticket" style="max-width: 100%; height: auto; border-radius: 12px; display: block;" />
                    </div>
                    <p style="margin: 16px 0; color: rgba(255, 255, 255, 0.65); font-size: 14px;">Present this ticket at the event venue for check-in</p>
                </div>
                ' : '
                <div style="text-align: center; margin: 32px 0;">
                    <p style="margin: 12px 0; color: rgba(255, 255, 255, 0.95); font-size: 17px; font-weight: 700; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">Your Event QR Code</p>
                    <div style="display: inline-block; padding: 24px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(255, 255, 255, 0.95)); border: 2px solid rgba(14, 165, 233, 0.3); border-radius: 20px; box-shadow: 0 8px 32px rgba(14, 165, 233, 0.25), inset 0 1px 0 rgba(255, 255, 255, 1), 0 0 0 1px rgba(14, 165, 233, 0.1);">
                        <img src="' . $qr_src . '" alt="QR Code" style="display: block; width: 200px; height: 200px; margin: 0 auto; border-radius: 8px;" />
                    </div>
                    <div style="margin: 20px 0; padding: 16px 24px; background: rgba(14, 165, 233, 0.08); backdrop-filter: blur(8px); border-radius: 12px; display: inline-block; border: 1px solid rgba(14, 165, 233, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);">
                        <p style="margin: 0; color: rgba(255, 255, 255, 0.9); font-size: 14px;">
                            Registration Code: <strong style="background: linear-gradient(135deg, #0ea5e9, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 18px; font-family: \'Courier New\', monospace; letter-spacing: 1px; padding: 0 8px;">' . htmlspecialchars($qr_code) . '</strong>
                        </p>
                    </div>
                    <p style="margin: 12px 0; color: rgba(255, 255, 255, 0.65); font-size: 14px;">Present this QR code at the event venue for check-in</p>
                    <p style="margin: 10px 0; color: rgba(255, 255, 255, 0.5); font-size: 12px; font-style: italic;">If the QR code is not visible, use the registration code above at the event.</p>
                </div>
                ') . '
                
                <div style="background: rgba(14, 165, 233, 0.08); backdrop-filter: blur(10px); border-radius: 14px; padding: 24px; margin: 24px 0; border: 1px solid rgba(14, 165, 233, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 4px 12px rgba(0, 0, 0, 0.3);">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td>
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right: 12px; vertical-align: top;">
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" style="display: block;">
                                                <rect x="3" y="3" width="18" height="18" rx="2" stroke="#0ea5e9" stroke-width="2" fill="rgba(14, 165, 233, 0.2)"/>
                                                <path d="M9 11l3 3 6-6" stroke="#06b6d4" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                <circle cx="12" cy="12" r="10" stroke="url(#glow)" stroke-width="0.5" opacity="0.3"/>
                                                <defs>
                                                    <linearGradient id="glow" x1="0%" y1="0%" x2="100%" y2="100%">
                                                        <stop offset="0%" style="stop-color:#0ea5e9;stop-opacity:1" />
                                                        <stop offset="100%" style="stop-color:#06b6d4;stop-opacity:1" />
                                                    </linearGradient>
                                                </defs>
                                            </svg>
                                        </td>
                                        <td>
                                            <h3 style="margin: 0 0 12px 0; color: #38bdf8; font-size: 17px; font-weight: 700;">Quick Access Tips</h3>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-top: 8px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="padding-right: 10px; vertical-align: top; padding-top: 2px;">
                                                        <svg width="6" height="6" viewBox="0 0 6 6" style="display: block;">
                                                            <circle cx="3" cy="3" r="3" fill="#06b6d4"/>
                                                        </svg>
                                                    </td>
                                                    <td style="color: rgba(255, 255, 255, 0.8); font-size: 14px; line-height: 1.6;">Save this email for easy access to your QR code</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="padding-right: 10px; vertical-align: top; padding-top: 2px;">
                                                        <svg width="6" height="6" viewBox="0 0 6 6" style="display: block;">
                                                            <circle cx="3" cy="3" r="3" fill="#06b6d4"/>
                                                        </svg>
                                                    </td>
                                                    <td style="color: rgba(255, 255, 255, 0.8); font-size: 14px; line-height: 1.6;">Screenshot the QR code for offline access</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="padding-right: 10px; vertical-align: top; padding-top: 2px;">
                                                        <svg width="6" height="6" viewBox="0 0 6 6" style="display: block;">
                                                            <circle cx="3" cy="3" r="3" fill="#06b6d4"/>
                                                        </svg>
                                                    </td>
                                                    <td style="color: rgba(255, 255, 255, 0.8); font-size: 14px; line-height: 1.6;">Arrive 15 minutes early for smooth check-in</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
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
                "Tips:\n" .
                "- Save this email for easy access to your QR code\n" .
                "- Screenshot the QR code for offline access\n" .
                "- Arrive 15 minutes early for smooth check-in\n\n" .
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
                <p style="margin: 20px 0; color: rgba(255, 255, 255, 0.85); line-height: 1.6;">
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
                <p style="margin: 20px 0; color: rgba(255, 255, 255, 0.85); line-height: 1.6;">
                    This is a friendly reminder that you are registered for the following event starting in <strong style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">' . $time_text . '</strong>:
                </p>
                <div style="background: rgba(251, 191, 36, 0.1); backdrop-filter: blur(12px); border: 2px solid rgba(251, 191, 36, 0.3); border-radius: 14px; padding: 24px; margin: 24px 0; box-shadow: 0 4px 16px rgba(251, 191, 36, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.05);">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td style="padding-bottom: 18px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right: 12px; vertical-align: middle;">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="display: block;">
                                                <circle cx="12" cy="12" r="10" stroke="#fbbf24" stroke-width="2" fill="rgba(251, 191, 36, 0.15)"/>
                                                <path d="M12 6v6l4 4" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                <circle cx="12" cy="12" r="2" fill="#fbbf24"/>
                                            </svg>
                                        </td>
                                        <td>
                                            <h2 style="margin: 0; color: #fbbf24; font-size: 21px; font-weight: 700; text-shadow: 0 2px 8px rgba(251, 191, 36, 0.3);">' . htmlspecialchars($event_details['event_name']) . '</h2>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right: 12px; vertical-align: top;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="display: block;">
                                                <rect x="3" y="6" width="18" height="15" rx="2" stroke="#fbbf24" stroke-width="2" fill="rgba(251, 191, 36, 0.15)"/>
                                                <path d="M3 10h18M8 3v4M16 3v4" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
                                                <circle cx="8" cy="14" r="1" fill="#fbbf24"/>
                                                <circle cx="12" cy="14" r="1" fill="#fbbf24"/>
                                                <circle cx="16" cy="14" r="1" fill="#fbbf24"/>
                                            </svg>
                                        </td>
                                        <td style="color: rgba(255, 255, 255, 0.85); font-size: 15px; line-height: 1.5;"><strong style="color: rgba(255, 255, 255, 0.95);">Date:</strong> ' . $event_date . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right: 12px; vertical-align: top;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="display: block;">
                                                <circle cx="12" cy="12" r="9" stroke="#fbbf24" stroke-width="2" fill="rgba(251, 191, 36, 0.15)"/>
                                                <path d="M12 6v6l4 2" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </td>
                                        <td style="color: rgba(255, 255, 255, 0.85); font-size: 15px; line-height: 1.5;"><strong style="color: rgba(255, 255, 255, 0.95);">Time:</strong> ' . $event_time . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right: 12px; vertical-align: top;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="display: block;">
                                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" stroke="#fbbf24" stroke-width="2" fill="rgba(251, 191, 36, 0.15)" stroke-linecap="round" stroke-linejoin="round"/>
                                                <circle cx="12" cy="9" r="2.5" fill="#f59e0b"/>
                                            </svg>
                                        </td>
                                        <td style="color: rgba(255, 255, 255, 0.85); font-size: 15px; line-height: 1.5;"><strong style="color: rgba(255, 255, 255, 0.95);">Venue:</strong> ' . htmlspecialchars($event_details['venue']) . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
                <div style="background: rgba(14, 165, 233, 0.08); backdrop-filter: blur(10px); border-left: 3px solid #0ea5e9; border-radius: 10px; padding: 20px; margin: 20px 0; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td style="padding-right: 12px; vertical-align: top;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="display: block;">
                                    <circle cx="12" cy="12" r="10" stroke="#0ea5e9" stroke-width="2" fill="rgba(14, 165, 233, 0.15)"/>
                                    <path d="M12 8v4M12 16h.01" stroke="#06b6d4" stroke-width="2.5" stroke-linecap="round"/>
                                </svg>
                            </td>
                            <td style="color: rgba(255, 255, 255, 0.85); font-size: 15px; line-height: 1.7;">
                                Don\'t forget to bring your registration confirmation or QR code for quick check-in!
                            </td>
                        </tr>
                    </table>
                </div>
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
     * Core send function
     */
    private static function send($to, $subject, $html_body, $text_body = null) {
        self::init();
        
        try {
            // Use SMTP if configured, otherwise fall back to mail()
            if (self::$use_smtp && defined('MAIL_HOST') && defined('MAIL_USERNAME')) {
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
     * Get HTML email template - Ultra Premium Dark Theme with Glassmorphism
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
    <meta name="color-scheme" content="dark">
    <meta name="supported-color-schemes" content="dark">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Inter, -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #000000;">
    <!-- Outer container with premium background -->
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: radial-gradient(ellipse at top, rgba(14, 165, 233, 0.15) 0%, #000000 50%); background-attachment: fixed;">
        <tr>
            <td align="center" style="padding: 60px 24px;">
                
                <!-- Main email card with glassmorphism -->
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="max-width: 580px; width: 100%; background: rgba(10, 10, 10, 0.8); backdrop-filter: blur(24px); border-radius: 20px; overflow: hidden; box-shadow: 0 0 0 1px rgba(14, 165, 233, 0.2), 0 20px 60px rgba(0, 0, 0, 0.9), inset 0 1px 0 rgba(255, 255, 255, 0.05);">
                    
                    <!-- Premium cyan glow top accent -->
                    <tr>
                        <td style="height: 3px; background: linear-gradient(90deg, transparent, #0ea5e9 30%, #06b6d4 70%, transparent); box-shadow: 0 0 24px rgba(14, 165, 233, 0.6), 0 0 48px rgba(6, 182, 212, 0.3);"></td>
                    </tr>
                    
                    <!-- Header section with gradient -->
                    <tr>
                        <td style="padding: 52px 52px 36px; text-align: center; background: linear-gradient(180deg, rgba(14, 165, 233, 0.03) 0%, transparent 100%);">
                            <div style="display: inline-block; position: relative;">
                                <h1 style="margin: 0; font-size: 36px; font-weight: 700; letter-spacing: -1.5px; background: linear-gradient(135deg, #ffffff 0%, #38bdf8 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; filter: drop-shadow(0 0 40px rgba(14, 165, 233, 0.4));">' . APP_NAME . '</h1>
                                <div style="height: 4px; margin-top: 16px; background: linear-gradient(90deg, transparent, #0ea5e9 20%, #06b6d4 50%, #0ea5e9 80%, transparent); border-radius: 2px; box-shadow: 0 0 16px rgba(14, 165, 233, 0.8), 0 0 32px rgba(6, 182, 212, 0.4);"></div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Title section with glass effect -->
                    <tr>
                        <td style="padding: 0 52px 36px;">
                            <div style="padding: 24px 28px; background: rgba(14, 165, 233, 0.06); backdrop-filter: blur(12px); border-radius: 12px; border: 1px solid rgba(14, 165, 233, 0.15); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08), 0 4px 12px rgba(0, 0, 0, 0.3);">
                                <h2 style="margin: 0; font-size: 24px; font-weight: 600; letter-spacing: -0.5px; color: #ffffff; line-height: 1.4; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);">' . $title . '</h2>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Content section -->
                    <tr>
                        <td style="padding: 0 52px 36px;">
                            <p style="margin: 0 0 28px; font-size: 16px; line-height: 1.75; color: rgba(255, 255, 255, 0.75); font-weight: 400;">' . $greeting . '</p>
                            <div style="font-size: 15px; line-height: 1.85; color: rgba(255, 255, 255, 0.7);">
                                ' . $content . '
                            </div>
                        </td>
                    </tr>
                    
                    ' . ($button_url ? '
                    <!-- Premium CTA Button with glassmorphism -->
                    <tr>
                        <td style="padding: 0 52px 44px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="background: linear-gradient(135deg, #0ea5e9, #06b6d4); border-radius: 12px; box-shadow: 0 0 32px rgba(14, 165, 233, 0.5), 0 8px 24px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.2); position: relative;">
                                                    <a href="' . htmlspecialchars($button_url) . '" style="display: inline-block; padding: 18px 48px; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px; letter-spacing: 0.3px; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);">' . htmlspecialchars($button_text) . '</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    ' : '') . '
                    
                    ' . ($alternative_text ? '
                    <!-- Alternative text with glass effect -->
                    <tr>
                        <td style="padding: 0 52px 44px;">
                            <div style="padding: 18px 20px; background: rgba(14, 165, 233, 0.04); backdrop-filter: blur(8px); border-left: 3px solid rgba(14, 165, 233, 0.5); border-radius: 8px; box-shadow: inset 0 0 20px rgba(14, 165, 233, 0.05);">
                                <p style="margin: 0; font-size: 14px; line-height: 1.7; color: rgba(255, 255, 255, 0.55);">' . htmlspecialchars($alternative_text) . '</p>
                            </div>
                        </td>
                    </tr>
                    ' : '') . '
                    
                    ' . ($footer_text ? '
                    <!-- Premium info box with glassmorphism -->
                    <tr>
                        <td style="padding: 0 52px 44px;">
                            <div style="padding: 24px 26px; background: rgba(14, 165, 233, 0.08); backdrop-filter: blur(16px); border: 1px solid rgba(14, 165, 233, 0.2); border-radius: 12px; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08), inset 0 0 24px rgba(14, 165, 233, 0.06), 0 4px 12px rgba(0, 0, 0, 0.3);">
                                <p style="margin: 0; font-size: 15px; line-height: 1.7; color: rgba(255, 255, 255, 0.7);">' . htmlspecialchars($footer_text) . '</p>
                            </div>
                        </td>
                    </tr>
                    ' : '') . '
                    
                    <!-- Premium divider -->
                    <tr>
                        <td style="padding: 0 52px;">
                            <div style="height: 1px; background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.3) 20%, rgba(6, 182, 212, 0.4) 50%, rgba(14, 165, 233, 0.3) 80%, transparent); box-shadow: 0 0 8px rgba(14, 165, 233, 0.3);"></div>
                        </td>
                    </tr>
                    
                    <!-- Footer with gradient text -->
                    <tr>
                        <td style="padding: 44px 52px; text-align: center;">
                            <p style="margin: 0 0 10px; font-size: 14px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.5), rgba(56, 189, 248, 0.6)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 500;">&copy; ' . date('Y') . ' ' . APP_NAME . '</p>
                            <p style="margin: 0; font-size: 12px; color: rgba(255, 255, 255, 0.3);">This is an automated message. Please do not reply.</p>
                        </td>
                    </tr>
                    
                    <!-- Bottom glow accent -->
                    <tr>
                        <td style="height: 2px; background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.4) 50%, transparent); box-shadow: 0 0 16px rgba(14, 165, 233, 0.4);"></td>
                    </tr>
                </table>
                
                <!-- Spacer -->
                <div style="height: 24px;"></div>
                
                <!-- Powered by text with glow -->
                <p style="margin: 0; text-align: center; font-size: 12px; color: rgba(255, 255, 255, 0.25); letter-spacing: 0.8px;">
                    Powered by <span style="background: linear-gradient(135deg, #0ea5e9, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 700; filter: drop-shadow(0 0 8px rgba(14, 165, 233, 0.4));">' . APP_NAME . '</span>
                </p>
                
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
    
    /**
     * Send email using SMTP
     */
    private static function sendViaSMTP($to, $subject, $html_body, $text_body = null) {
        $host = defined('MAIL_HOST') ? MAIL_HOST : 'smtp.gmail.com';
        $port = defined('MAIL_PORT') ? MAIL_PORT : 587;
        $username = defined('MAIL_USERNAME') ? MAIL_USERNAME : '';
        $password = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';
        $encryption = defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'tls';
        
        try {
            // Create socket connection
            $smtp = fsockopen(
                $encryption === 'ssl' ? 'ssl://' . $host : $host,
                $port,
                $errno,
                $errstr,
                30
            );
            
            if (!$smtp) {
                throw new Exception("SMTP connection failed: $errstr ($errno)");
            }
            
            // Read server greeting
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) !== '220') {
                throw new Exception("SMTP greeting failed: $response");
            }
            
            // Send EHLO
            fputs($smtp, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
            
            // Read all EHLO responses
            while ($line = fgets($smtp, 515)) {
                if (substr($line, 0, 3) === '250' && $line[3] === ' ') {
                    break; // Last line of EHLO response
                }
            }
            
            // Start TLS if needed
            if ($encryption === 'tls') {
                fputs($smtp, "STARTTLS\r\n");
                $response = fgets($smtp, 515);
                if (substr($response, 0, 3) !== '220') {
                    throw new Exception("STARTTLS failed: $response");
                }
                
                $crypto = stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                if (!$crypto) {
                    throw new Exception("TLS encryption failed");
                }
                
                // Send EHLO again after TLS
                fputs($smtp, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
                while ($line = fgets($smtp, 515)) {
                    if (substr($line, 0, 3) === '250' && $line[3] === ' ') {
                        break;
                    }
                }
            }
            
            // Authenticate
            fputs($smtp, "AUTH LOGIN\r\n");
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) !== '334') {
                throw new Exception("AUTH LOGIN failed: $response");
            }
            
            fputs($smtp, base64_encode($username) . "\r\n");
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) !== '334') {
                throw new Exception("Username rejected: $response");
            }
            
            fputs($smtp, base64_encode($password) . "\r\n");
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) !== '235') {
                throw new Exception("SMTP authentication failed: $response");
            }
            
            // Send MAIL FROM
            fputs($smtp, "MAIL FROM: <" . self::$from_email . ">\r\n");
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) !== '250') {
                throw new Exception("MAIL FROM failed: $response");
            }
            
            // Send RCPT TO
            fputs($smtp, "RCPT TO: <$to>\r\n");
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) !== '250') {
                throw new Exception("RCPT TO failed: $response");
            }
            
            // Send DATA
            fputs($smtp, "DATA\r\n");
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) !== '354') {
                throw new Exception("DATA command failed: $response");
            }
            
            // Prepare email headers
            $boundary = md5(time());
            $headers = "From: " . self::$from_name . " <" . self::$from_email . ">\r\n";
            $headers .= "Reply-To: " . self::$from_email . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
            $headers .= "Subject: $subject\r\n";
            $headers .= "To: $to\r\n";
            
            // Prepare message body
            $message = $headers . "\r\n";
            $message .= "--$boundary\r\n";
            
            // Plain text version
            if ($text_body) {
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $message .= $text_body . "\r\n\r\n";
                $message .= "--$boundary\r\n";
            }
            
            // HTML version
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $html_body . "\r\n\r\n";
            $message .= "--$boundary--\r\n";
            
            // Send message
            fputs($smtp, $message);
            fputs($smtp, "\r\n.\r\n");
            
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) !== '250') {
                throw new Exception("Message sending failed: $response");
            }
            
            // Send QUIT
            fputs($smtp, "QUIT\r\n");
            fclose($smtp);
            
            error_log("Email sent successfully via SMTP to: " . $to);
            return true;
            
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            // Fallback to development mode logging
            error_log("=== EMAIL (SMTP Failed - Logged Instead) ===");
            error_log("To: " . $to);
            error_log("Subject: " . $subject);
            
            // Extract verification link
            if (preg_match('/href="([^"]*verify-email[^"]*)"/', $html_body, $matches)) {
                error_log("Verification Link: " . $matches[1]);
            }
            
            // Extract verification code
            if (preg_match('/verification code.*?<strong[^>]*>(\d{6})<\/strong>/is', $html_body, $matches)) {
                error_log("Verification Code: " . $matches[1]);
            }
            
            error_log("=== END EMAIL ===");
            return false;
        }
    }
    
    /**
     * Send email using PHP mail() function
     */
    private static function sendViaMail($to, $subject, $html_body, $text_body = null) {
        // Development mode for Windows - log emails instead of sending
        if (PHP_OS_FAMILY === 'Windows') {
            error_log("=== EMAIL (Development Mode - Not Sent) ===");
            error_log("To: " . $to);
            error_log("Subject: " . $subject);
            error_log("HTML Body Preview: " . substr(strip_tags($html_body), 0, 200) . "...");
            
            // Extract verification link if present
            if (preg_match('/href="([^"]*verify-email[^"]*)"/', $html_body, $matches)) {
                error_log("Verification Link: " . $matches[1]);
            }
            
            // Extract verification code if present
            if (preg_match('/verification code.*?<strong[^>]*>(\d{6})<\/strong>/is', $html_body, $matches)) {
                error_log("Verification Code: " . $matches[1]);
            }
            
            error_log("=== END EMAIL ===");
            return true; // Simulate success in development
        }
        
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
}
?>