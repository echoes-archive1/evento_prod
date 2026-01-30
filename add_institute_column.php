<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Adding institute column to users table...\n";
    
    // Check if column already exists
    $check_sql = "SHOW COLUMNS FROM users LIKE 'institute'";
    $check_stmt = $db->query($check_sql);
    
    if ($check_stmt->rowCount() == 0) {
        $sql = "ALTER TABLE users ADD COLUMN institute VARCHAR(100) DEFAULT NULL COMMENT 'Institute (CSPIT/Depstar)'";
        $db->exec($sql);
        echo "✓ Successfully added institute column\n";
        
        // Add index for performance
        $index_sql = "CREATE INDEX idx_users_institute ON users (institute)";
        $db->exec($index_sql);
        echo "✓ Added index for institute column\n";
        
        // Update existing users with institute based on roll number
        $update_sql = "UPDATE users 
                      SET institute = CASE 
                        WHEN roll_number REGEXP '^[0-9]{2}D' THEN 'Depstar'
                        WHEN roll_number IS NOT NULL AND roll_number != '' THEN 'CSPIT'
                        ELSE NULL 
                      END
                      WHERE roll_number IS NOT NULL";
        
        $db->exec($update_sql);
        echo "✓ Updated existing users with institute information\n";
        
    } else {
        echo "✓ Institute column already exists\n";
    }
    
    echo "\nDatabase update completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>