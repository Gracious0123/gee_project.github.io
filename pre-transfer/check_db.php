<?php
require_once 'database.php';

echo "=== Database State Check ===\n";

try {
    // Check if new tables exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_preferred_districts'");
    $districts_table_exists = $stmt->rowCount() > 0;
    echo "user_preferred_districts table exists: " . ($districts_table_exists ? "YES" : "NO") . "\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_preferred_schools'");
    $schools_table_exists = $stmt->rowCount() > 0;
    echo "user_preferred_schools table exists: " . ($schools_table_exists ? "YES" : "NO") . "\n";
    
    // Check users table structure
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Users table columns: " . implode(', ', $columns) . "\n";
    
    // Check if old columns still exist
    $has_old_districts = in_array('preferred_districts', $columns);
    $has_old_schools = in_array('preferred_schools', $columns);
    echo "Old preferred_districts column exists: " . ($has_old_districts ? "YES" : "NO") . "\n";
    echo "Old preferred_schools column exists: " . ($has_old_schools ? "YES" : "NO") . "\n";
    
    // Check data in new tables
    if ($districts_table_exists) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM user_preferred_districts");
        $districts_count = $stmt->fetchColumn();
        echo "Records in user_preferred_districts: " . $districts_count . "\n";
    }
    
    if ($schools_table_exists) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM user_preferred_schools");
        $schools_count = $stmt->fetchColumn();
        echo "Records in user_preferred_schools: " . $schools_count . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "=== End Check ===\n";
?> 