# Quick Test Guide - Verification Code Feature

## Test the New Verification Code Feature

### Test 1: Registration with Code Verification

1. **Register a New Account**
   - Open: http://localhost/evento/register.php
   - Email: test@example.com
   - Password: StrongPass123!
   - Confirm Password: StrongPass123!
   - Click "Create Account"

2. **Verification Status Page**
   - You'll be redirected to the verification status page
   - You should see:
     - Email address displayed
     - Timer counting down from 10:00
     - **NEW**: Verification code input field (highlighted in purple)

3. **Check Your Email**
   - Open your email inbox
   - Find the verification email from Evento
   - You should see:
     - **NEW**: Large 6-digit code displayed prominently (e.g., 123456)
     - "Verify Email Address" button (existing feature)

4. **Verify Using Code**
   - Copy the 6-digit code from your email
   - Paste it into the verification code field on the verification status page
   - Click the "Verify" button
   - You should be redirected to the complete-profile page

5. **Complete Profile**
   - Fill in your details (name, roll number, department, etc.)
   - Submit to complete registration

### Test 2: Verification Using Link (Existing Method)

1. **Register Another Account**
   - Use a different email address
   
2. **Click Verification Link**
   - Instead of using the code, click the "Verify Email Address" button in the email
   - You should be redirected to complete-profile page directly

### Test 3: Invalid Code

1. **Enter Wrong Code**
   - Try entering: 000000 (or any invalid code)
   - Click "Verify"
   - You should see: "Invalid verification code. Please check the code and try again."

### Test 4: Expired Code

1. **Wait for Code to Expire**
   - Wait for the 10-minute timer to reach 00:00
   - Try to use the code
   - You should see: "This verification code has expired"

### Test 5: Resend Verification

1. **Click "Resend Email"**
   - From the verification status page
   - A new code should be generated
   - Check your email for the new 6-digit code

## Expected Results

✅ **Email should contain:**
- Large, bold 6-digit code in a dashed box
- Code is easy to copy
- Both verification methods clearly explained

✅ **Verification page should show:**
- Purple highlighted code input section
- 6-digit input field (only accepts numbers)
- "Verify" button next to the input
- "OR" separator between code input and other options

✅ **Code verification should:**
- Accept only numeric characters
- Auto-strip any non-numeric characters
- Verify instantly when submitted
- Redirect to complete-profile page on success

## Visual Checklist

When testing, verify these visual elements:

### Email
- [ ] 6-digit code is displayed in large, bold font
- [ ] Code is in a dashed border box with light background
- [ ] Text says "Your verification code:"
- [ ] Both verification methods are explained

### Verification Status Page
- [ ] Purple/blue highlighted section for code input
- [ ] Heading: "✉️ Have your verification code?"
- [ ] 6-digit input field with monospace font
- [ ] Input field has proper styling (border, padding)
- [ ] "Verify" button next to input field
- [ ] "— OR —" separator text
- [ ] Instructions mention copying the 6-digit code

### User Experience
- [ ] Code input only accepts numbers
- [ ] Paste functionality works properly
- [ ] Form submits successfully
- [ ] Redirects to complete-profile on success
- [ ] Shows appropriate error messages

## Database Verification

Check the database to confirm:

```sql
-- Check if column exists
SHOW COLUMNS FROM users LIKE 'verification_code';

-- Check recent registrations
SELECT id, email, verification_code, verification_token, token_expiry 
FROM users 
ORDER BY created_at DESC 
LIMIT 5;

-- Verify code format (should be 6 digits)
SELECT verification_code 
FROM users 
WHERE verification_code IS NOT NULL;
```

## Troubleshooting

### Code not showing in input field
- Clear browser cache
- Check if JavaScript is enabled

### Email not received
- Check spam folder
- Verify SMTP settings in config.php
- Check logs/error.log

### Code not working
- Make sure code hasn't expired (10 minutes)
- Verify you're copying exactly 6 digits
- Check database that verification_code column exists

## Success Indicators

🎉 **Feature is working correctly if:**
1. Email contains visible 6-digit code
2. Code input field accepts the code
3. Verification succeeds with valid code
4. User is redirected to complete-profile page
5. Old link-based verification still works

---

**Ready to test!** 🚀
