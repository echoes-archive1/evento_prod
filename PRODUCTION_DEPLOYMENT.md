# 🚀 Production Deployment Guide - Evento Email Verification

## ✅ Pre-Deployment Checklist

### 1. Database Migration
Run this SQL to add email verification columns to your production database:

```sql
-- Add verification columns
ALTER TABLE `users` 
ADD COLUMN `verification_token` VARCHAR(64) DEFAULT NULL AFTER `email_verified`,
ADD COLUMN `token_expiry` DATETIME DEFAULT NULL AFTER `verification_token`,
ADD COLUMN `email_verified_at` DATETIME DEFAULT NULL AFTER `token_expiry`,
ADD COLUMN `reset_token` VARCHAR(64) DEFAULT NULL AFTER `email_verified_at`,
ADD COLUMN `reset_token_expiry` DATETIME DEFAULT NULL AFTER `reset_token`,
ADD INDEX `idx_verification_token` (`verification_token`),
ADD INDEX `idx_reset_token` (`reset_token`);
```

### 2. Configuration Updates

Edit `config/config.php` and update these settings:

```php
// ❌ TURN OFF ERROR DISPLAY (Already configured)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// ✅ ENABLE HTTPS COOKIES (Already configured)
ini_set('session.cookie_secure', 1);

// 🌐 UPDATE BASE URL
define('BASE_URL', 'https://hitanshparikh.tech/evento');  // ⚠️ CHANGE THIS!

// 📧 EMAIL CONFIGURATION
define('MAIL_FROM_ADDRESS', 'noreply@yourdomain.com');  // ⚠️ CHANGE THIS!
define('MAIL_FROM_NAME', 'Evento - Event Management');
define('MAIL_USE_SMTP', true);
define('MAIL_HOST', 'smtp.gmail.com');  // Or your SMTP server
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'hitanshpparikh@gmail.com');  // ⚠️ CHANGE THIS!
define('MAIL_PASSWORD', 'your-16-digit-app-password');  // ⚠️ CHANGE THIS!
define('MAIL_ENCRYPTION', 'tls');
define('ENABLE_EMAIL_VERIFICATION', true);  // ✅ Already enabled
```

### 3. Gmail SMTP Setup (Recommended)

