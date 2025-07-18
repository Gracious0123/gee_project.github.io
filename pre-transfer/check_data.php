<?php
require_once 'database.php';

echo "=== Data Migration Check ===\n";

try {
    // Check data in new tables
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_preferred_districts");
    $districts_count = $stmt->fetchColumn();
    echo "Records in user_preferred_districts: " . $districts_count . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_preferred_schools");
    $schools_count = $stmt->fetchColumn();
    echo "Records in user_preferred_schools: " . $schools_count . "\n";
    
    // Show sample data from new tables
    if ($districts_count > 0) {
        echo "\nSample preferred districts:\n";
        $stmt = $pdo->query("SELECT user_id, district FROM user_preferred_districts LIMIT 5");
        $districts = $stmt->fetchAll();
        foreach ($districts as $district) {
            echo "User ID: " . $district['user_id'] . " - District: " . $district['district'] . "\n";
        }
    }
    
    if ($schools_count > 0) {
        echo "\nSample preferred schools:\n";
        $stmt = $pdo->query("SELECT ups.user_id, s.name as school_name FROM user_preferred_schools ups JOIN schools s ON ups.school_id = s.id LIMIT 5");
        $schools = $stmt->fetchAll();
        foreach ($schools as $school) {
            echo "User ID: " . $school['user_id'] . " - School: " . $school['school_name'] . "\n";
        }
    }
    
    // Check total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt->fetchColumn();
    echo "\nTotal users in database: " . $total_users . "\n";
    
    // Check users with preferences
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_preferred_districts");
    $users_with_districts = $stmt->fetchColumn();
    echo "Users with preferred districts: " . $users_with_districts . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_preferred_schools");
    $users_with_schools = $stmt->fetchColumn();
    echo "Users with preferred schools: " . $users_with_schools . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "=== End Data Check ===\n";
?> 