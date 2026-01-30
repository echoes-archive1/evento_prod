<?php
/**
 * Database Migration Script for Railway
 * Run this once after deployment to set up the database structure
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Connected to database successfully!\n";
    echo "Database: " . DB_NAME . "\n";
    echo "Host: " . DB_HOST . "\n";
    
    // Read and execute the main SQL file
    $sql_file = __DIR__ . '/u149605981_evento.sql';
    
    if (file_exists($sql_file)) {
        echo "Reading SQL file...\n";
        $sql = file_get_contents($sql_file);
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $db->exec($statement);
                    $success_count++;
                } catch (PDOException $e) {
                    $error_count++;
                    echo "Error executing statement: " . $e->getMessage() . "\n";
                    echo "Statement: " . substr($statement, 0, 100) . "...\n";
                }
            }
        }
        
        echo "Migration completed!\n";
        echo "Successful statements: $success_count\n";
        echo "Errors: $error_count\n";
        
    } else {
        echo "SQL file not found: $sql_file\n";
        echo "Please upload your database dump file.\n";
    }
    
    // Check if tables were created
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nTables in database:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>