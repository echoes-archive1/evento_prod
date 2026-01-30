<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

// Check if there's a pending verification
if (!isset($_SESSION['pending_verification_email']) || !isset($_SESSION['pending_verification_user_id'])) {
    header('Location: ' . BASE_URL . '/register.php');
    exit();
}

$email = $_SESSION['pending_verification_email'];
$user_id = $_SESSION['pending_verification_user_id'];
$sent_at = $_SESSION['verification_sent_at'] ?? time();

// Calculate time remaining (10 minutes = 600 seconds)
$time_elapsed = time() - $sent_at;
$time_remaining = max(0, 600 - $time_elapsed); // 10 minutes in seconds

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/auth.css?v=<?php echo time(); ?>">
    <style>
        .verification-status {
            text-align: center;
            padding: 2rem 0;
        }
        
        .status-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 193, 7, 0.1);
            border: 3px solid #ffc107;
        }
        
        .status-icon.verified {
            background: rgba(16, 185, 129, 0.1);
            border-color: #10b981;
        }
        
        .status-icon.expired {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
        }
        
        .status-icon svg {
            width: 50px;
            height: 50px;
            color: #ffc107;
        }
        
        .status-icon.verified svg {
            color: #10b981;
        }
        
        .status-icon.expired svg {
            color: #ef4444;
        }
        
        .timer {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffc107;
            margin: 1.5rem 0;
        }
        
        .timer.expired {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        
        .email-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 50px;
            color: #818cf8;
            font-size: 0.9rem;
            margin: 1rem 0;
        }
        
        .instructions {
            margin: 2rem 0;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            text-align: left;
        }
        
        .instructions h3 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .instructions ol {
            padding-left: 1.5rem;
            color: var(--text-secondary);
            line-height: 1.8;
        }
        
        .instructions ol li {
            margin-bottom: 0.5rem;
        }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffc107;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .pulse {
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Apple Liquid Glass Effect Button */
        .btn-liquid-glass {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.95);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 14px;
            cursor: pointer;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            box-shadow: 
                0 4px 6px rgba(0, 0, 0, 0.1),
                0 1px 3px rgba(0, 0, 0, 0.08),
                inset 0 1px 1px rgba(255, 255, 255, 0.1);
        }
        
        .btn-liquid-glass::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(
                135deg,
                rgba(255, 255, 255, 0.2) 0%,
                rgba(255, 255, 255, 0.05) 50%,
                rgba(255, 255, 255, 0.15) 100%
            );
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .btn-liquid-glass:hover {
            transform: translateY(-2px) scale(1.02);
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
            box-shadow: 
                0 8px 16px rgba(0, 0, 0, 0.15),
                0 3px 6px rgba(0, 0, 0, 0.1),
                inset 0 1px 1px rgba(255, 255, 255, 0.2),
                0 0 20px rgba(99, 102, 241, 0.3);
        }
        
        .btn-liquid-glass:hover::before {
            opacity: 1;
        }
        
        .btn-liquid-glass:active {
            transform: translateY(-1px) scale(0.98);
            box-shadow: 
                0 4px 8px rgba(0, 0, 0, 0.12),
                0 2px 4px rgba(0, 0, 0, 0.08),
                inset 0 1px 1px rgba(255, 255, 255, 0.15);
        }
        
        .btn-liquid-content {
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .btn-liquid-shine {
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            transform: rotate(45deg) translateX(-100%);
            transition: transform 0.6s ease;
        }
        
        .btn-liquid-glass:hover .btn-liquid-shine {
            transform: rotate(45deg) translateX(100%);
        }
        
        /* Subtle pulsing glow animation */
        @keyframes liquidGlow {
            0%, 100% {
                box-shadow: 
                    0 4px 6px rgba(0, 0, 0, 0.1),
                    0 1px 3px rgba(0, 0, 0, 0.08),
                    inset 0 1px 1px rgba(255, 255, 255, 0.1),
                    0 0 15px rgba(99, 102, 241, 0.2);
            }
            50% {
                box-shadow: 
                    0 4px 6px rgba(0, 0, 0, 0.1),
                    0 1px 3px rgba(0, 0, 0, 0.08),
                    inset 0 1px 1px rgba(255, 255, 255, 0.1),
                    0 0 25px rgba(99, 102, 241, 0.4);
            }
        }
        
        .btn-liquid-glass:focus {
            outline: none;
            animation: liquidGlow 2s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-glass-card">
            <div class="auth-header">
                <h1 class="auth-title">Verify Your Email</h1>
                <p class="auth-subtitle">We've sent a verification link to your email</p>
            </div>
            
            <div class="verification-status">
                <div class="status-icon" id="statusIcon">
                    <span class="spinner"></span>
                </div>
                
                <h2 id="statusTitle" style="margin-bottom: 1rem; color: var(--text-primary);">
                    Waiting for verification...
                </h2>
                
                <p id="statusMessage" style="color: var(--text-secondary); margin-bottom: 1rem;">
                    We're checking your verification status
                </p>
                
                <div class="email-badge">
                    <svg style="width: 16px; height: 16px; vertical-align: middle; margin-right: 0.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <?php echo htmlspecialchars($email); ?>
                </div>
                
                <!-- Verification Code Input Form -->
                <div style="margin: 30px 0; padding: 25px; background: rgba(99, 102, 241, 0.1); border-radius: 16px; border: 2px solid rgba(99, 102, 241, 0.3);">
                    <h3 style="margin: 0 0 15px 0; color: var(--text-primary); font-size: 18px; text-align: center;">
                        ✉️ Have your verification code?
                    </h3>
                    <p style="margin: 0 0 20px 0; color: var(--text-secondary); text-align: center; font-size: 14px;">
                        Enter the 6-digit code from your email to verify instantly
                    </p>
                    
                    <form method="POST" action="<?php echo BASE_URL; ?>/verify-email.php" id="codeForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div style="display: flex; gap: 10px; justify-content: center; align-items: center;">
                            <input 
                                type="text" 
                                name="code" 
                                id="verificationCode"
                                placeholder="000000" 
                                maxlength="6" 
                                pattern="[0-9]{6}"
                                required
                                style="width: 180px; padding: 14px 20px; font-size: 24px; font-weight: bold; letter-spacing: 8px; text-align: center; border: 2px solid rgba(99, 102, 241, 0.5); border-radius: 12px; background: rgba(255, 255, 255, 0.05); color: #6366f1; font-family: monospace;"
                            >
                            <button 
                                type="submit" 
                                class="btn btn-primary"
                                style="padding: 14px 28px; white-space: nowrap;"
                            >
                                <svg style="width: 20px; height: 20px; margin-right: 0.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Verify
                            </button>
                        </div>
                    </form>
                    
                    <p style="margin: 15px 0 0 0; text-align: center; color: var(--text-secondary); font-size: 13px;">
                        💡 Check your email for the verification code
                    </p>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <p style="color: var(--text-secondary); font-size: 14px;">
                        — OR —
                    </p>
                </div>
                
                <div class="timer" id="timer">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span id="timerText">
                        <?php
                        $minutes = floor($time_remaining / 60);
                        $seconds = $time_remaining % 60;
                        echo sprintf('%02d:%02d', $minutes, $seconds);
                        ?>
                    </span>
                </div>
                
                <div class="instructions">
                    <h3>📧 What to do next:</h3>
                    <ol>
                        <li><strong>Check your email inbox</strong> for a message from <?php echo MAIL_FROM_ADDRESS; ?></li>
                        <li><strong>Copy the 6-digit code</strong> and paste it above, OR</li>
                        <li><strong>Click the verification link</strong> in the email</li>
                        <li><strong>Complete your profile</strong> with your details</li>
                    </ol>
                    
                    <div style="margin-top: 1rem; padding: 1rem; background: rgba(255, 193, 7, 0.1); border-left: 3px solid #ffc107; border-radius: 8px;">
                        <p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary);">
                            <strong>⏱️ Important:</strong> The verification code expires in <strong>10 minutes</strong>. 
                            If expired, you'll need to register again.
                        </p>
                    </div>
                </div>
                
                <div id="actionButtons" style="margin-top: 2rem;">
                    <form method="POST" action="<?php echo BASE_URL; ?>/resend-verification.php" style="display: inline-block;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <button type="submit" class="btn-liquid-glass" style="margin-right: 1rem;">
                            <span class="btn-liquid-content">
                                <svg style="width: 20px; height: 20px; margin-right: 0.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Resend Email
                            </span>
                            <span class="btn-liquid-shine"></span>
                        </button>
                    </form>
                    
                    <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-secondary">
                        <svg style="width: 20px; height: 20px; margin-right: 0.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Register
                    </a>
                </div>
            </div>
        </div>
        
        <div class="auth-bg-blur"></div>
    </div>
    
    <script>
        let checkInterval;
        let timerInterval;
        let timeRemaining = <?php echo $time_remaining; ?>;
        
        // Auto-format verification code input
        const codeInput = document.getElementById('verificationCode');
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
            });
            
            // Focus on the input for easy access
            codeInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                const numbers = pastedText.replace(/[^0-9]/g, '').substring(0, 6);
                this.value = numbers;
            });
        }
        
        // Check verification status
        async function checkVerificationStatus() {
            try {
                const response = await fetch('<?php echo BASE_URL; ?>/api/check-verification.php?user_id=<?php echo $user_id; ?>');
                const data = await response.json();
                
                if (data.verified) {
                    // User is verified! Update UI
                    clearInterval(checkInterval);
                    clearInterval(timerInterval);
                    
                    const icon = document.getElementById('statusIcon');
                    icon.className = 'status-icon verified';
                    icon.innerHTML = `
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                        </svg>
                    `;
                    
                    document.getElementById('statusTitle').textContent = 'Email Verified! ✅';
                    document.getElementById('statusMessage').textContent = 'Redirecting to complete your profile...';
                    document.getElementById('timer').style.display = 'none';
                    
                    // Redirect to complete profile after 2 seconds
                    setTimeout(() => {
                        window.location.href = '<?php echo BASE_URL; ?>/complete-profile.php';
                    }, 2000);
                } else if (data.expired) {
                    // Token expired
                    clearInterval(checkInterval);
                    clearInterval(timerInterval);
                    
                    const icon = document.getElementById('statusIcon');
                    icon.className = 'status-icon expired';
                    icon.innerHTML = `
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    `;
                    
                    document.getElementById('statusTitle').textContent = 'Verification Expired ⏱️';
                    document.getElementById('statusMessage').textContent = 'Your verification link has expired. Please register again.';
                    document.getElementById('timer').className = 'timer expired';
                    document.getElementById('timerText').textContent = '00:00';
                }
            } catch (error) {
                console.error('Error checking verification status:', error);
            }
        }
        
        // Update countdown timer
        function updateTimer() {
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                clearInterval(checkInterval);
                
                const icon = document.getElementById('statusIcon');
                icon.className = 'status-icon expired';
                icon.innerHTML = `
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                `;
                
                document.getElementById('statusTitle').textContent = 'Verification Expired ⏱️';
                document.getElementById('statusMessage').textContent = 'Time limit reached. Please register again.';
                document.getElementById('timer').className = 'timer expired';
                document.getElementById('timerText').textContent = '00:00';
                return;
            }
            
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            document.getElementById('timerText').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            timeRemaining--;
        }
        
        // Start checking every 3 seconds
        checkInterval = setInterval(checkVerificationStatus, 3000);
        
        // Start countdown timer
        timerInterval = setInterval(updateTimer, 1000);
        
        // Check immediately on page load
        checkVerificationStatus();
    </script>
</body>
</html>
