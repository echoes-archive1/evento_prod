<?php
require_once __DIR__ . '/config/config.php';

$message = '';
$success = false;
$token = $_GET['token'] ?? '';
$code = $_POST['code'] ?? '';

// Handle verification code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $code) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $message = 'Invalid security token. Please try again.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Clean the code (remove spaces, dashes, etc.)
            $code = preg_replace('/[^0-9]/', '', $code);
            
            // Find user with this verification code
            $sql = "SELECT id, full_name, email, email_verified, token_expiry 
                    FROM users 
                    WHERE verification_code = :code";
            $stmt = $db->prepare($sql);
            $stmt->execute(['code' => $code]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $message = 'Invalid verification code. Please check the code and try again.';
            } elseif ($user['email_verified']) {
                $message = 'Your email has already been verified. You can login now.';
                $success = true;
            } elseif (strtotime($user['token_expiry']) < time()) {
                // Token expired - delete the user account
                $delete_sql = "DELETE FROM users WHERE id = :user_id";
                $delete_stmt = $db->prepare($delete_sql);
                $delete_stmt->execute(['user_id' => $user['id']]);
                
                $message = 'This verification code has expired (10 minutes). Your account has been deleted. Please register again.';
            } else {
                // Check if profile is complete
                if (empty($user['full_name'])) {
                    // Profile not complete - redirect to complete-profile.php
                    $_SESSION['verify_user_id'] = $user['id'];
                    $_SESSION['verify_email'] = $user['email'];
                    
                    // Clear pending verification session
                    unset($_SESSION['pending_verification_email']);
                    unset($_SESSION['pending_verification_user_id']);
                    unset($_SESSION['verification_sent_at']);
                    
                    header('Location: ' . BASE_URL . '/complete-profile.php');
                    exit;
                } else {
                    // Profile already complete - just verify email
                    $update_sql = "UPDATE users 
                                  SET email_verified = 1, 
                                      email_verified_at = NOW(), 
                                      verification_token = NULL,
                                      verification_code = NULL,
                                      token_expiry = NULL
                                  WHERE id = :user_id";
                    $update_stmt = $db->prepare($update_sql);
                    $update_stmt->execute(['user_id' => $user['id']]);
                    
                    // Log verification
                    Security::logAudit($user['id'], 'email_verified', 'users', $user['id']);
                    
                    $message = 'Email verified successfully! You can now login and access all features.';
                    $success = true;
                }
            }
        } catch (Exception $e) {
            error_log("Code verification error: " . $e->getMessage());
            $message = 'An error occurred during verification. Please try again later.';
        }
    }
}

// Handle token-based verification (link from email)
if ($token && !$code) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Find user with this token
        $sql = "SELECT id, full_name, email, email_verified, token_expiry 
                FROM users 
                WHERE verification_token = :token";
        $stmt = $db->prepare($sql);
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $message = 'Invalid verification link. The link may have been used already or does not exist.';
        } elseif ($user['email_verified']) {
            $message = 'Your email has already been verified. You can login now.';
            $success = true;
        } elseif (strtotime($user['token_expiry']) < time()) {
            // Token expired - delete the user account
            $delete_sql = "DELETE FROM users WHERE id = :user_id";
            $delete_stmt = $db->prepare($delete_sql);
            $delete_stmt->execute(['user_id' => $user['id']]);
            
            $message = 'This verification link has expired (10 minutes). Your account has been deleted. Please register again.';
        } else {
            // Check if profile is complete
            if (empty($user['full_name'])) {
                // Profile not complete - redirect to complete-profile.php
                $_SESSION['verify_user_id'] = $user['id'];
                $_SESSION['verify_email'] = $user['email'];
                
                // Clear pending verification session
                unset($_SESSION['pending_verification_email']);
                unset($_SESSION['pending_verification_user_id']);
                unset($_SESSION['verification_sent_at']);
                
                header('Location: ' . BASE_URL . '/complete-profile.php');
                exit;
            } else {
                // Profile already complete - just verify email
                $update_sql = "UPDATE users 
                              SET email_verified = 1, 
                                  email_verified_at = NOW(), 
                                  verification_token = NULL,
                                  verification_code = NULL,
                                  token_expiry = NULL
                              WHERE id = :user_id";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->execute(['user_id' => $user['id']]);
                
                // Log verification
                Security::logAudit($user['id'], 'email_verified', 'users', $user['id']);
                
                $message = 'Email verified successfully! You can now login and access all features.';
                $success = true;
            }
        }
    } catch (Exception $e) {
        error_log("Email verification error: " . $e->getMessage());
        $message = 'An error occurred during verification. Please try again later.';
    }
} elseif (!$token && !$code) {
    $message = 'No verification token or code provided.';
}

$csrf_token = generateCSRFToken();
?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/auth.css?v=<?php echo time(); ?>">
    <style>
        .verification-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .verification-icon.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .verification-icon.error {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }
        
        .verification-icon svg {
            width: 48px;
            height: 48px;
            color: #ffffff;
        }
        
        .verification-message {
            text-align: center;
            margin: 24px 0;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .verification-message h2 {
            margin: 0 0 12px 0;
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
        }
        
        .verification-message p {
            margin: 0;
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
            line-height: 1.6;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 32px;
        }
        
        .btn {
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-glass-card">
            <div class="auth-header">
                <h1 class="auth-title"><?php echo APP_NAME; ?></h1>
            </div>
            
            <div class="verification-icon <?php echo $success ? 'success' : 'error'; ?>">
                <?php if ($success): ?>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                <?php else: ?>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                <?php endif; ?>
            </div>
            
            <div class="verification-message">
                <h2><?php echo $success ? 'Email Verified!' : 'Verification Failed'; ?></h2>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            
            <div class="action-buttons">
                <?php if ($success): ?>
                    <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary">
                        <svg style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                        Login Now
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-secondary">
                        Register Again
                    </a>
                    <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary">
                        Go to Login
                    </a>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 24px;">
                <a href="<?php echo BASE_URL; ?>" style="color: rgba(255, 255, 255, 0.6); text-decoration: none; font-size: 14px;">
                    ← Back to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>
