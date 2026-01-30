# 📧 New Two-Step Registration Flow

## Overview
The registration process has been completely redesigned to use a **two-step email verification flow**:

### Step 1: Email & Password
User enters only **email** and **password** on the registration page.

### Step 2: Complete Profile (After Verification)
After clicking the verification link, user completes their profile with:
- Full Name
- Roll Number / Student ID
- Department
- Year
- Phone Number

## ⏱️ Key Features

### 10-Minute Expiry
- Verification links expire after **10 minutes**
- Expired accounts are automatically deleted
- Users can re-register with the same email after expiry

### Auto-Cleanup
- Unverified users are deleted when they try to verify with expired token
- Manual cleanup script available: `/database/cleanup-expired-users.php`

## 🔄 Complete Flow

```
1. User visits register.php
   └─> Enters email & password
   └─> Clicks "Send Verification Email"

2. System creates minimal user account
   └─> Email sent with 10-minute token
   └─> User receives email

3. User clicks verification link
   └─> If expired: Account deleted, must register again
   └─> If valid: Redirected to complete-profile.php

4. User completes profile form
   └─> Name, Roll Number, Department, Year, Phone
   └─> Clicks "Complete Registration"

5. Registration complete!
   └─> Email verified
   └─> Profile saved
   └─> Student role assigned
   └─> Redirected to login
```

## 📁 Files Modified

### 1. `register.php`
- **Changed:** Only asks for email & password
- **Token:** Expires in 10 minutes (not 24 hours)
- **Duplicate:** Deletes old unverified accounts with same email
- **Email:** Sends verification email immediately

### 2. `verify-email.php`
- **Expired:** Deletes user account if token expired
- **Success:** Redirects to complete-profile.php
- **Session:** Stores user ID and email for profile completion

### 3. `complete-profile.php` (NEW)
- **Form:** Full name, roll number, department, year, phone
- **Validation:** Checks roll number uniqueness
- **Completion:** Marks email as verified and completes registration
- **Redirect:** Sends to login page after 2 seconds

### 4. `database/cleanup-expired-users.php` (NEW)
- **Purpose:** Delete expired unverified users
- **Can run:** Manually or via cron job
- **SQL:** Deletes where email_verified=0 and token_expiry < NOW()

## 🎯 Advantages

### Better UX
- ✅ Simpler first step (just email & password)
- ✅ Only verified users enter full details
- ✅ No wasted effort for users who won't verify

### Security
- ✅ Shorter token expiry (10 minutes vs 24 hours)
- ✅ Auto-cleanup of abandoned registrations
- ✅ No incomplete accounts lingering

### Data Quality
- ✅ Only completed profiles in database
- ✅ All registered users have verified emails
- ✅ Less spam registrations

## 🧪 Testing the New Flow

### Test 1: Successful Registration
1. Visit `register.php`
2. Enter email and password
3. Check email (should arrive within seconds)
4. Click verification link
5. Complete profile form
6. Should redirect to login
7. Login with email and password

### Test 2: Expired Token
1. Register with email/password
2. **Wait 10 minutes** (or modify database: `UPDATE users SET token_expiry = NOW() - INTERVAL 1 MINUTE`)
3. Click verification link
4. Should show "expired" message
5. Account should be deleted
6. Can register again with same email

### Test 3: Duplicate Email (Unverified)
1. Register with email
2. Don't verify
3. Register again with same email
4. Old account deleted, new one created
5. New verification email sent

### Test 4: Duplicate Email (Verified)
1. Complete registration fully
2. Try to register with same email again
3. Should show error: "Email already registered"

## 📊 Database Changes

### Users Table
No schema changes needed! Uses existing columns:
- `email` - Stored in step 1
- `password_hash` - Stored in step 1
- `verification_token` - 10-minute expiry
- `token_expiry` - NOW() + 10 minutes
- `full_name` - Added in step 2 (initially NULL)
- `roll_number` - Added in step 2 (initially NULL)
- `department` - Added in step 2 (initially NULL)
- `year` - Added in step 2 (initially NULL)
- `phone` - Added in step 2 (initially NULL)

## 🔧 Maintenance

### Automatic Cleanup
Users with expired tokens are deleted when they try to verify.

### Manual Cleanup (Optional)
Run this periodically to clean up any stragglers:
```bash
php database/cleanup-expired-users.php
```

### Cron Job (Linux/Mac)
Add to crontab to run every 15 minutes:
```bash
*/15 * * * * cd /path/to/evento && php database/cleanup-expired-users.php
```

### Windows Task Scheduler
Create a scheduled task:
```
Program: C:\xampp\php\php.exe
Arguments: C:\xampp\htdocs\evento\database\cleanup-expired-users.php
Schedule: Every 15 minutes
```

## ⚠️ Important Notes

### Email Must Be Fast
Since token expires in 10 minutes, email must arrive quickly:
- Use reliable SMTP (Gmail, SendGrid, etc.)
- Test email delivery speed
- Check spam folders

### User Communication
Make sure users know:
- ⏱️ They have 10 minutes to verify
- 📧 Check spam folder
- 🔄 They can register again if expired

### Resend Functionality
The `resend-verification.php` page still works, but:
- If user is expired, account is deleted
- User should just register again instead

## 🎨 UI Messages

### Registration Page
"Step 1: Enter your email and create a password"

"⏱️ Important: After clicking register, you have **10 minutes** to verify your email and complete your profile."

### Email Sent
"Please check your email! A verification link has been sent to {email}. You have 10 minutes to verify and complete your registration."

### Expired Token
"This verification link has expired (10 minutes). Your account has been deleted. Please register again."

### Complete Profile Page
"✅ Email Verified!"

"Step 2: Complete your profile to finish registration"

## 🚀 Deployment

### Already Deployed!
All changes are complete and ready to use:
- ✅ register.php updated
- ✅ verify-email.php updated
- ✅ complete-profile.php created
- ✅ cleanup script created
- ✅ 10-minute expiry configured

### Just Test!
1. Visit: https://hitanshparikh.tech/evento/register.php
2. Register with a test email
3. Verify and complete profile
4. Login and enjoy!

## 📈 Analytics to Track

Monitor these metrics:
- Registration start rate
- Email verification rate
- Profile completion rate
- Time between registration and verification
- Expired token rate

**Target:** >80% of users who register should complete profile within 10 minutes.

---

**New Flow Active:** ✅  
**Token Expiry:** 10 minutes  
**Auto-Delete:** Yes  
**Status:** Production Ready
