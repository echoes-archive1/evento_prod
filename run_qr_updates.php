<?php
/**
 * QR System Database Migration Script
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Starting QR Code System Enhancement database updates...\n\n";
    
    // Read and execute SQL file
    $sql_content = file_get_contents(__DIR__ . '/database_qr_system_updates.sql');
    
    // Split by semicolons and filter out comments/empty statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($stmt) {
            return !empty($stmt) && 
                   !preg_match('/^\s*--/', $stmt) && 
                   !preg_match('/^\s*DELIMITER/i', $stmt) &&
                   !preg_match('/^\s*SELECT.*message/i', $stmt);
        }
    );
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        try {
            $db->exec($statement);
            $success_count++;
            $preview = substr(trim($statement), 0, 60) . (strlen($statement) > 60 ? '...' : '');
            echo "✅ Executed: $preview\n";
        } catch (Exception $e) {
            $error_count++;
            $preview = substr(trim($statement), 0, 60) . (strlen($statement) > 60 ? '...' : '');
            echo "❌ Error in: $preview\n";
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== QR System Database Update Summary ===\n";
    echo "✅ Successful operations: $success_count\n";
    echo "❌ Errors: $error_count\n";
    
    if ($error_count === 0) {
        echo "\n🎉 All database updates completed successfully!\n";
        echo "\nQR Code System Enhancement features are now ready:\n";
        echo "- Enhanced QR code generation with event tickets\n";
        echo "- Real-time attendance tracking API\n";
        echo "- Mobile-responsive QR scanner interface\n";
        echo "- Comprehensive attendance dashboard\n";
        echo "- Export attendance reports (CSV/JSON)\n";
        echo "- Audit logs and scan history tracking\n";
        echo "- Bulk attendance operations\n";
    } else {
        echo "\n⚠️  Some errors occurred. Please check the database manually.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>