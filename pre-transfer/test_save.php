<?php
require_once 'database.php';

echo "=== Testing School Save ===\n";

$user_id = 1; // Test with user ID 1
$school_name = "isenga primary";

try {
    // Test the school lookup
    echo "Looking for school: '$school_name'\n";
    $stmt = $pdo->prepare("SELECT id, name FROM schools WHERE LOWER(name) LIKE LOWER(?)");
    $stmt->execute(['%' . $school_name . '%']);
    $school = $stmt->fetch();
    
    if ($school) {
        echo "Found school: ID=" . $school['id'] . ", Name=" . $school['name'] . "\n";
        
        // Test inserting into user_preferred_schools
        echo "Inserting into user_preferred_schools...\n";
        $insert = $pdo->prepare("INSERT INTO user_preferred_schools (user_id, school_id) VALUES (?, ?)");
        $result = $insert->execute([$user_id, $school['id']]);
        
        if ($result) {
            echo "Successfully inserted school preference!\n";
        } else {
            echo "Failed to insert school preference.\n";
        }
    } else {
        echo "School not found in database.\n";
        
        // Show all schools for comparison
        echo "\nAvailable schools:\n";
        $stmt = $pdo->query("SELECT id, name FROM schools ORDER BY name");
        $schools = $stmt->fetchAll();
        foreach ($schools as $s) {
            echo "ID: " . $s['id'] . " - Name: " . $s['name'] . "\n";
        }
    }
    
    // Check current user preferences
    echo "\nCurrent user preferences:\n";
    $stmt = $pdo->prepare("SELECT ups.user_id, s.name as school_name FROM user_preferred_schools ups JOIN schools s ON ups.school_id = s.id WHERE ups.user_id = ?");
    $stmt->execute([$user_id]);
    $user_schools = $stmt->fetchAll();
    
    if (empty($user_schools)) {
        echo "No preferred schools found for user $user_id.\n";
    } else {
        foreach ($user_schools as $school) {
            echo "User ID: " . $school['user_id'] . " - School: " . $school['school_name'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "=== End Test ===\n";
?> 