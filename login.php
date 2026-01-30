<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

// If already logged in, redirect to dashboard
if (Auth::check()) {
    Auth::redirectToDashboard();
}

$error = '';
$show_resend_link = false;

// Handle flash error messages from session
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Handle Google login error (legacy support, will be removed)
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    // Clean URL by redirecting without the error parameter
    if (!empty($error)) {
        $_SESSION['flash_error'] = $error;
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = Security::sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password';
        } else {
            try {
                $db = Database::getInstance()->getConnection();
                
                $sql = "SELECT id, full_name, email, password_hash, is_active, email_verified FROM users WHERE email = :email";
                $stmt = $db->prepare($sql);
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();
                
                if ($user && Security::verifyPassword($password, $user['password_hash'])) {
                    if (!$user['is_active']) {
                        $error = 'Your account has been deactivated. Please contact administrator.';
                    } elseif (ENABLE_EMAIL_VERIFICATION && !$user['email_verified']) {
                        // Store user info for resend functionality
                        $_SESSION['unverified_user_id'] = $user['id'];
                        $_SESSION['unverified_user_email'] = $user['email'];
                        $_SESSION['unverified_user_name'] = $user['full_name'];
                        $error = 'Please verify your email address before logging in.';
                        $show_resend_link = true;
                    } else {
                        // Login successful
                        Auth::login($user['id']);
                        
                        // Set remember me cookie (30 days)
                        if ($remember) {
                            $token = Security::generateToken();
                            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                        }
                        
                        // Check if redirecting from event registration
                        if (isset($_GET['redirect']) && $_GET['redirect'] === 'event' && isset($_GET['event_id'])) {
                            $event_id = (int)$_GET['event_id'];
                            // Store in session for after login redirect
                            $_SESSION['auto_register_event'] = $event_id;
                        }
                        
                        // Redirect to intended page or dashboard
                        $redirect = $_SESSION['redirect_after_login'] ?? null;
                        unset($_SESSION['redirect_after_login']);
                        
                        if ($redirect) {
                            header('Location: ' . $redirect);
                        } else {
                            Auth::redirectToDashboard();
                        }
                        exit;
                    }
                } else {
                    $error = 'Invalid email or password';
                    
                    // Log failed attempt
                    Security::logAudit(null, 'login_failed', null, null, ['email' => $email]);
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'Login failed. Please try again later.';
            }
        }
    }
}

// Check for timeout
if (isset($_GET['timeout'])) {
    $_SESSION['flash_error'] = 'Your session has expired. Please login again.';
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/auth.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="auth-container">
        <div class="auth-glass-card">
            <div class="auth-header">
                <h1 class="auth-title">Welcome Back</h1>
                <p class="auth-subtitle">Login to access college events</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <span><?php echo htmlspecialchars($error); ?></span>
                        <?php if ($show_resend_link): ?>
                            <br><a href="<?php echo BASE_URL; ?>/resend-verification.php" style="color: #fff; text-decoration: underline; font-weight: 600;">Resend verification email</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="email">College Email</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo $_POST['email'] ?? ''; ?>" 
                           placeholder="your.email@college.edu" autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required 
                               placeholder="Enter your password">
                        <button type="button" class="password-toggle" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                            <svg id="password-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-group form-checkbox">
                    <label>
                        <input type="checkbox" name="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                        <span>Remember me for 30 days</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    <span>Login</span>
                    <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
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
                <p>Don't have an account? <a href="<?php echo BASE_URL; ?>/register.php">Register here</a></p>
            </div>
            
            <div class="demo-credentials">
                <p><strong>Demo Login:</strong></p>
                <p>Admin: admin@college.edu / Admin@123</p>
            </div>
        </div>
        
        <div class="auth-bg-blur"></div>
    </div>
    
    <script src="<?php echo BASE_URL; ?>/public/js/auth.js"></script>
</body>
</html>
