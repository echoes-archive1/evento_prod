<?php
/**
 * Complete Database Fix for Registration Issues
 * Run this file once to fix all registration problems
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Fix - Evento</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; border-radius: 5px; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; border-radius: 5px; }
        .warning { color: orange; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; margin: 10px 0; border-radius: 5px; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; }
        ul { list-style: none; padding: 0; }
        li { padding: 5px 0; }
        .button { display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 0 0; }
        .button:hover { background: #45a049; }
    </style>
</head>
<body>";

echo "<h1>🔧 Database Fix - Evento Registration</h1>";

$db = Database::getInstance()->getConnection();
$fixes_applied = [];
$errors = [];
$warnings = [];

try {
    // Fix 1: Check and add email verification columns
    echo "<h2>Checking Email Verification Columns...</h2>";
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'verification_token'");
    if ($stmt->rowCount() == 0) {
        try {
            $db->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(64) NULL AFTER email_verified");
            $db->exec("ALTER TABLE users ADD COLUMN token_expiry DATETIME NULL AFTER verification_token");
            $db->exec("ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER token_expiry");
            $db->exec("ALTER TABLE users ADD INDEX idx_verification_token (verification_token)");
            $fixes_applied[] = "✓ Added email verification columns to users table";
        } catch (Exception $e) {
            $errors[] = "Failed to add email verification columns: " . $e->getMessage();
        }
    } else {
        $fixes_applied[] = "✓ Email verification columns already exist";
    }

    // Fix 2: Check and create audit_logs table
    echo "<h2>Checking Audit Logs Table...</h2>";
    $stmt = $db->query("SHOW TABLES LIKE 'audit_logs'");
    if ($stmt->rowCount() == 0) {
        try {
            $sql = "CREATE TABLE audit_logs (
                id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(11) UNSIGNED,
                action VARCHAR(100) NOT NULL,
                table_name VARCHAR(100),
                record_id INT(11) UNSIGNED,
                old_values JSON,
                new_values JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->exec($sql);
            $fixes_applied[] = "✓ Created audit_logs table";
        } catch (Exception $e) {
            // Try without foreign key if users table doesn't exist yet
            try {
                $sql = "CREATE TABLE audit_logs (
                    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT(11) UNSIGNED,
                    action VARCHAR(100) NOT NULL,
                    table_name VARCHAR(100),
                    record_id INT(11) UNSIGNED,
                    old_values JSON,
                    new_values JSON,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_action (action),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $db->exec($sql);
                $fixes_applied[] = "✓ Created audit_logs table (without foreign keys)";
            } catch (Exception $e2) {
                $errors[] = "Failed to create audit_logs table: " . $e2->getMessage();
            }
        }
    } else {
        $fixes_applied[] = "✓ Audit_logs table already exists";
    }

    // Fix 3: Check and create roles table
    echo "<h2>Checking Roles Table...</h2>";
    $stmt = $db->query("SHOW TABLES LIKE 'roles'");
    if ($stmt->rowCount() == 0) {
        try {
            $sql = "CREATE TABLE roles (
                id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                role_name VARCHAR(50) NOT NULL UNIQUE,
                role_description TEXT,
                permissions JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_role_name (role_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->exec($sql);
            $fixes_applied[] = "✓ Created roles table";
        } catch (Exception $e) {
            $errors[] = "Failed to create roles table: " . $e->getMessage();
        }
    } else {
        $fixes_applied[] = "✓ Roles table already exists";
    }

    // Fix 4: Insert default roles if empty
    echo "<h2>Checking Default Roles...</h2>";
    $stmt = $db->query("SELECT COUNT(*) FROM roles");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        try {
            $roles = [
                ['admin', 'System Administrator', '["all"]'],
                ['student', 'Student', '["view_events", "register_events", "view_profile"]'],
                ['faculty', 'Faculty Member', '["create_events", "view_events", "manage_registrations"]'],
                ['club_leader', 'Club Leader', '["manage_club", "create_events", "view_members"]']
            ];
            
            $sql = "INSERT INTO roles (role_name, role_description, permissions) VALUES (?, ?, ?)";
            $stmt = $db->prepare($sql);
            foreach ($roles as $role) {
                $stmt->execute($role);
            }
            $fixes_applied[] = "✓ Inserted default roles (admin, student, faculty, club_leader)";
        } catch (Exception $e) {
            $errors[] = "Failed to insert roles: " . $e->getMessage();
        }
    } else {
        $fixes_applied[] = "✓ Roles already exist ($count roles found)";
    }

    // Fix 5: Check and create user_roles table
    echo "<h2>Checking User Roles Table...</h2>";
    $stmt = $db->query("SHOW TABLES LIKE 'user_roles'");
    if ($stmt->rowCount() == 0) {
        try {
            $sql = "CREATE TABLE user_roles (
                id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(11) UNSIGNED NOT NULL,
                role_id INT(11) UNSIGNED NOT NULL,
                assigned_by INT(11) UNSIGNED,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_user_role (user_id, role_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_role_id (role_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->exec($sql);
            $fixes_applied[] = "✓ Created user_roles table";
        } catch (Exception $e) {
            $errors[] = "Failed to create user_roles table: " . $e->getMessage();
        }
    } else {
        $fixes_applied[] = "✓ User_roles table already exists";
    }

    // Fix 6: Verify student role exists
    echo "<h2>Verifying Student Role...</h2>";
    $stmt = $db->query("SELECT id FROM roles WHERE role_name = 'student'");
    if ($stmt->rowCount() == 0) {
        $warnings[] = "⚠ Student role not found! Registration will fail. Please ensure the roles table has the 'student' role.";
    } else {
        $fixes_applied[] = "✓ Student role verified";
    }

} catch (Exception $e) {
    $errors[] = "Critical error: " . $e->getMessage();
}

// Display results
echo "<h2>📊 Results</h2>";

if (!empty($fixes_applied)) {
    echo "<div class='success'><strong>Fixes Applied:</strong><ul>";
    foreach ($fixes_applied as $fix) {
        echo "<li>$fix</li>";
    }
    echo "</ul></div>";
}

if (!empty($warnings)) {
    echo "<div class='warning'><strong>Warnings:</strong><ul>";
    foreach ($warnings as $warning) {
        echo "<li>$warning</li>";
    }
    echo "</ul></div>";
}

if (!empty($errors)) {
    echo "<div class='error'><strong>Errors:</strong><ul>";
    foreach ($errors as $error) {
        echo "<li>✗ $error</li>";
    }
    echo "</ul></div>";
}

if (empty($errors) && empty($warnings)) {
    echo "<div class='success'><h2>✅ All Systems Ready!</h2>
    <p>Registration should now work properly.</p></div>";
    echo "<a href='register.php' class='button'>Go to Registration</a>";
    echo "<a href='login.php' class='button'>Go to Login</a>";
} else {
    echo "<div class='warning'><h2>⚠ Action Required</h2>
    <p>Please fix the errors/warnings above before attempting registration.</p></div>";
}

echo "</body></html>";
?>
