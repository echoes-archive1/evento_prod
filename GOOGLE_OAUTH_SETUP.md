# Google OAuth Setup Guide

## 🔐 Google OAuth Integration for CHARUSAT Email Validation

This guide will help you set up Google OAuth authentication with domain validation for @charusat.edu.in and @charusat.ac.in email addresses.

---

## 📋 Prerequisites

1. Google Cloud Console account
2. XAMPP or similar PHP development environment
3. MySQL database with `evento_db` configured

---

## 🚀 Setup Steps

### Step 1: Create Google OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Navigate to **APIs & Services** → **Credentials**
4. Click **+ CREATE CREDENTIALS** → **OAuth client ID**
5. If prompted, configure the OAuth consent screen:
   - User Type: **External**
   - App name: **Evento - Event Management**
   - User support email: Your email
   - Developer contact: Your email
   - Scopes: Add `email` and `profile`
   - Test users: Add your test email addresses

6. Create OAuth Client ID:
   - Application type: **Web application**
   - Name: **Evento Web Client**
   - Authorized JavaScript origins (no paths allowed):
     ```
     http://localhost
     ```
   - Authorized redirect URIs (must include full path):
     ```
     http://localhost/evento/api/google-callback.php
     ```
   - For production, add your live domain:
     - Authorized JavaScript origins:
       ```
       https://yourdomain.com
       ```
     - Authorized redirect URIs:
       ```
       https://yourdomain.com/api/google-callback.php
       ```

7. Click **CREATE** and copy:
   - **Client ID**
   - **Client Secret**

### Step 2: Configure Application

1. Open `config/config.php`
2. Replace the placeholder values:
   ```php
   define('GOOGLE_CLIENT_ID', 'YOUR_ACTUAL_CLIENT_ID_HERE');
   define('GOOGLE_CLIENT_SECRET', 'YOUR_ACTUAL_CLIENT_SECRET_HERE');
   ```

### Step 3: Update Database

Run the SQL migration to add the `google_id` column:

```sql
-- Option 1: Run in phpMyAdmin
-- Select evento_db database and execute:

ALTER TABLE users 
ADD COLUMN google_id VARCHAR(255) DEFAULT NULL,
ADD UNIQUE INDEX idx_google_id (google_id);
```

Or import the file:
```bash
# From XAMPP MySQL bin directory
mysql -u root -p evento_db < add_google_oauth.sql
```

### Step 4: Test the Integration

1. Start XAMPP (Apache + MySQL)
2. Navigate to: `http://localhost/evento/login.php`
3. Click **"Continue with Google"**
4. Test with a CHARUSAT email address (@charusat.edu.in or @charusat.ac.in)

---

## 🎯 How It Works

### Domain Validation

The system automatically validates that users sign in with approved email domains:
- ✅ `student@charusat.edu.in` - **Allowed**
- ✅ `faculty@charusat.ac.in` - **Allowed**
- ❌ `user@gmail.com` - **Blocked**
- ❌ `user@yahoo.com` - **Blocked**

### First-Time Google Users

When a user signs in with Google for the first time:

1. System validates the email domain
2. If domain is valid, redirects to **Complete Profile** page
3. User fills in additional details:
   - Full Name
   - Roll Number (for students)
   - Department
   - User Type (Student/Faculty/Club Leader)
4. Account is created with email pre-verified
5. User is logged in automatically

### Existing Users

When a user with an existing account signs in with Google:

1. System matches email address
2. Automatically marks email as verified
3. Links Google ID to account
4. Logs user in immediately

---

## 🔧 Configuration Options

### Disable Email Verification for Google Users

Google-authenticated users automatically have verified emails. The system:
- Sets `email_verified = 1` on Google login
- Skips email verification step
- Links `google_id` to user record

### Change Allowed Domains

Edit `app/helpers/GoogleAuth.php`:

```php
private static $allowed_domains = ['charusat.edu.in', 'charusat.ac.in'];
```

Add or remove domains as needed.

---

## 🐛 Troubleshooting

### "Invalid state token" Error
- **Cause**: Session issue or CSRF attack prevention
- **Solution**: Clear browser cookies and try again

### "Only CHARUSAT email addresses are allowed"
- **Cause**: User tried to sign in with non-CHARUSAT email
- **Solution**: Use @charusat.edu.in or @charusat.ac.in email

### "Failed to get access token"
- **Cause**: Invalid Client ID/Secret or redirect URI mismatch
- **Solution**: 
  1. Verify credentials in `config/config.php`
  2. Check authorized redirect URIs in Google Console match exactly
  3. Ensure no extra spaces or typos

### "curl error" in logs
- **Cause**: cURL not enabled in PHP
- **Solution**: 
  1. Open `php.ini`
  2. Uncomment: `extension=curl`
  3. Restart Apache

---

## 🔒 Security Features

1. **State Token Validation**: Prevents CSRF attacks
2. **Domain Whitelist**: Only CHARUSAT emails allowed
3. **Secure Token Exchange**: HTTPS for token requests (in production)
4. **Session Management**: Proper session handling and timeout

---

## 📱 Production Deployment

Before deploying to production:

1. Update OAuth credentials:
   - Add production domain to authorized origins
   - Add production callback URL
   
2. Update `config/config.php`:
   ```php
   define('BASE_URL', 'https://yourdomain.com');
   define('GOOGLE_CLIENT_ID', 'production_client_id');
   define('GOOGLE_CLIENT_SECRET', 'production_client_secret');
   ```

3. Enable HTTPS:
   ```php
   ini_set('session.cookie_secure', 1); // Force HTTPS cookies
   ```

4. Submit OAuth consent screen for verification (for public use)

---

## 📄 Files Modified/Created

- ✅ `app/helpers/GoogleAuth.php` - Google OAuth handler class
- ✅ `api/google-callback.php` - OAuth callback endpoint
- ✅ `config/config.php` - Added Google credentials
- ✅ `login.php` - Added Google login button
- ✅ `register.php` - Added Google registration button
- ✅ `public/css/auth.css` - Added Google button styles
- ✅ `add_google_oauth.sql` - Database migration

---

## ✨ Features

- 🔐 **Secure OAuth 2.0** authentication
- 🎓 **Domain validation** for CHARUSAT emails only
- 🚀 **One-click registration** and login
- ✅ **Auto-verified emails** for Google users
- 🔄 **Seamless account linking** for existing users
- 🎨 **Beautiful UI** with Google brand guidelines

---

## 📞 Support

If you encounter any issues:

1. Check the error logs: `logs/error.log`
2. Enable debug mode in `config/config.php`:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```
3. Review Google Cloud Console logs
4. Check browser console for JavaScript errors

---

**Happy Coding! 🎉**