#### Step A: Enable 2-Factor Authentication
1. Go to [Google Account Security](https://myaccount.google.com/security)
2. Enable "2-Step Verification"

#### Step B: Generate App Password
1. Go to [App Passwords](https://myaccount.google.com/apppasswords)
2. Select "Mail" and "Other (Custom name)"
3. Name it "Evento Production"
4. Click "Generate"
5. Copy the 16-digit password (format: `xxxx xxxx xxxx xxxx`)
6. Use this password in `MAIL_PASSWORD` (without spaces)

### 4. Create Required Directories

```bash
# Create logs directory
mkdir logs
chmod 755 logs

# Ensure proper permissions
chmod 644 config/config.php
```

### 5. SSL Certificate (HTTPS)

**Your hosting MUST have HTTPS enabled for production!**

- Most hosting providers offer free Let's Encrypt SSL
- Ensure `session.cookie_secure` is set to `1`
- Update `BASE_URL` to use `https://`

## 📧 Email Verification Flow

### For New Users:
1. User registers → Email sent with verification link
2. Link valid for 24 hours
3. User clicks link → Email verified → Can login
4. If link expires → Use "Resend Verification" page

### For Existing Users:
Users registered before this update will have `email_verified = 0`.

**Option 1: Auto-verify existing users**
```sql
UPDATE users SET email_verified = 1, email_verified_at = NOW() 
WHERE created_at < '2025-12-31' AND email_verified = 0;
```

**Option 2: Force verification**
Send them to the resend verification page.

## 🧪 Testing Email in Production

### Test Email Sending
1. Visit: `https://hitanshparikh.tech/evento/database/test-email.php`
2. Enter your email
3. Check inbox (and spam folder)
4. Verify you receive the test email

### Test Registration Flow
1. Register a new test account
2. Check email for verification link
3. Click verification link
4. Try to login before verification (should be blocked)
5. After verification, login should work

### Test Resend Verification
1. Try logging in with unverified account
2. Click "Resend verification email" link
3. Enter email and submit
4. Check for new verification email

## 🔒 Security Checklist

- [x] Error display disabled (`display_errors = 0`)
- [x] HTTPS enabled (SSL certificate installed)
- [x] Secure cookies enabled (`cookie_secure = 1`)
- [x] Email verification enabled
- [x] CSRF protection active
- [x] Password hashing with bcrypt
- [x] SQL injection protection (prepared statements)
- [x] XSS protection (htmlspecialchars)
- [x] Session timeout configured

## 📁 Files Modified for Email Verification

### New Files:
- `resend-verification.php` - Resend verification email page
- `PRODUCTION_DEPLOYMENT.md` - This guide

### Modified Files:
- `config/config.php` - Email & security settings
- `database/schema.sql` - Added verification columns
- `login.php` - Check email verification before login
- `register.php` - Send verification email on signup
- `verify-email.php` - Handle email verification
- `app/helpers/Email.php` - Added SMTP support

## 🚨 Common Issues & Solutions

### Issue: Emails not sending
**Solution:**
1. Check SMTP credentials in `config/config.php`
2. Verify Gmail App Password is correct (no spaces)
3. Check `logs/error.log` for errors
4. Test with `database/test-email.php`

### Issue: "Email already verified" after clicking link
**Reason:** User clicked the link multiple times (this is normal)
**Solution:** Just login - email is already verified

### Issue: Verification link expired
**Solution:** Go to `resend-verification.php` and request new link

### Issue: Emails going to spam
**Solutions:**
1. Use a proper domain email (not Gmail)
2. Set up SPF, DKIM, DMARC records for your domain
3. Use a reputable SMTP service (SendGrid, Mailgun, AWS SES)

### Issue: Users registered before this update can't login
**Solution:** Run this SQL to auto-verify them:
```sql
UPDATE users SET email_verified = 1 WHERE email_verified = 0;
```

## 🎯 Post-Deployment Verification

### Day 1: Monitor
- [ ] Check error logs: `tail -f logs/error.log`
- [ ] Test user registration
- [ ] Test email delivery
- [ ] Test verification links

### Week 1: Review
- [ ] Check email delivery rate
- [ ] Review spam complaints
- [ ] Monitor verification completion rate
- [ ] Check for abandoned registrations

## 🔄 Backup Plan

If email verification causes issues, you can temporarily disable it:

```php
// In config/config.php
define('ENABLE_EMAIL_VERIFICATION', false);
```

This will:
- Auto-verify new users on registration
- Allow existing unverified users to login
- Keep the verification system for future use

## 📊 Success Metrics

Track these to ensure smooth deployment:
- ✉️ Email delivery rate (should be >95%)
- ✅ Verification completion rate (should be >80%)
- 🔐 Login success rate
- ⏱️ Time to verify (average should be <5 minutes)

## 🆘 Emergency Rollback

If critical issues occur:

1. **Disable email verification:**
```php
define('ENABLE_EMAIL_VERIFICATION', false);
```

2. **Auto-verify all users:**
```sql
UPDATE users SET email_verified = 1;
```

3. **Restore from backup** (if needed)

## 📞 Support

For issues, check:
1. `logs/error.log` - Application errors
2. Database audit_logs table - Security events
3. Email service logs (Gmail/SMTP provider)

## ✨ Production Deployment Complete!

Your email verification system is now production-ready with:
- ✅ Secure SMTP email delivery
- ✅ 24-hour token expiration
- ✅ Resend verification functionality
- ✅ Login protection for unverified users
- ✅ Beautiful responsive email templates
- ✅ Comprehensive error handling
- ✅ Audit logging

---

**Last Updated:** December 31, 2025
**Version:** 1.0.0
**System:** Evento - College Event Management
