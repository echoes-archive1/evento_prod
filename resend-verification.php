<?php
require_once __DIR__ . '/config/config.php';

$message = '';
$success = false;

// Check if coming from login with unverified user
$user_id = $_SESSION['unverified_user_id'] ?? null;
$user_email = $_SESSION['unverified_user_email'] ?? null;
$user_name = $_SESSION['unverified_user_name'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $message = 'Invalid security token. Please try again.';
    } else {
        $email = Security::sanitize($_POST['email'] ?? '');
        
        if (empty($email)) {
            $message = 'Please enter your email address.';
        } else {
            try {
                $db = Database::getInstance()->getConnection();
                
                // Find user
                $sql = "SELECT id, full_name, email, email_verified, verification_token, token_expiry 
                        FROM users WHERE email = :email";
                $stmt = $db->prepare($sql);
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $message = 'No account found with this email address.';
                } elseif ($user['email_verified']) {
                    $message = 'Your email is already verified. You can <a href="login.php" style="color: #667eea;">login here</a>.';
                    $success = true;
                } else {
                    // Generate new verification token and code
                    $verification_token = bin2hex(random_bytes(32));
                    $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    // Update token and code
                    $update_sql = "UPDATE users 
                                  SET verification_token = :token,
                                      verification_code = :code, 
                                      token_expiry = :expiry 
                                  WHERE id = :user_id";
                    $update_stmt = $db->prepare($update_sql);
                    $update_stmt->execute([
                        'token' => $verification_token,
                        'code' => $verification_code,
                        'expiry' => $token_expiry,
                        'user_id' => $user['id']
                    ]);
                    
                    // Send verification email
                    $email_sent = Email::sendVerificationEmail($user['email'], $user['full_name'], $verification_token, $verification_code);
                    
                    if ($email_sent) {
                        $message = 'Verification email sent successfully! Please check your inbox and spam folder.';
                        $success = true;
                        
                        // Clear session data
                        unset($_SESSION['unverified_user_id']);
                        unset($_SESSION['unverified_user_email']);
                        unset($_SESSION['unverified_user_name']);
                        
                        // Log action
                        Security::logAudit($user['id'], 'verification_resent', 'users', $user['id']);
                    } else {
                        $message = 'Failed to send verification email. Please try again later or contact support.';
                    }
                }
            } catch (Exception $e) {
                error_log("Resend verification error: " . $e->getMessage());
                $message = 'An error occurred. Please try again later.';
            }
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
    <title>Resend Verification Email - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/auth.css?v=<?php echo time(); ?>">
    <style>
        .info-box {
            background: rgba(102, 126, 234, 0.1);
            border-left: 4px solid #667eea;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        
        .info-box p {
            margin: 0;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
        }
        
        .info-box strong {
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-glass-card">
            <div class="auth-header">
                <h1 class="auth-title">Resend Verification Email</h1>
                <p class="auth-subtitle">Enter your email to receive a new verification link</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $success ? 'success' : 'error'; ?>">
                    <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?php if ($success): ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        <?php else: ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        <?php endif; ?>
                    </svg>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($user_email): ?>
                <div class="info-box">
                    <p><strong>Account detected:</strong> <?php echo htmlspecialchars($user_email); ?></p>
                    <p>The email field below has been pre-filled for you.</p>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($user_email ?? $_POST['email'] ?? ''); ?>" 
                           placeholder="your.email@college.edu" autofocus>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    <span>Send Verification Email</span>
                    <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Already verified? <a href="<?php echo BASE_URL; ?>/login.php">Login here</a></p>
                <p>Need to register? <a href="<?php echo BASE_URL; ?>/register.php">Create an account</a></p>
            </div>
            
            <div class="demo-credentials" style="background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107;">
                <p style="color: #ffc107;"><strong>⚠️ Important:</strong></p>
                <p style="color: rgba(255, 255, 255, 0.9);">Check your spam/junk folder if you don't see the email within a few minutes.</p>
                <p style="color: rgba(255, 255, 255, 0.9);">Verification links expire after 24 hours.</p>
            </div>
        </div>
        
        <div class="auth-bg-blur"></div>
    </div>
    
    <script src="<?php echo BASE_URL; ?>/public/js/auth.js"></script>
</body>
</html>
