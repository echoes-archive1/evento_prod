# Email Verification Flow with Code

## Complete User Journey

```
┌─────────────────────────────────────────────────────────────────┐
│                    REGISTRATION FLOW                             │
└─────────────────────────────────────────────────────────────────┘

                        [USER VISITS SITE]
                               ↓
                    ┌──────────────────────┐
                    │   register.php       │
                    │                      │
                    │  • Enter email       │
                    │  • Enter password    │
                    │  • Submit form       │
                    └──────────────────────┘
                               ↓
                               ↓
        ┌─────────────────────────────────────────┐
        │  BACKEND PROCESSING (register.php)      │
        │                                         │
        │  1. Validate input                      │
        │  2. Generate verification_token         │
        │  3. Generate verification_code (NEW!)   │
        │     └─→ Random 6-digit: "042891"       │
        │  4. Set expiry: 10 minutes              │
        │  5. Insert into database                │
        │  6. Send email with BOTH token & code   │
        └─────────────────────────────────────────┘
                               ↓
                               ↓
                    ┌──────────────────────┐
                    │ verification-        │
                    │ status.php           │
                    │                      │
                    │ Waiting for          │
                    │ verification...      │
                    └──────────────────────┘
                               ↓
                   ┌───────────┴───────────┐
                   ↓                       ↓
                                    
    ┌──────────────────────┐      ┌──────────────────────┐
    │   USER'S EMAIL       │      │  VERIFICATION PAGE   │
    │                      │      │                      │
    │  ┌────────────────┐  │      │  [Code Input Form]  │
    │  │  Your Code:    │  │      │                      │
    │  │                │  │      │  ┏━━━━━━━━━━━━━━━┓  │
    │  │   042891       │←─┼──────┼─→┃  [042891]    ┃  │
    │  │                │  │      │  ┗━━━━━━━━━━━━━━━┛  │
    │  └────────────────┘  │      │                      │
    │                      │      │  [Verify Button]     │
    │  [Verify Button Link]│      │                      │
    └──────────────────────┘      └──────────────────────┘
              │                              │
              │                              │
              ↓                              ↓
                                    
        ┌─────────────────────────────────────────┐
        │         TWO VERIFICATION METHODS         │
        ├─────────────────────────────────────────┤
        │                                         │
        │  METHOD 1: Click Link (EXISTING)       │
        │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
        │  verify-email.php?token=abc123...      │
        │                                         │
        │  METHOD 2: Enter Code (NEW!)           │
        │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
        │  POST to verify-email.php              │
        │  with code=042891                      │
        └─────────────────────────────────────────┘
                               ↓
                               ↓
        ┌─────────────────────────────────────────┐
        │   VERIFICATION PROCESSING               │
        │   (verify-email.php)                    │
        │                                         │
        │  IF TOKEN PROVIDED:                     │
        │    • Find user by token                 │
        │    • Check expiry                       │
        │    • Verify email                       │
        │                                         │
        │  IF CODE PROVIDED (NEW!):               │
        │    • Clean input (numbers only)         │
        │    • Find user by code                  │
        │    • Check expiry                       │
        │    • Verify email                       │
        │                                         │
        │  THEN:                                  │
        │    • Set email_verified = 1             │
        │    • Clear verification_token           │
        │    • Clear verification_code            │
        │    • Store user_id in session           │
        └─────────────────────────────────────────┘
                               ↓
                               ↓
                    ┌──────────────────────┐
                    │  complete-           │
                    │  profile.php         │
                    │                      │
                    │  • Enter name        │
                    │  • Enter roll no.    │
                    │  • Enter department  │
                    │  • Submit            │
                    └──────────────────────┘
                               ↓
                               ↓
                    ┌──────────────────────┐
                    │   DASHBOARD          │
                    │                      │
                    │   ✅ Registration    │
                    │      Complete!       │
                    └──────────────────────┘


═══════════════════════════════════════════════════════════════════

## Detailed Code Verification Flow (NEW!)

┌─────────────────────────────────────────────────────────────────┐
│                  USER ENTERS CODE                                │
└─────────────────────────────────────────────────────────────────┘

        User sees code in email: "042891"
                    ↓
        User clicks in input field
                    ↓
        Types or pastes: "042891"
                    ↓
        JavaScript validates: numbers only
                    ↓
        User clicks [Verify] button
                    ↓
                    
┌─────────────────────────────────────────────────────────────────┐
│               FORM SUBMISSION (POST)                             │
└─────────────────────────────────────────────────────────────────┘

        POST /verify-email.php
        {
          csrf_token: "...",
          code: "042891"
        }
                    ↓
                    
┌─────────────────────────────────────────────────────────────────┐
│            SERVER PROCESSING (verify-email.php)                  │
└─────────────────────────────────────────────────────────────────┘

    1. Verify CSRF token
       ✓ Valid
                    ↓
    2. Clean code input
       Input: "042891"
       Clean: "042891" (remove non-digits)
                    ↓
    3. Database query
       SELECT * FROM users 
       WHERE verification_code = '042891'
                    ↓
    4. Check results
       ┌──────────────────────┐
       │ User found?          │
       │ ┌────┐               │
       │ │ NO │ → Invalid code error
       │ └────┘               │
       │ ┌────┐               │
       │ │YES │ → Continue    │
       │ └────┘               │
       └──────────────────────┘
                    ↓
    5. Check if already verified
       ┌──────────────────────┐
       │ email_verified = 1?  │
       │ ┌────┐               │
       │ │YES │ → Already verified message
       │ └────┘               │
       │ ┌────┐               │
       │ │ NO │ → Continue    │
       │ └────┘               │
       └──────────────────────┘
                    ↓
    6. Check expiry
       ┌──────────────────────┐
       │ token_expiry > now?  │
       │ ┌────┐               │
       │ │ NO │ → Expired, delete user
       │ └────┘               │
       │ ┌────┐               │
       │ │YES │ → Continue    │
       │ └────┘               │
       └──────────────────────┘
                    ↓
    7. Update database
       UPDATE users SET
         email_verified = 1,
         email_verified_at = NOW(),
         verification_token = NULL,
         verification_code = NULL,
         token_expiry = NULL
       WHERE id = user_id
                    ↓
    8. Set session
       $_SESSION['verify_user_id'] = user_id
       $_SESSION['verify_email'] = email
                    ↓
    9. Redirect
       → /complete-profile.php
                    ↓
                    
┌─────────────────────────────────────────────────────────────────┐
│                    SUCCESS!                                      │
└─────────────────────────────────────────────────────────────────┘

        User completes profile
                    ↓
        Registration complete
                    ↓
        Access to dashboard


═══════════════════════════════════════════════════════════════════

## Database State Changes

┌─────────────────────────────────────────────────────────────────┐
│              BEFORE VERIFICATION                                 │
├─────────────────────────────────────────────────────────────────┤
│ id: 123                                                         │
│ email: user@example.com                                         │
│ email_verified: 0                          ← Not verified       │
│ verification_token: "abc123def456..."      ← For link method    │
│ verification_code: "042891"                ← For code method    │
│ token_expiry: "2025-12-31 14:10:00"       ← 10 min expiry      │
│ email_verified_at: NULL                                         │
└─────────────────────────────────────────────────────────────────┘
                                ↓
                    [ VERIFICATION OCCURS ]
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│              AFTER VERIFICATION                                  │
├─────────────────────────────────────────────────────────────────┤
│ id: 123                                                         │
│ email: user@example.com                                         │
│ email_verified: 1                          ← ✅ VERIFIED!       │
│ verification_token: NULL                   ← Cleared            │
│ verification_code: NULL                    ← Cleared            │
│ token_expiry: NULL                         ← Cleared            │
│ email_verified_at: "2025-12-31 14:05:23"  ← Timestamp          │
└─────────────────────────────────────────────────────────────────┘


═══════════════════════════════════════════════════════════════════

## Security Flow

┌─────────────────────────────────────────────────────────────────┐
│                   SECURITY CHECKS                                │
└─────────────────────────────────────────────────────────────────┘

    [INPUT RECEIVED]
          ↓
    ┌──────────────────────┐
    │ 1. CSRF Token        │ → Prevents cross-site attacks
    │    Validation        │
    └──────────────────────┘
          ↓
    ┌──────────────────────┐
    │ 2. Input             │ → Remove non-numeric characters
    │    Sanitization      │    "0 4 2 8 9 1" → "042891"
    └──────────────────────┘
          ↓
    ┌──────────────────────┐
    │ 3. Prepared          │ → Prevent SQL injection
    │    Statements        │    PDO with placeholders
    └──────────────────────┘
          ↓
    ┌──────────────────────┐
    │ 4. Expiry Check      │ → Code valid for 10 minutes
    │                      │    Expired → Delete account
    └──────────────────────┘
          ↓
    ┌──────────────────────┐
    │ 5. One-Time Use      │ → Code cleared after use
    │                      │    Cannot reuse
    └──────────────────────┘
          ↓
    ┌──────────────────────┐
    │ 6. Audit Logging     │ → Track all verification attempts
    │                      │
    └──────────────────────┘
          ↓
    [VERIFIED ✅]


═══════════════════════════════════════════════════════════════════

## Error Handling Flow

┌─────────────────────────────────────────────────────────────────┐
│                    POSSIBLE ERRORS                               │
└─────────────────────────────────────────────────────────────────┘

    Error 1: Invalid Code
    ───────────────────────────
    User enters: "000000"
    Database: No match found
    Response: "Invalid verification code"
    Action: User can try again

    Error 2: Expired Code
    ───────────────────────────
    Code age: > 10 minutes
    Response: "Code has expired"
    Database: User account deleted
    Action: Must register again

    Error 3: Already Verified
    ───────────────────────────
    email_verified = 1
    Response: "Already verified, please login"
    Action: Redirect to login

    Error 4: CSRF Token Invalid
    ───────────────────────────
    Token mismatch
    Response: "Security token invalid"
    Action: Refresh page, try again

    Error 5: Database Error
    ───────────────────────────
    Connection/query fails
    Response: "Error occurred, try again"
    Logged: Error details in log file


═══════════════════════════════════════════════════════════════════

## Success Metrics

    ✅ Code generated successfully
    ✅ Email delivered with code
    ✅ User receives code within seconds
    ✅ User copies code easily
    ✅ User pastes in input field
    ✅ Code validates correctly
    ✅ Database updated
    ✅ Session created
    ✅ Redirect to profile completion
    ✅ Registration completed

    🎉 VERIFICATION SUCCESSFUL!


═══════════════════════════════════════════════════════════════════
                      END OF FLOW DIAGRAM
═══════════════════════════════════════════════════════════════════
