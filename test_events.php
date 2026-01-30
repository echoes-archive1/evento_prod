<?php
require_once __DIR__ . '/config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if events exist
    $stmt = $db->query('SELECT id, event_name, status, created_by, club_id FROM events LIMIT 5');
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Events in database:\n";
    echo json_encode($events, JSON_PRETTY_PRINT);
    echo "\n\n";
    
    // Check if clubs table has required columns
    $stmt = $db->query('DESCRIBE clubs');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Clubs table columns:\n";
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString();
}
