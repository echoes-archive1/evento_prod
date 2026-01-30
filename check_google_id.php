<?php
require_once __DIR__ . '/config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if google_id column exists
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Database Column Check</h2>";
    echo "<h3>Users Table Columns:</h3>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    if (in_array('google_id', $columns)) {
        echo "<p style='color: green;'>✅ google_id column EXISTS</p>";
    } else {
        echo "<p style='color: red;'>❌ google_id column MISSING</p>";
        echo "<p>Run: <code>mysql -u root -p u149605981_evento < add_google_id_column.sql</code></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
