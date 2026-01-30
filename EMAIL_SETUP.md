# Email Service Setup Guide

## Overview
The Evento application now includes a complete email service for:
- ✉️ Email verification on user registration
- 🎉 Event registration confirmation emails
- 🔐 Password reset emails (framework ready)

## Quick Start

### Step 1: Run Database Migration
Visit: `https://hitanshparikh.tech/evento/database/migrate_email_verification.php`

This will add the necessary columns to your database:
- `verification_token`
- `token_expiry`
- `email_verified_at`
- `reset_token`
- `reset_token_expiry`

### Step 2: Configure XAMPP Email Settings

#### For Gmail (Recommended for Testing)

**A. Configure PHP (php.ini)**
1. Open `C:\xampp\php\php.ini`
2. Find and update these lines:
```ini
[mail function]
SMTP = smtp.gmail.com
smtp_port = 587
sendmail_from = hitanshpparikh@gmail.com
sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"
```

**B. Configure Sendmail (sendmail.ini)**
1. Open `C:\xampp\sendmail\sendmail.ini`
2. Update these settings:
```ini
[sendmail]
smtp_server=smtp.gmail.com
smtp_port=587
error_logfile=error.log
debug_logfile=debug.log
auth_username=hitanshpparikh@gmail.com
auth_password=your-16-digit-app-password
force_sender=hitanshpparikh@gmail.com
hostname=localhost
```

**C. Get Gmail App Password**
1. Go to [Google Account Security](https://myaccount.google.com/security)
2. Enable 2-Step Verification if not already enabled
3. Go to [App Passwords](https://myaccount.google.com/apppasswords)
4. Select "Mail" and "Windows Computer"
5. Copy the 16-digit password (no spaces)
6. Use this in `auth_password` in sendmail.ini

### Step 3: Restart Apache
- Stop and start Apache in XAMPP Control Panel

### Step 4: Test Email
Visit: `https://hitanshparikh.tech/evento/database/test-email.php`
- Enter your email address
- Click "Send Test Email"
- Check your inbox (and spam folder)

## Configuration Options

### Email Settings (config/config.php)
```php
// Email Configuration
define('MAIL_FROM_ADDRESS', 'noreply@evento.com');
define('MAIL_FROM_NAME', 'Evento - Event Management');
define('MAIL_USE_SMTP', false);
define('ENABLE_EMAIL_VERIFICATION', true);
```

**ENABLE_EMAIL_VERIFICATION**
- `true` - Users must verify email before full access (recommended for production)
- `false` - Users are auto-verified on registration (for development/testing)

## Email Types

### 1. Verification Email
**Trigger:** User registers a new account
**Template:** Premium glassmorphic design with verification button
**Contains:**
- Welcome message
- Verification link (24-hour expiry)
- App branding

### 2. Event Registration Email
**Trigger:** User registers for an event
**Template:** Event details card with QR code
**Contains:**
- Event name, date, time, venue
- Department information
- Unique QR code for check-in
- Link to view event details

### 3. Password Reset Email (Ready for Implementation)
**Template:** Secure reset link
**Framework:** Already included in Email helper class

## File Structure

```
app/helpers/
  └── Email.php              # Email service class

database/
  ├── add_email_verification.sql      # Migration SQL
  ├── migrate_email_verification.php  # Migration runner
  └── test-email.php                  # Email test page

verify-email.php             # Email verification handler
register.php                 # Updated with email sending
api/register-event.php       # Updated with email sending
```

## Troubleshooting

### Emails Not Sending?
1. Check Apache error logs: `C:\xampp\apache\logs\error.log`
2. Check sendmail logs: `C:\xampp\sendmail\debug.log`
3. Verify Gmail credentials are correct
4. Ensure 2-Step Verification is enabled
5. Check spam folder

### Gmail Specific Issues
- **"Username and Password not accepted"**: Use App Password, not regular password
- **"Less secure app access"**: This option is deprecated, use App Passwords instead
- **SSL/TLS errors**: Port 587 uses STARTTLS (not SSL)

### Testing Without Email
Set in config.php:
```php
define('ENABLE_EMAIL_VERIFICATION', false);
```
Users will be auto-verified on registration.

## Alternative SMTP Services

### Using Mailtrap (Development)
Perfect for testing without sending real emails:
```ini
smtp_server=smtp.mailtrap.io
smtp_port=2525
auth_username=your-mailtrap-username
auth_password=your-mailtrap-password
```
Sign up at [mailtrap.io](https://mailtrap.io)

### Using SendGrid (Production)
```ini
smtp_server=smtp.sendgrid.net
smtp_port=587
auth_username=apikey
auth_password=your-sendgrid-api-key
```

### Using Mailgun (Production)
```ini
smtp_server=smtp.mailgun.org
smtp_port=587
auth_username=postmaster@your-domain.mailgun.org
auth_password=your-mailgun-password
```

## Security Notes

1. **Never commit** sendmail.ini or php.ini with real credentials
2. Use **App Passwords**, never regular passwords
3. For production, consider using **dedicated SMTP service** (SendGrid, Mailgun, SES)
4. Email content is **HTML-escaped** to prevent XSS
5. Verification tokens are **cryptographically secure** (64 characters)
6. Tokens expire after **24 hours**

## Email Template Customization

The Email helper class (`app/helpers/Email.php`) uses template methods:
- `getEmailTemplate()` - HTML version
- `getTextTemplate()` - Plain text fallback

Modify these methods to customize:
- Colors and branding
- Email layout
- Button styles
- Footer content

## Production Recommendations

1. **Use professional SMTP service** (SendGrid, Mailgun, Amazon SES)
2. **Enable SSL/TLS** for security
3. **Set up SPF/DKIM records** for deliverability
4. **Monitor bounce rates** and failed deliveries
5. **Test thoroughly** before going live
6. **Keep email logs** for debugging
7. **Implement rate limiting** to prevent abuse

## Testing Checklist

- [ ] Database migration completed
- [ ] SMTP configured in php.ini
- [ ] Sendmail configured in sendmail.ini
- [ ] Apache restarted
- [ ] Test email received
- [ ] New user registration sends verification email
- [ ] Email verification link works
- [ ] Event registration sends confirmation email
- [ ] Emails not landing in spam
- [ ] Both HTML and plain text versions work

## Support

If you encounter issues:
1. Check the error logs
2. Run the test email page
3. Verify SMTP credentials
4. Try with Mailtrap for debugging

---

**Ready to use!** 🚀

After completing the setup, users will receive beautiful, branded emails for all important actions in your Evento application.
