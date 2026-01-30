<?php
/**
 * Fix Registration Issues
 * This script will fix common registration problems
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<h1>Registration Fix Script</h1>";

$db = Database::getInstance()->getConnection();
$fixes_applied = [];
$errors = [];

// Fix 1: Add email verification columns if missing
try {
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'verification_token'");
    if ($stmt->rowCount() == 0) {
        echo "Adding email verification columns...<br>";
        $db->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) NULL AFTER email_verified");
        $db->exec("ALTER TABLE users ADD COLUMN token_expiry DATETIME NULL AFTER verification_token");
        $db->exec("ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL AFTER token_expiry");
        $fixes_applied[] = "✓ Added email verification columns (verification_token, token_expiry, email_verified_at)";
    } else {
        $fixes_applied[] = "✓ Email verification columns already exist";
    }
} catch (Exception $e) {
    $errors[] = "✗ Error adding email verification columns: " . $e->getMessage();
}

// Fix 2: Ensure audit_logs table exists
try {
    $stmt = $db->query("SHOW TABLES LIKE 'audit_logs'");
    if ($stmt->rowCount() == 0) {
        echo "Creating audit_logs table...<br>";
        $sql = "CREATE TABLE `audit_logs` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) UNSIGNED,
            `action` VARCHAR(100) NOT NULL,
            `table_name` VARCHAR(100),
            `record_id` INT(11) UNSIGNED,
            `old_values` JSON,
            `new_values` JSON,
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_action` (`action`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $db->exec($sql);
        $fixes_applied[] = "✓ Created audit_logs table";
    } else {
        $fixes_applied[] = "✓ Audit_logs table already exists";
    }
} catch (Exception $e) {
    $errors[] = "✗ Error creating audit_logs table: " . $e->getMessage();
}

// Fix 3: Ensure roles table exists and has data
try {
    $stmt = $db->query("SHOW TABLES LIKE 'roles'");
    if ($stmt->rowCount() == 0) {
        echo "Creating roles table...<br>";
        $sql = "CREATE TABLE `roles` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `role_name` VARCHAR(50) NOT NULL UNIQUE,
            `role_description` TEXT,
            `permissions` JSON,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_role_name` (`role_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $db->exec($sql);
        $fixes_applied[] = "✓ Created roles table";
    } else {
        $fixes_applied[] = "✓ Roles table already exists";
    }
    
    // Check if roles have data
    $stmt = $db->query("SELECT COUNT(*) FROM roles");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "Inserting default roles...<br>";
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
    } else {
        $fixes_applied[] = "✓ Roles already exist in database";
    }
} catch (Exception $e) {
    $errors[] = "✗ Error setting up roles: " . $e->getMessage();
}

// Fix 4: Ensure user_roles table exists
try {
    $stmt = $db->query("SHOW TABLES LIKE 'user_roles'");
    if ($stmt->rowCount() == 0) {
        echo "Creating user_roles table...<br>";
        $sql = "CREATE TABLE `user_roles` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) UNSIGNED NOT NULL,
            `role_id` INT(11) UNSIGNED NOT NULL,
            `assigned_by` INT(11) UNSIGNED,
            `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_user_role` (`user_id`, `role_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_role_id` (`role_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $db->exec($sql);
        $fixes_applied[] = "✓ Created user_roles table";
    } else {
        $fixes_applied[] = "✓ User_roles table already exists";
    }
} catch (Exception $e) {
    $errors[] = "✗ Error creating user_roles table: " . $e->getMessage();
}

// Display results
echo "<h2>Fixes Applied</h2>";
if (!empty($fixes_applied)) {
    echo "<ul>";
    foreach ($fixes_applied as $fix) {
        echo "<li>$fix</li>";
    }
    echo "</ul>";
}

if (!empty($errors)) {
    echo "<h2 style='color: red;'>Errors</h2>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li style='color: red;'>$error</li>";
    }
    echo "</ul>";
}

if (empty($errors)) {
    echo "<h2 style='color: green;'>✓ All fixes applied successfully!</h2>";
    echo "<p>Registration should now work properly. <a href='register.php'>Try registering</a></p>";
} else {
    echo "<h2 style='color: orange;'>⚠ Some fixes could not be applied</h2>";
    echo "<p>Please check the errors above and fix them manually.</p>";
}
?>
