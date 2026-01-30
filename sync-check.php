<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evento - Sync Status Check</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            margin: 0;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .version {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }
        .check-item {
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }
        .check-item.success {
            background: #d1fae5;
            border-left-color: #10b981;
        }
        .check-item.warning {
            background: #fef3c7;
            border-left-color: #f59e0b;
        }
        .check-item.error {
            background: #fee2e2;
            border-left-color: #ef4444;
        }
        .check-title {
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .check-description {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .icon {
            font-size: 20px;
        }
        .code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .summary {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
        }
        .summary h2 {
            margin-top: 0;
            color: #333;
        }
        ul {
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        require_once __DIR__ . '/config/config.php';
        
        $checks = [];
        $all_passed = true;
        
        // Check 1: Version
        $current_version = APP_VERSION;
        $checks[] = [
            'title' => 'Application Version',
            'status' => ($current_version === '1.1.0') ? 'success' : 'warning',
            'message' => "Current version: <span class='code'>$current_version</span>",
            'description' => $current_version === '1.1.0' ? 'You are on the latest version' : 'Expected version 1.1.0'
        ];
        if ($current_version !== '1.1.0') $all_passed = false;
        
        // Check 2: Database Column
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("DESCRIBE users");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $has_google_id = in_array('google_id', $columns);
            
            $checks[] = [
                'title' => 'Database Schema - google_id Column',
                'status' => $has_google_id ? 'success' : 'error',
                'message' => $has_google_id ? 'Column exists in users table' : 'Column is missing!',
                'description' => $has_google_id ? 'Google OAuth support is enabled' : 'Run: mysql -u root evento_db < add_google_id_column.sql'
            ];
            if (!$has_google_id) $all_passed = false;
        } catch (Exception $e) {
            $checks[] = [
                'title' => 'Database Connection',
                'status' => 'error',
                'message' => 'Database connection failed',
                'description' => $e->getMessage()
            ];
            $all_passed = false;
        }
        
        // Check 3: Public Landing Page
        $index_content = file_get_contents(__DIR__ . '/index.php');
        $has_public_page = strpos($index_content, 'Public landing page') !== false;
        
        $checks[] = [
            'title' => 'Public Landing Page (v1.1.0 Feature)',
            'status' => $has_public_page ? 'success' : 'warning',
            'message' => $has_public_page ? 'Public event showcase enabled' : 'Old login-redirect version',
            'description' => $has_public_page ? 'Users can browse events without logging in' : 'Update index.php to latest version'
        ];
        if (!$has_public_page) $all_passed = false;
        
        // Check 4: Auto-Registration Flow
        $login_content = file_get_contents(__DIR__ . '/login.php');
        $has_auto_register = strpos($login_content, 'auto_register_event') !== false;
        
        $checks[] = [
            'title' => 'Auto-Registration Flow (v1.1.0 Feature)',
            'status' => $has_auto_register ? 'success' : 'warning',
            'message' => $has_auto_register ? 'Smart event registration flow active' : 'Not implemented',
            'description' => $has_auto_register ? 'Seamless transition from browsing to registration' : 'Update login.php for auto-registration support'
        ];
        if (!$has_auto_register) $all_passed = false;
        
        // Check 5: Leader Search Feature
        $clubs_content = file_get_contents(__DIR__ . '/admin/clubs.php');
        $has_search = strpos($clubs_content, 'filterLeaders') !== false;
        
        $checks[] = [
            'title' => 'Leader Search Feature (v1.1.0 Feature)',
            'status' => $has_search ? 'success' : 'warning',
            'message' => $has_search ? 'Real-time leader search enabled' : 'Not implemented',
            'description' => $has_search ? 'Fast search in club leader assignment' : 'Update admin/clubs.php for search functionality'
        ];
        if (!$has_search) $all_passed = false;
        
        // Check 6: PHP 8.x Compatibility
        $users_content = file_get_contents(__DIR__ . '/admin/users.php');
        $has_null_coalescing = strpos($users_content, "?? 'No Name'") !== false;
        
        $checks[] = [
            'title' => 'PHP 8.x Compatibility Fixes',
            'status' => $has_null_coalescing ? 'success' : 'warning',
            'message' => $has_null_coalescing ? 'Null coalescing operators applied' : 'Deprecation warnings may occur',
            'description' => $has_null_coalescing ? 'No htmlspecialchars() warnings' : 'Update admin files with ?? operators'
        ];
        
        // Check 7: Config File
        $checks[] = [
            'title' => 'Configuration File',
            'status' => 'success',
            'message' => "BASE_URL: <span class='code'>" . BASE_URL . "</span>",
            'description' => 'Application URL is configured'
        ];
        
        // Check 8: File Structure
        $required_files = [
            'index.php' => 'Public landing page',
            'login.php' => 'Login page',
            'register.php' => 'Registration page',
            'admin/dashboard.php' => 'Admin dashboard',
            'student/dashboard.php' => 'Student dashboard',
            'faculty/dashboard.php' => 'Faculty dashboard',
            'club-leader/dashboard.php' => 'Club leader dashboard'
        ];
        
        $missing_files = [];
        foreach ($required_files as $file => $desc) {
            if (!file_exists(__DIR__ . '/' . $file)) {
                $missing_files[] = "$file ($desc)";
            }
        }
        
        $checks[] = [
            'title' => 'File Structure Integrity',
            'status' => empty($missing_files) ? 'success' : 'error',
            'message' => empty($missing_files) ? 'All core files present' : count($missing_files) . ' files missing',
            'description' => empty($missing_files) ? 'Complete application structure' : implode(', ', $missing_files)
        ];
        if (!empty($missing_files)) $all_passed = false;
        
        ?>
        
        <h1>🔄 Evento Sync Status</h1>
        <p class="version">Version Check Report - <?php echo date('F d, Y H:i:s'); ?></p>
        
        <?php foreach ($checks as $check): ?>
        <div class="check-item <?php echo $check['status']; ?>">
            <div class="check-title">
                <span class="icon">
                    <?php 
                    if ($check['status'] === 'success') echo '✅';
                    elseif ($check['status'] === 'warning') echo '⚠️';
                    else echo '❌';
                    ?>
                </span>
                <?php echo $check['title']; ?>
            </div>
            <div><?php echo $check['message']; ?></div>
            <?php if (!empty($check['description'])): ?>
            <div class="check-description"><?php echo $check['description']; ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <div class="summary">
            <h2><?php echo $all_passed ? '✅ System is Fully Synced!' : '⚠️ Action Required'; ?></h2>
            
            <?php if ($all_passed): ?>
            <p><strong>Congratulations!</strong> Your Evento installation is up to date with version 1.1.0.</p>
            <ul>
                <li>✅ Public landing page with event showcase</li>
                <li>✅ Smart auto-registration flow</li>
                <li>✅ Real-time leader search</li>
                <li>✅ Google OAuth database support</li>
                <li>✅ PHP 8.x compatibility</li>
            </ul>
            <?php else: ?>
            <p><strong>Some components need attention.</strong> Please review the warnings and errors above.</p>
            <p>Common fixes:</p>
            <ul>
                <li>Update version in <span class="code">config/config.php</span></li>
                <li>Run database migration if google_id column is missing</li>
                <li>Update core files to latest versions from VERSION_HISTORY.md</li>
            </ul>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; color: #999; margin-top: 40px; font-size: 13px;">
            <p>Evento College Event Management System • <?php echo APP_VERSION; ?></p>
            <p>For detailed changelog, see <span class="code">VERSION_HISTORY.md</span></p>
        </div>
    </div>
</body>
</html>
