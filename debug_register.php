<?php
// Debug registration issue
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

// Test 1: Database Connection
try {
    require_once __DIR__ . '/config/database.php';
    $db = Database::getInstance()->getConnection();
    echo "✓ Database connection successful<br>";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
    die();
}

// Test 2: Check if users table exists
try {
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Users table exists<br>";
    } else {
        echo "✗ Users table does not exist<br>";
        die();
    }
} catch (Exception $e) {
    echo "✗ Error checking users table: " . $e->getMessage() . "<br>";
    die();
}

// Test 3: Check users table structure
try {
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Users table columns: " . implode(", ", $columns) . "<br>";
    
    // Check for required columns
    $required = ['id', 'full_name', 'roll_number', 'email', 'department', 'year', 'phone', 'password_hash'];
    $missing = array_diff($required, $columns);
    if (!empty($missing)) {
        echo "✗ Missing columns: " . implode(", ", $missing) . "<br>";
    } else {
        echo "✓ All required columns exist<br>";
    }
} catch (Exception $e) {
    echo "✗ Error checking table structure: " . $e->getMessage() . "<br>";
    die();
}

// Test 4: Check if roles table exists and has data
try {
    $stmt = $db->query("SELECT COUNT(*) FROM roles WHERE role_name = 'student'");
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        echo "✓ Student role exists<br>";
    } else {
        echo "✗ Student role does not exist - this will cause registration to fail!<br>";
        echo "<strong>Solution: Run the database/sample_data.sql file to insert roles</strong><br>";
    }
} catch (Exception $e) {
    echo "✗ Error checking roles table: " . $e->getMessage() . "<br>";
}

// Test 5: Check if audit_logs table exists
try {
    $stmt = $db->query("SHOW TABLES LIKE 'audit_logs'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Audit_logs table exists<br>";
    } else {
        echo "✗ Audit_logs table does not exist - this might cause registration to fail!<br>";
    }
} catch (Exception $e) {
    echo "✗ Error checking audit_logs table: " . $e->getMessage() . "<br>";
}

// Test 6: Check email verification columns
try {
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('verification_token', $columns) && in_array('token_expiry', $columns)) {
        echo "✓ Email verification columns exist<br>";
    } else {
        echo "✗ Email verification columns missing - this will cause registration to fail!<br>";
        echo "<strong>Solution: Run the database/add_email_verification.sql file</strong><br>";
    }
} catch (Exception $e) {
    echo "✗ Error checking email verification columns: " . $e->getMessage() . "<br>";
}

// Test 7: Try a simple test registration
echo "<h2>Test Registration Simulation</h2>";
try {
    require_once __DIR__ . '/app/helpers/Security.php';
    
    $test_data = [
        'full_name' => 'Test User',
        'roll_number' => 'TEST' . rand(1000, 9999),
        'email' => 'test' . rand(1000, 9999) . '@test.com',
        'department' => 'Computer Science',
        'year' => '1',
        'phone' => '9' . rand(100000000, 999999999),
        'password_hash' => Security::hashPassword('Test@123'),
        'verification_token' => bin2hex(random_bytes(32)),
        'token_expiry' => date('Y-m-d H:i:s', strtotime('+24 hours'))
    ];
    
    $sql = "INSERT INTO users (full_name, roll_number, email, department, year, phone, password_hash, verification_token, token_expiry) 
            VALUES (:full_name, :roll_number, :email, :department, :year, :phone, :password_hash, :verification_token, :token_expiry)";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($test_data);
    
    if ($result) {
        $user_id = $db->lastInsertId();
        echo "✓ Test user created with ID: $user_id<br>";
        
        // Clean up test user
        $db->exec("DELETE FROM users WHERE id = $user_id");
        echo "✓ Test user cleaned up<br>";
    }
} catch (Exception $e) {
    echo "✗ Test registration failed: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Summary</h2>";
echo "If all tests pass, registration should work. If any tests fail, follow the suggested solutions above.";
?>
