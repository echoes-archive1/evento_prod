<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

// If already logged in, redirect to dashboard
if (Auth::check()) {
    Auth::redirectToDashboard();
}

$error = '';
$success = '';

// Handle Google login error
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = Security::sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        $validator = new Validator();
        
        $validator->required('email', $email, 'Email');
        $validator->email('email', $email);
        $validator->required('password', $password, 'Password');
        $validator->strongPassword('password', $password);
        
        if ($password !== $confirm_password) {
            $validator->addError('confirm_password', 'Passwords do not match');
        }
        
        // Check if email already exists (including unverified accounts)
        if (!empty($email)) {
            try {
                $db = Database::getInstance()->getConnection();
                
                // Check for existing verified account
                $check_sql = "SELECT id, email_verified FROM users WHERE email = :email";
                $check_stmt = $db->prepare($check_sql);
                $check_stmt->execute(['email' => $email]);
                $existing_user = $check_stmt->fetch();
                
                if ($existing_user && $existing_user['email_verified'] == 1) {
                    $validator->addError('email', 'This email is already registered. Please login.');
                } elseif ($existing_user && $existing_user['email_verified'] == 0) {
                    // Delete old unverified account to allow re-registration
                    $delete_sql = "DELETE FROM users WHERE id = :user_id";
                    $delete_stmt = $db->prepare($delete_sql);
                    $delete_stmt->execute(['user_id' => $existing_user['id']]);
                }
            } catch (Exception $e) {
                error_log("Email check error: " . $e->getMessage());
            }
        }
        
        if ($validator->passes()) {
            try {
                $db = Database::getInstance()->getConnection();
                
                // Generate verification token and code
                $verification_token = bin2hex(random_bytes(32));
                $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $token_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes')); // 10 minutes expiry
                
                // Insert user with minimal info (email and password only)
                $sql = "INSERT INTO users (email, password_hash, verification_token, verification_code, token_expiry, 
                        email_verified, created_at) 
                        VALUES (:email, :password_hash, :verification_token, :verification_code, :token_expiry, 
                        0, NOW())";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    'email' => $email,
                    'password_hash' => Security::hashPassword($password),
                    'verification_token' => $verification_token,
                    'verification_code' => $verification_code,
                    'token_expiry' => $token_expiry
                ]);
                
                if ($result) {
                    $user_id = $db->lastInsertId();
                    
                    // Log registration attempt
                    Security::logAudit($user_id, 'registration_started', 'users', $user_id);
                    
                    // Send verification email
                    $email_sent = Email::sendVerificationEmail($email, 'New User', $verification_token, $verification_code);
                    
                    if ($email_sent) {
                        // Store email and user_id in session for verification status page
                        $_SESSION['pending_verification_email'] = $email;
                        $_SESSION['pending_verification_user_id'] = $user_id;
                        $_SESSION['verification_sent_at'] = time();
                        
                        // Redirect to verification status page
                        header('Location: ' . BASE_URL . '/verification-status.php');
                        exit();
                    } else {
                        $error = 'Failed to send verification email. Please try again later.';
                        // Delete the user since we couldn't send email
                        $delete_sql = "DELETE FROM users WHERE id = :user_id";
                        $delete_stmt = $db->prepare($delete_sql);
                        $delete_stmt->execute(['user_id' => $user_id]);
                    }
                }
            } catch (PDOException $e) {
                error_log("Registration database error: " . $e->getMessage());
                $error = 'Registration failed. Please try again later.';
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = 'Registration failed. Please try again later.';
            }
        } else {
            $error = $validator->getFirstError();
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/auth.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="auth-container">
        <div class="auth-glass-card">
            <div class="auth-header">
                <h1 class="auth-title">Create Account</h1>
                <p class="auth-subtitle">Register once, access all events</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo $_POST['email'] ?? ''; ?>" 
                           placeholder="your.email@example.com" autofocus>
                    <small class="form-hint">You'll receive a verification link at this email</small>
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required 
                               placeholder="Create a strong password">
                        <button type="button" class="password-toggle" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                            <svg id="password-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                    <small class="form-hint">Min 8 characters with uppercase, lowercase, number & special character</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Re-enter your password">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')" aria-label="Toggle password visibility">
                            <svg id="confirm_password-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div style="background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107; padding: 12px; border-radius: 8px; margin: 16px 0;">
                    <p style="margin: 0; color: rgba(255, 255, 255, 0.9); font-size: 14px;">
                        ⏱️ <strong>Important:</strong> After clicking register, you have <strong>10 minutes</strong> to verify your email and complete your profile.
                    </p>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    <span>Send Verification Email</span>
                    <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </button>
            </form>
            
            <div class="auth-divider">
                <span>or</span>
            </div>
            
            <a href="<?php echo GoogleAuth::getLoginUrl(); ?>" class="btn btn-google btn-full">
                <svg class="google-icon" viewBox="0 0 24 24" width="20" height="20">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span>Continue with Google</span>
            </a>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="<?php echo BASE_URL; ?>/login.php">Login here</a></p>
            </div>
        </div>
        
        <div class="auth-bg-blur"></div>
    </div>
    
    <script src="<?php echo BASE_URL; ?>/public/js/auth.js"></script>
</body>
</html>
