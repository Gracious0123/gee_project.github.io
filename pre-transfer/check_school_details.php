<?php
require_once 'database.php';

echo "=== School Details Check ===\n";

try {
    // Check all schools with their complete details
    $stmt = $pdo->query("SELECT * FROM schools ORDER BY name");
    $schools = $stmt->fetchAll();
    
    echo "All schools in database:\n";
    foreach ($schools as $school) {
        echo "\nSchool ID: " . $school['id'] . "\n";
        echo "Name: " . $school['name'] . "\n";
        echo "District: " . ($school['district'] ?? 'NULL') . "\n";
        echo "Region: " . ($school['region'] ?? 'NULL') . "\n";
        echo "Address: " . ($school['address'] ?? 'NULL') . "\n";
        echo "Contact: " . ($school['contact_number'] ?? 'NULL') . "\n";
        echo "Email: " . ($school['email'] ?? 'NULL') . "\n";
        echo "Principal: " . ($school['principal_name'] ?? 'NULL') . "\n";
        echo "Status: " . $school['status'] . "\n";
        echo "---\n";
    }
    
    // Check user's current school assignment
    echo "\nUser school assignments:\n";
    $stmt = $pdo->query("SELECT u.id, u.first_name, u.last_name, u.school_id, s.name as school_name FROM users u LEFT JOIN schools s ON u.school_id = s.id ORDER BY u.id");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        echo "User ID: " . $user['id'] . " - " . $user['first_name'] . " " . $user['last_name'] . "\n";
        echo "School ID: " . ($user['school_id'] ?? 'NULL') . "\n";
        echo "School Name: " . ($user['school_name'] ?? 'No school assigned') . "\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "=== End School Details Check ===\n";
?> 