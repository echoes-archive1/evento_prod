<?php
/**
 * Database Optimization for Event Registration
 * Adds indexes to improve registration speed
 */

require_once __DIR__ . '/config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Optimizing database for faster event registration...\n\n";
    
    // Add indexes for faster registration queries
    $indexes = [
        // Index for checking existing registration (most common query)
        "CREATE INDEX IF NOT EXISTS idx_event_registrations_user_event ON event_registrations(user_id, event_id)",
        
        // Index for event lookup by status
        "CREATE INDEX IF NOT EXISTS idx_events_status ON events(status)",
        
        // Index for event registration count updates
        "CREATE INDEX IF NOT EXISTS idx_events_id_status ON events(id, status)",
        
        // Index for user lookup
        "CREATE INDEX IF NOT EXISTS idx_users_id_active ON users(id, is_active)",
        
        // Index for QR code lookup (for verification)
        "CREATE INDEX IF NOT EXISTS idx_event_registrations_qr_code ON event_registrations(qr_code)",
        
        // Index for event date filtering
        "CREATE INDEX IF NOT EXISTS idx_events_event_date ON events(event_date)",
        
        // Index for registration deadline checks
        "CREATE INDEX IF NOT EXISTS idx_events_registration_deadline ON events(registration_deadline)"
    ];
    
    $success_count = 0;
    foreach ($indexes as $index_sql) {
        try {
            $db->exec($index_sql);
            $success_count++;
            echo "✅ Added index: " . substr($index_sql, 0, 80) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "ℹ️  Index already exists: " . substr($index_sql, 0, 80) . "...\n";
            } else {
                echo "❌ Failed to add index: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n";
    echo "Database optimization completed!\n";
    echo "Added/verified $success_count indexes for faster queries.\n\n";
    
    // Test query performance
    echo "Testing registration query performance...\n";
    $start_time = microtime(true);
    
    // Simulate the most common registration queries
    $test_queries = [
        "SELECT id FROM event_registrations WHERE event_id = 1 AND user_id = 1",
        "SELECT * FROM events WHERE id = 1 AND status = 'approved'",
        "SELECT full_name, email FROM users WHERE id = 1"
    ];
    
    foreach ($test_queries as $query) {
        $query_start = microtime(true);
        $stmt = $db->query($query);
        $query_time = (microtime(true) - $query_start) * 1000;
        echo "Query time: " . number_format($query_time, 2) . "ms - " . substr($query, 0, 50) . "...\n";
    }
    
    $total_time = (microtime(true) - $start_time) * 1000;
    echo "\nTotal test time: " . number_format($total_time, 2) . "ms\n";
    echo "Registration queries should now be significantly faster!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>