# 🚀 PRODUCTION DEPLOYMENT CHECKLIST

## Before You Deploy

### 1. Database Setup ✅
- [ ] Run migration: `/database/migrate_email_verification.php`
- [ ] Verify columns added: `verification_token`, `token_expiry`, `email_verified_at`
- [ ] Backup existing database
- [ ] Test database connection

### 2. Configuration Updates ⚙️
- [ ] Open `config/config.php`
- [ ] Update `BASE_URL` to production domain (https://hitanshparikh.tech/evento)
- [ ] Update `MAIL_FROM_ADDRESS` (noreply@yourdomain.com)
- [ ] Update `MAIL_USERNAME` (your Gmail)
- [ ] Update `MAIL_PASSWORD` (16-digit App Password)
- [ ] Verify `ENABLE_EMAIL_VERIFICATION` is `true`
- [ ] Verify `MAIL_USE_SMTP` is `true`
- [ ] Verify `session.cookie_secure` is `1`
- [ ] Verify `display_errors` is `0`

### 3. Gmail App Password Setup 📧
- [ ] Visit https://myaccount.google.com/security
- [ ] Enable 2-Step Verification
- [ ] Visit https://myaccount.google.com/apppasswords
- [ ] Select "Mail" and "Other (Custom)"
- [ ] Name it "Evento Production"
- [ ] Copy 16-digit password
- [ ] Paste in `config/config.php` MAIL_PASSWORD (no spaces)

### 4. SSL/HTTPS Setup 🔒
- [ ] SSL certificate installed and active
- [ ] Site accessible via https://
- [ ] HTTP redirects to HTTPS
- [ ] No mixed content warnings
- [ ] Cookies set with secure flag

### 5. File Permissions 🔐
- [ ] logs/ directory exists and writable (755)
- [ ] config/config.php set to 644
- [ ] public/uploads/ writable (755)
- [ ] .htaccess files in place

### 6. Testing Phase 🧪

#### Test 1: Email Sending
- [ ] Visit `/database/test-email.php`
- [ ] Send test email to your address
- [ ] Email received in inbox (check spam too)
- [ ] Email displays correctly
- [ ] Links work properly

#### Test 2: New User Registration
- [ ] Register new test account
- [ ] Verification email sent immediately
- [ ] Email contains correct verification link
- [ ] Click link → Shows success message
- [ ] Account now verified in database
- [ ] Can login successfully

#### Test 3: Login Protection
- [ ] Register another test account
- [ ] Do NOT verify email
- [ ] Try to login → Blocked with error
- [ ] Error shows "resend" link
- [ ] Click resend link → Goes to resend page

#### Test 4: Resend Verification
- [ ] Enter unverified email address
- [ ] Submit form
- [ ] New verification email received
- [ ] Click link → Account verified
- [ ] Can now login

#### Test 5: Edge Cases
- [ ] Click verification link twice → Shows "already verified"
- [ ] Try expired token → Shows expired message
- [ ] Invalid token → Shows error message
- [ ] Empty email in resend → Shows validation error

### 7. Security Verification 🛡️
- [ ] Error display disabled (display_errors = 0)
- [ ] Error logging enabled (logs/error.log)
- [ ] CSRF protection active
- [ ] SQL injection protection (prepared statements)
- [ ] XSS protection (htmlspecialchars)
- [ ] Password hashing with bcrypt
- [ ] Session timeout configured
- [ ] Secure cookie settings

### 8. Monitoring Setup 📊
- [ ] Error log accessible: `logs/error.log`
- [ ] Log file protected by .htaccess
- [ ] Can view audit_logs table
- [ ] Email delivery tracking in place

### 9. Existing Users (Optional) 👥
Choose ONE option:

**Option A: Auto-verify existing users** (Recommended)
```sql
UPDATE users SET email_verified = 1, email_verified_at = NOW() 
WHERE email_verified = 0 AND created_at < NOW();
```

**Option B: Force verification**
- Let them use resend verification page
- Manually verify VIP users if needed

### 10. Documentation 📚
- [ ] Read `PRODUCTION_DEPLOYMENT.md`
- [ ] Read `QUICK_REFERENCE.md`
- [ ] Read `EMAIL_VERIFICATION_READY.md`
- [ ] Team informed of new verification flow

## Post-Deployment Monitoring

### Day 1 - Immediate Checks
- [ ] Monitor error logs every hour
- [ ] Check email delivery rate
- [ ] Test new user registration
- [ ] Verify no 500 errors
- [ ] Check user feedback

### Week 1 - Daily Checks
- [ ] Review error logs daily
- [ ] Check verification completion rate
- [ ] Monitor abandoned registrations
- [ ] Check spam complaints
- [ ] Review failed SMTP attempts

### Month 1 - Weekly Review
- [ ] Email delivery metrics
- [ ] Average time to verify
- [ ] Unverified user count
- [ ] System performance
- [ ] User satisfaction

## Success Metrics 📈

Target metrics for healthy system:
- ✅ Email delivery rate: >95%
- ✅ Verification completion: >80%
- ✅ Average time to verify: <5 minutes
- ✅ Failed SMTP attempts: <1%
- ✅ User complaints: <2%

## Rollback Plan 🔄

If critical issues occur:

### Quick Disable (2 minutes)
```php
// In config/config.php
define('ENABLE_EMAIL_VERIFICATION', false);
```

### Auto-Verify All Users (1 minute)
```sql
UPDATE users SET email_verified = 1, email_verified_at = NOW();
```

### Full Rollback (10 minutes)
1. Restore database backup
2. Restore old config.php
3. Clear session data
4. Restart web server

## Support Resources 🆘

- Error logs: `logs/error.log`
- Database: `audit_logs` table
- Gmail logs: https://mail.google.com
- Test page: `/database/test-email.php`
- Resend page: `/resend-verification.php`

## Final Verification ✅

Before declaring success, verify:
- [ ] All checklist items completed
- [ ] No errors in production
- [ ] Users can register and verify
- [ ] Emails delivering properly
- [ ] Team trained on new flow
- [ ] Documentation accessible
- [ ] Monitoring in place
- [ ] Backup tested

## 🎉 DEPLOYMENT COMPLETE!

Once all items are checked, your email verification system is live!

---

**Deployment Date:** _____________
**Deployed By:** _____________
**Verified By:** _____________
**Status:** ⬜ Ready | ⬜ In Progress | ⬜ Complete

---

## Notes / Issues

(Document any issues encountered during deployment)

_________________________________________________________________

_________________________________________________________________

_________________________________________________________________

---

**Next Review Date:** _____________
