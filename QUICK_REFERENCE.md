# 📧 Email Verification - Quick Reference

## 🚀 Quick Start for Production

### 1. Run Database Migration
Visit in browser: `https://hitanshparikh.tech/evento/database/migrate_email_verification.php`

### 2. Update Config (Required!)
Edit `config/config.php`:
```php
// Change these three settings:
define('BASE_URL', 'https://hitanshparikh.tech/evento/');  // Your domain
define('MAIL_USERNAME', 'hitanshpparikh@gmail.com');  // Your Gmail
define('MAIL_PASSWORD', 'pqjy evfz vfjz gker');  // 16-digit App Password
```

### 3. Get Gmail App Password
1. Visit: https://myaccount.google.com/apppasswords
2. Select "Mail" → "Other" → Name it "Evento"
3. Copy the 16-digit code
4. Paste in `pqjy evfz vfjz gker` (without spaces)

### 4. Test Email
Visit: `https://hitanshparikh.tech/evento/database/test-email.php`

## ✅ What's Working Now

- ✅ Registration sends verification email (24-hour expiry)
- ✅ Login blocked for unverified users
- ✅ Resend verification email page
- ✅ Beautiful responsive email templates
- ✅ SMTP support for production
- ✅ Secure token generation
- ✅ Audit logging

## 📋 User Flow

### New User Registration:
1. User fills registration form
2. System creates account (unverified)
3. Verification email sent
4. User clicks link in email
5. Account verified → Can login

### Login with Unverified Email:
1. User tries to login
2. System checks email_verified status
3. If unverified → Error message with resend link
4. User clicks "Resend verification email"
5. New email sent

## 🔧 Configuration Settings

All in `config/config.php`:

```php
// Email Verification Toggle
define('ENABLE_EMAIL_VERIFICATION', true);  // true = ON, false = OFF

// SMTP Settings
define('MAIL_USE_SMTP', true);  // true for production
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);  // 587 for TLS, 465 for SSL
define('MAIL_ENCRYPTION', 'tls');  // 'tls' or 'ssl'
```

## 📁 New Files Created

- `resend-verification.php` - Resend verification page
- `PRODUCTION_DEPLOYMENT.md` - Full deployment guide
- `QUICK_REFERENCE.md` - This file
- `setup-production.bat` - Windows setup script
- `setup-production.sh` - Linux setup script

## 📝 Modified Files

- `config/config.php` - Added email settings
- `database/schema.sql` - Added verification columns
- `login.php` - Check verification before login
- `register.php` - Send verification email
- `verify-email.php` - Handle verification
- `app/helpers/Email.php` - Added SMTP support

## 🗄️ Database Changes

New columns in `users` table:
- `verification_token` (VARCHAR 64)
- `token_expiry` (DATETIME)
- `email_verified_at` (DATETIME)
- `reset_token` (VARCHAR 64) - For future use
- `reset_token_expiry` (DATETIME) - For future use

## 🔍 Testing Checklist

- [ ] Database migration completed
- [ ] Config updated with real credentials
- [ ] Test email sent successfully
- [ ] Register new user → Email received
- [ ] Click verification link → Success
- [ ] Try login before verify → Blocked
- [ ] Resend verification → Works
- [ ] After verify → Login works

## 🚨 Common Issues

### Emails Not Sending?
1. Check `logs/error.log`
2. Verify Gmail App Password (no spaces!)
3. Check SMTP settings
4. Try test email page

### Verification Link Expired?
- Users can request new link at `resend-verification.php`
- Links expire after 24 hours

### Existing Users Can't Login?
Auto-verify them:
```sql
UPDATE users SET email_verified = 1, email_verified_at = NOW();
```

## 🔄 Disable Email Verification (Emergency)

If you need to turn it off:

```php
// In config/config.php
define('ENABLE_EMAIL_VERIFICATION', false);
```

This will:
- Auto-verify new registrations
- Allow unverified users to login
- Keep system ready to re-enable later

## 📞 Support URLs

- Migration: `/database/migrate_email_verification.php`
- Test Email: `/database/test-email.php`
- Resend Verification: `/resend-verification.php`
- Verify Email: `/verify-email.php?token=...`

## 🎯 Production Requirements

**MUST HAVE:**
- ✅ HTTPS enabled (SSL certificate)
- ✅ Valid SMTP credentials
- ✅ Database migrated
- ✅ Config updated

**SHOULD HAVE:**
- Error logging enabled
- Backup system
- Monitoring for email delivery
- SPF/DKIM records (for better deliverability)

## 📊 Monitor These

- Email delivery rate
- Verification completion rate
- Failed login attempts (unverified)
- Token expiration issues

## 🎉 You're Ready!

Once all checkboxes are ✅, your email verification system is production-ready!

For detailed guide, see: `PRODUCTION_DEPLOYMENT.md`
