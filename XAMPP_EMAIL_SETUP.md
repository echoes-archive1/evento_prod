# 📧 XAMPP Email Configuration Guide

## ⚠️ IMPORTANT: Your App Password is Already Configured
Your Gmail App Password (`pqjy evfz vfjz gker`) is already set in the config.

## 🔧 XAMPP Configuration (Required)

### Step 1: Configure PHP (php.ini)
**Location:** `C:\xampp\php\php.ini`

Find these lines and update them:

```ini
[mail function]
SMTP = smtp.gmail.com
smtp_port = 587
sendmail_from = hitanshpparikh@gmail.com
sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"
```

**To find quickly:** Press `Ctrl+F` and search for `[mail function]`

### Step 2: Configure Sendmail (sendmail.ini)
**Location:** `C:\xampp\sendmail\sendmail.ini`

Update these settings:

```ini
[sendmail]
smtp_server=smtp.gmail.com
smtp_port=587
error_logfile=error.log
debug_logfile=debug.log
auth_username=hitanshpparikh@gmail.com
auth_password=pqjy evfz vfjz gker
force_sender=hitanshpparikh@gmail.com
hostname=localhost
```

**Important:** 
- The `auth_password` should be your Gmail App Password: `pqjyevfzvfjzgker` (no spaces)
- Your App Password in config.php has spaces, but in sendmail.ini use NO SPACES

### Step 3: Restart Apache
1. Open XAMPP Control Panel
2. Click "Stop" on Apache
3. Wait 2 seconds
4. Click "Start" on Apache

## ✅ Your Current Configuration

From `config/config.php`:
```php
MAIL_FROM_ADDRESS: hitanshpparikh@gmail.com
MAIL_FROM_NAME: Evento - Event Management
MAIL_USE_SMTP: false (Using PHP mail() with sendmail)
ENABLE_EMAIL_VERIFICATION: true
```

## 🧪 Test Email Sending

### Method 1: Via Web Interface
1. Visit: https://hitanshparikh.tech/evento/database/test-email.php
2. Login as admin first
3. Enter your test email
4. Click "Send Test Email"
5. Check your inbox (and spam folder)

### Method 2: Quick Test Script
Create a file `test-mail.php` in the root:

```php
<?php
$to = "hitanshpparikh@gmail.com";
$subject = "XAMPP Test Email";
$message = "This is a test email from XAMPP";
$headers = "From: hitanshpparikh@gmail.com\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

if(mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully!";
} else {
    echo "Email sending failed!";
}
?>
```

## 🚨 Common Issues & Solutions

### Issue 1: Email not sending
**Check these files:**
1. `C:\xampp\sendmail\error.log` - Check for errors
2. `C:\xampp\sendmail\debug.log` - Check SMTP communication
3. `C:\xampp\apache\logs\error.log` - Check Apache errors

**Common fixes:**
- Restart Apache
- Check Gmail App Password (no spaces in sendmail.ini)
- Verify Gmail account has 2FA enabled
- Check if antivirus is blocking sendmail.exe

### Issue 2: Authentication failed
- Double-check App Password is correct
- Make sure 2-Step Verification is enabled on Gmail
- Try generating a new App Password

### Issue 3: Connection timeout
- Check if port 587 is open
- Temporarily disable firewall/antivirus
- Try port 465 with SSL instead:
  ```ini
  smtp_port=465
  # Add this line:
  smtp_ssl=ssl
  ```

### Issue 4: "Mail() has been disabled"
- Check php.ini: Make sure `disable_functions` does NOT include `mail`
- Restart Apache after any php.ini changes

## 📝 Important Notes for sendmail.ini

### Current Format (with spaces - for reference):
```
pqjy evfz vfjz gker
```

### Required Format (NO spaces):
```
pqjyevfzvfjzgker
```

## 🔍 Verify Configuration

### Check php.ini settings:
```bash
php -i | findstr "SMTP"
```

### Check sendmail.exe exists:
```bash
dir C:\xampp\sendmail\sendmail.exe
```

### Test SMTP connection:
```bash
telnet smtp.gmail.com 587
```

## 📊 Email Sending Flow

```
Your PHP Application
        ↓
   PHP mail() function
        ↓
C:\xampp\sendmail\sendmail.exe
        ↓
   smtp.gmail.com:587
        ↓
    Gmail SMTP Server
        ↓
  Email Delivered ✅
```

## ✨ Production vs Development

### XAMPP (Development) - Current Setup:
```php
define('MAIL_USE_SMTP', false);  // Use PHP mail() with sendmail
```

### Production (Live Server):
```php
define('MAIL_USE_SMTP', true);   // Use direct SMTP connection
```

## 🎯 Quick Checklist

Before testing emails:
- [ ] php.ini configured (Step 1)
- [ ] sendmail.ini configured (Step 2)
- [ ] App Password has NO spaces in sendmail.ini
- [ ] Apache restarted
- [ ] Gmail 2FA enabled
- [ ] App Password is valid
- [ ] sendmail.exe exists
- [ ] No firewall blocking port 587

## 🆘 Still Not Working?

### Enable Debug Mode in sendmail.ini:
```ini
debug_logfile=debug.log
```

Then check: `C:\xampp\sendmail\debug.log` for detailed SMTP conversation

### Alternative: Use Direct SMTP (Bypass sendmail)
If sendmail is problematic, switch to direct SMTP:

In `config/config.php`:
```php
define('MAIL_USE_SMTP', true);  // Enable direct SMTP
```

This uses the built-in SMTP client in Email.php (already configured)

## ✅ Success Indicators

When everything works:
1. No errors in sendmail error.log
2. Email appears in your inbox
3. test-email.php shows "Email sent successfully"
4. Registration sends verification emails

---

**Your Setup:**
- Email: hitanshpparikh@gmail.com
- App Password: pqjy evfz vfjz gker (configured)
- Mode: PHP mail() with XAMPP sendmail
- Status: Ready to configure XAMPP

**Next Step:** Configure php.ini and sendmail.ini, then restart Apache!
