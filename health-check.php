<?php
/**
 * System Health Check
 * Verify database connection and system requirements
 */

require_once __DIR__ . '/config/config.php';

// Set JSON header if requested
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
}

$checks = [
    'database' => false,
    'php_version' => false,
    'required_extensions' => [],
    'write_permissions' => [],
    'session' => false,
    'timezone' => false
];

// Check PHP Version
$checks['php_version'] = version_compare(PHP_VERSION, '7.4.0', '>=');

// Check Required PHP Extensions
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'curl', 'gd'];
foreach ($required_extensions as $ext) {
    $checks['required_extensions'][$ext] = extension_loaded($ext);
}

// Check Database Connection
try {
    $db = Database::getInstance();
    $checks['database'] = $db->testConnection();
    if ($checks['database']) {
        $checks['database_stats'] = $db->getStats();
    }
} catch (Exception $e) {
    $checks['database'] = false;
    $checks['database_error'] = $e->getMessage();
}

// Check Write Permissions
$paths_to_check = [
    'logs' => __DIR__ . '/logs',
    'uploads' => __DIR__ . '/public/uploads',
    'profiles' => __DIR__ . '/public/uploads/profiles',
    'events' => __DIR__ . '/public/uploads/events'
];

foreach ($paths_to_check as $name => $path) {
    $checks['write_permissions'][$name] = is_writable($path);
}

// Check Session
$checks['session'] = session_status() === PHP_SESSION_ACTIVE;

// Check Timezone
$checks['timezone'] = date_default_timezone_get() === 'Asia/Kolkata';

// Calculate overall health
$all_checks_passed = $checks['database'] && 
                     $checks['php_version'] && 
                     !in_array(false, $checks['required_extensions']) &&
                     !in_array(false, $checks['write_permissions']) &&
                     $checks['session'] &&
                     $checks['timezone'];

$health_status = $all_checks_passed ? 'healthy' : 'unhealthy';

// Output results
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    echo json_encode([
        'status' => $health_status,
        'timestamp' => date('Y-m-d H:i:s'),
        'checks' => $checks,
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ], JSON_PRETTY_PRINT);
    exit;
}

// HTML Output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Check - Evento</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; }
        .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 2em; margin-bottom: 10px; }
        .status { display: inline-block; padding: 8px 20px; border-radius: 20px; font-weight: bold; margin-top: 10px; }
        .status.healthy { background: #28a745; }
        .status.unhealthy { background: #dc3545; }
        .content { padding: 30px; }
        .check-group { margin-bottom: 30px; }
        .check-group h2 { color: #333; font-size: 1.3em; margin-bottom: 15px; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .check-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; margin-bottom: 8px; border-radius: 5px; }
        .check-name { font-weight: 500; color: #555; }
        .check-status { font-weight: bold; padding: 4px 12px; border-radius: 12px; font-size: 0.9em; }
        .check-status.pass { background: #d4edda; color: #155724; }
        .check-status.fail { background: #f8d7da; color: #721c24; }
        .stats { background: #e7f3ff; padding: 15px; border-radius: 8px; margin-top: 10px; }
        .stats-item { display: flex; justify-content: space-between; padding: 5px 0; }
        .footer { text-align: center; padding: 20px; color: #999; font-size: 0.9em; border-top: 1px solid #eee; }
        .refresh-btn { display: inline-block; margin-top: 20px; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; transition: all 0.3s; }
        .refresh-btn:hover { background: #5568d3; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.4); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 System Health Check</h1>
            <p>Evento - Event Management System</p>
            <span class="status <?php echo $health_status; ?>">
                <?php echo $all_checks_passed ? '✓ All Systems Operational' : '⚠ Issues Detected'; ?>
            </span>
        </div>
        
        <div class="content">
            <!-- Database Check -->
            <div class="check-group">
                <h2>🗄️ Database Connection</h2>
                <div class="check-item">
                    <span class="check-name">MySQL Connection</span>
                    <span class="check-status <?php echo $checks['database'] ? 'pass' : 'fail'; ?>">
                        <?php echo $checks['database'] ? '✓ Connected' : '✗ Failed'; ?>
                    </span>
                </div>
                <?php if ($checks['database'] && isset($checks['database_stats'])): ?>
                <div class="stats">
                    <?php foreach ($checks['database_stats'] as $key => $value): ?>
                    <div class="stats-item">
                        <span><?php echo htmlspecialchars($key); ?></span>
                        <strong><?php echo htmlspecialchars($value); ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- PHP Version -->
            <div class="check-group">
                <h2>🐘 PHP Environment</h2>
                <div class="check-item">
                    <span class="check-name">PHP Version (>= 7.4.0)</span>
                    <span class="check-status <?php echo $checks['php_version'] ? 'pass' : 'fail'; ?>">
                        <?php echo PHP_VERSION; ?>
                    </span>
                </div>
                <div class="check-item">
                    <span class="check-name">Session Status</span>
                    <span class="check-status <?php echo $checks['session'] ? 'pass' : 'fail'; ?>">
                        <?php echo $checks['session'] ? '✓ Active' : '✗ Inactive'; ?>
                    </span>
                </div>
                <div class="check-item">
                    <span class="check-name">Timezone (Asia/Kolkata)</span>
                    <span class="check-status <?php echo $checks['timezone'] ? 'pass' : 'fail'; ?>">
                        <?php echo date_default_timezone_get(); ?>
                    </span>
                </div>
            </div>
            
            <!-- PHP Extensions -->
            <div class="check-group">
                <h2>📦 PHP Extensions</h2>
                <?php foreach ($checks['required_extensions'] as $ext => $loaded): ?>
                <div class="check-item">
                    <span class="check-name"><?php echo htmlspecialchars($ext); ?></span>
                    <span class="check-status <?php echo $loaded ? 'pass' : 'fail'; ?>">
                        <?php echo $loaded ? '✓ Loaded' : '✗ Missing'; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- File Permissions -->
            <div class="check-group">
                <h2>📁 File Permissions</h2>
                <?php foreach ($checks['write_permissions'] as $name => $writable): ?>
                <div class="check-item">
                    <span class="check-name"><?php echo htmlspecialchars($name); ?> directory</span>
                    <span class="check-status <?php echo $writable ? 'pass' : 'fail'; ?>">
                        <?php echo $writable ? '✓ Writable' : '✗ Not Writable'; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center;">
                <a href="health-check.php" class="refresh-btn">🔄 Refresh Status</a>
                <a href="index.php" class="refresh-btn" style="background: #28a745;">🏠 Go to Homepage</a>
            </div>
        </div>
        
        <div class="footer">
            <p>Last checked: <?php echo date('F j, Y, g:i:s A'); ?></p>
            <p>Server: <?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></p>
        </div>
    </div>
</body>
</html>
