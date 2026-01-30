<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

Auth::requireAuth();

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id, full_name, email, roll_number, department, institute, year, current_semester, auto_extracted FROM users WHERE id = :id");
$stmt->execute(['id' => Auth::id()]);
$user = $stmt->fetch();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile Debug - Evento</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .debug-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h2 { color: #333; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; font-weight: bold; }
        .empty { color: #dc3545; font-weight: bold; }
        .filled { color: #28a745; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
        .btn:hover { background: #5568d3; }
    </style>
</head>
<body>
    <div class="debug-box">
        <h2>🔍 Profile Debug Information</h2>
        <p><strong>User ID:</strong> <?php echo htmlspecialchars($user['id']); ?></p>
        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
        
        <h3>Current Database Values:</h3>
        <table>
            <tr>
                <th>Field</th>
                <th>Value</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>roll_number</td>
                <td><?php echo htmlspecialchars($user['roll_number'] ?? '(null)'); ?></td>
                <td class="<?php echo !empty($user['roll_number']) ? 'filled' : 'empty'; ?>">
                    <?php echo !empty($user['roll_number']) ? '✓ HAS VALUE' : '✗ EMPTY'; ?>
                </td>
            </tr>
            <tr>
                <td>department</td>
                <td><?php echo htmlspecialchars($user['department'] ?? '(null)'); ?></td>
                <td class="<?php echo !empty($user['department']) ? 'filled' : 'empty'; ?>">
                    <?php echo !empty($user['department']) ? '✓ HAS VALUE' : '✗ EMPTY'; ?>
                </td>
            </tr>
            <tr>
                <td>institute</td>
                <td><?php echo htmlspecialchars($user['institute'] ?? '(null)'); ?></td>
                <td class="<?php echo !empty($user['institute']) ? 'filled' : 'empty'; ?>">
                    <?php echo !empty($user['institute']) ? '✓ HAS VALUE' : '✗ EMPTY'; ?>
                </td>
            </tr>
            <tr>
                <td>year</td>
                <td><?php echo htmlspecialchars($user['year'] ?? '(null)'); ?></td>
                <td class="<?php echo !empty($user['year']) ? 'filled' : 'empty'; ?>">
                    <?php echo !empty($user['year']) ? '✓ HAS VALUE' : '✗ EMPTY'; ?>
                </td>
            </tr>
            <tr>
                <td>current_semester</td>
                <td><?php echo htmlspecialchars($user['current_semester'] ?? '(null)'); ?></td>
                <td class="<?php echo !empty($user['current_semester']) ? 'filled' : 'empty'; ?>">
                    <?php echo !empty($user['current_semester']) ? '✓ HAS VALUE' : '✗ EMPTY'; ?>
                </td>
            </tr>
            <tr>
                <td>auto_extracted</td>
                <td><?php echo $user['auto_extracted'] ? 'Yes (1)' : 'No (0)'; ?></td>
                <td><?php echo $user['auto_extracted'] ? 'Auto-filled from email' : 'Manual entry'; ?></td>
            </tr>
        </table>
        
        <h3>Session Data:</h3>
        <pre><?php print_r($_SESSION['user'] ?? 'No session user data'); ?></pre>
        
        <a href="student/profile.php" class="btn">← Back to Profile</a>
    </div>
</body>
</html>
