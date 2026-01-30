<?php
/**
 * Google OAuth Configuration Test
 * Check if Google OAuth is properly configured
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/GoogleAuth.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google OAuth Test - Evento</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 { 
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .status {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
            font-size: 14px;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .status-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        .icon {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }
        .detail {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
            font-family: 'Courier New', monospace;
        }
        .detail-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }
        .detail-value {
            color: #6c757d;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .back-link {
            display: block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Google OAuth Configuration Test</h1>
        <p class="subtitle">Testing Google Sign-In integration for Evento</p>

        <?php
        // Test 1: Check if credentials are set
        $credentials_set = !empty(GOOGLE_CLIENT_ID) && !empty(GOOGLE_CLIENT_SECRET);
        ?>
        <div class="status <?php echo $credentials_set ? 'status-success' : 'status-error'; ?>">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <?php if ($credentials_set): ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                <?php else: ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                <?php endif; ?>
            </svg>
            <span><strong>OAuth Credentials:</strong> <?php echo $credentials_set ? 'Configured ✓' : 'Not Set ✗'; ?></span>
        </div>

        <?php
        // Test 2: Check database column
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'google_id'");
            $column_exists = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $column_exists = false;
        }
        ?>
        <div class="status <?php echo $column_exists ? 'status-success' : 'status-error'; ?>">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <?php if ($column_exists): ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                <?php else: ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                <?php endif; ?>
            </svg>
            <span><strong>Database Column (google_id):</strong> <?php echo $column_exists ? 'Exists ✓' : 'Missing ✗'; ?></span>
        </div>

        <?php
        // Test 3: Check GoogleAuth helper
        $helper_loaded = class_exists('GoogleAuth');
        ?>
        <div class="status <?php echo $helper_loaded ? 'status-success' : 'status-error'; ?>">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <?php if ($helper_loaded): ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                <?php else: ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                <?php endif; ?>
            </svg>
            <span><strong>GoogleAuth Helper:</strong> <?php echo $helper_loaded ? 'Loaded ✓' : 'Not Found ✗'; ?></span>
        </div>

        <?php if ($credentials_set): ?>
            <div class="detail">
                <div class="detail-label">Client ID:</div>
                <div class="detail-value"><?php echo htmlspecialchars(GOOGLE_CLIENT_ID); ?></div>
            </div>

            <div class="detail">
                <div class="detail-label">Redirect URI:</div>
                <div class="detail-value"><?php echo htmlspecialchars(BASE_URL . '/api/google-callback.php'); ?></div>
            </div>

            <?php if ($helper_loaded): ?>
                <?php
                try {
                    $login_url = GoogleAuth::getLoginUrl();
                    $url_generated = !empty($login_url);
                } catch (Exception $e) {
                    $url_generated = false;
                    $error_msg = $e->getMessage();
                }
                ?>
                <div class="status <?php echo $url_generated ? 'status-success' : 'status-error'; ?>">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?php if ($url_generated): ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        <?php else: ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        <?php endif; ?>
                    </svg>
                    <span><strong>Login URL Generation:</strong> <?php echo $url_generated ? 'Working ✓' : 'Failed ✗'; ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        $all_tests_passed = $credentials_set && $column_exists && $helper_loaded && ($url_generated ?? false);
        ?>

        <?php if ($all_tests_passed): ?>
            <div class="status status-success" style="margin-top: 30px; font-size: 16px; font-weight: bold;">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>✨ Google OAuth is fully configured and ready!</span>
            </div>
            
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn">
                Test Google Login →
            </a>
        <?php else: ?>
            <div class="status status-warning" style="margin-top: 30px;">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span>Some configuration steps are incomplete. Please check the items above.</span>
            </div>
        <?php endif; ?>

        <a href="<?php echo BASE_URL; ?>/index.php" class="back-link">← Back to Dashboard</a>
    </div>
</body>
</html>
