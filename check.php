<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evento - Installation Check</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f1f5f9;
            padding: 2rem;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 3rem;
        }
        h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: #94a3b8;
        }
        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(20px);
        }
        .check-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin-bottom: 0.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
        }
        .status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .status.success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        .status.error {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .status.warning {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 0.5rem;
            transition: transform 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .actions {
            text-align: center;
            margin-top: 2rem;
        }
        pre {
            background: rgba(0, 0, 0, 0.3);
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            margin: 1rem 0;
        }
        code {
            color: #8b5cf6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Evento Installation Check</h1>
            <p class="subtitle">Verify your installation before proceeding</p>
        </div>

        <?php
        $checks = [];
        $overall_status = true;

        // PHP Version Check
        $php_version = phpversion();
        $php_ok = version_compare($php_version, '7.4', '>=');
        $checks[] = [
            'name' => 'PHP Version',
            'status' => $php_ok,
            'message' => "PHP $php_version" . ($php_ok ? ' ✓' : ' (Requires 7.4+)')
        ];
        $overall_status = $overall_status && $php_ok;

        // PDO Extension
        $pdo_ok = extension_loaded('PDO') && extension_loaded('pdo_mysql');
        $checks[] = [
            'name' => 'PDO MySQL Extension',
            'status' => $pdo_ok,
            'message' => $pdo_ok ? 'Installed' : 'Not Found'
        ];
        $overall_status = $overall_status && $pdo_ok;

        // Database Connection
        try {
            require_once __DIR__ . '/config/database.php';
            $db = Database::getInstance()->getConnection();
            $checks[] = [
                'name' => 'Database Connection',
                'status' => true,
                'message' => 'Connected Successfully'
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'Database Connection',
                'status' => false,
                'message' => 'Connection Failed: ' . $e->getMessage()
            ];
            $overall_status = false;
        }

        // Check if tables exist
        if (isset($db)) {
            try {
                $stmt = $db->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $required_tables = ['users', 'events', 'event_registrations', 'roles', 'clubs'];
                $tables_ok = count(array_intersect($required_tables, $tables)) === count($required_tables);
                
                $checks[] = [
                    'name' => 'Database Tables',
                    'status' => $tables_ok,
                    'message' => count($tables) . ' tables found' . ($tables_ok ? ' ✓' : ' (Missing required tables)')
                ];
                $overall_status = $overall_status && $tables_ok;

                // Check admin user
                $stmt = $db->query("SELECT COUNT(*) FROM users WHERE email = 'admin@college.edu'");
                $admin_exists = $stmt->fetchColumn() > 0;
                $checks[] = [
                    'name' => 'Admin User',
                    'status' => $admin_exists,
                    'message' => $admin_exists ? 'Default admin exists' : 'Admin user not found'
                ];
                $overall_status = $overall_status && $admin_exists;

            } catch (Exception $e) {
                $checks[] = [
                    'name' => 'Database Tables',
                    'status' => false,
                    'message' => 'Error checking tables: ' . $e->getMessage()
                ];
                $overall_status = false;
            }
        }

        // Upload Directory
        $upload_dir = __DIR__ . '/public/uploads';
        $upload_ok = is_dir($upload_dir) && is_writable($upload_dir);
        $checks[] = [
            'name' => 'Upload Directory',
            'status' => $upload_ok,
            'message' => $upload_ok ? 'Writable' : 'Not found or not writable'
        ];

        // Session
        $session_ok = session_status() !== PHP_SESSION_DISABLED;
        $checks[] = [
            'name' => 'PHP Sessions',
            'status' => $session_ok,
            'message' => $session_ok ? 'Enabled' : 'Disabled'
        ];
        $overall_status = $overall_status && $session_ok;

        // GD Library
        $gd_ok = extension_loaded('gd');
        $checks[] = [
            'name' => 'GD Library (Image Processing)',
            'status' => $gd_ok,
            'message' => $gd_ok ? 'Installed' : 'Not installed (optional)'
        ];
        ?>

        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">System Requirements</h2>
            <?php foreach ($checks as $check): ?>
                <div class="check-item">
                    <span><?php echo $check['name']; ?></span>
                    <span class="status <?php echo $check['status'] ? 'success' : 'error'; ?>">
                        <?php echo $check['message']; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($overall_status): ?>
            <div class="card" style="border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.05);">
                <h2 style="color: #10b981; margin-bottom: 1rem;">✓ Installation Successful!</h2>
                <p style="margin-bottom: 1.5rem; color: #94a3b8;">
                    Your Evento system is properly configured and ready to use.
                </p>
                
                <h3 style="margin: 1rem 0 0.5rem 0;">Default Admin Credentials:</h3>
                <pre><code>Email: admin@college.edu
Password: Admin@123</code></pre>
                
                <div class="actions">
                    <a href="login.php" class="btn">Go to Login</a>
                    <a href="register.php" class="btn">Register New User</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05);">
                <h2 style="color: #ef4444; margin-bottom: 1rem;">⚠ Installation Incomplete</h2>
                <p style="margin-bottom: 1rem; color: #94a3b8;">
                    Please fix the errors above before proceeding.
                </p>
                
                <h3 style="margin: 1rem 0 0.5rem 0;">Quick Fix Steps:</h3>
                <ol style="margin-left: 2rem; line-height: 1.8; color: #94a3b8;">
                    <li>Ensure XAMPP Apache and MySQL are running</li>
                    <li>Create database 'evento' in phpMyAdmin</li>
                    <li>Import database/schema.sql</li>
                    <li>Create public/uploads directory</li>
                    <li>Check config/database.php settings</li>
                </ol>
                
                <div class="actions">
                    <a href="check.php" class="btn" onclick="location.reload()">Recheck Installation</a>
                    <a href="SETUP.md" class="btn">View Setup Guide</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3 style="margin-bottom: 1rem;">Next Steps</h3>
            <ol style="margin-left: 2rem; line-height: 2; color: #94a3b8;">
                <li>Login with admin credentials</li>
                <li>Change default admin password</li>
                <li>Create clubs and assign leaders</li>
                <li>Add faculty members</li>
                <li>Create test events</li>
                <li>Test student registration</li>
            </ol>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 1rem;">Optional: Load Sample Data</h3>
            <p style="color: #94a3b8; margin-bottom: 1rem;">
                Want to test with sample users and events? Import the sample data:
            </p>
            <pre><code>Import: database/sample_data.sql in phpMyAdmin</code></pre>
            <p style="color: #94a3b8; margin-top: 0.5rem; font-size: 0.875rem;">
                This will add 5 students, 2 faculty members, and 5 events with registrations.
            </p>
        </div>

        <div style="text-align: center; margin-top: 3rem; color: #64748b;">
            <p>Evento v1.0 - College Event Management System</p>
            <p style="margin-top: 0.5rem;">For support, check README.md or SETUP.md</p>
        </div>
    </div>
</body>
</html>
