<?php
require_once 'database.php';

echo "=== Schools Check ===\n";

try {
    // Check if "isenga primary" exists
    $stmt = $pdo->prepare("SELECT * FROM schools WHERE name LIKE ?");
    $stmt->execute(['%isenga%']);
    $isenga_schools = $stmt->fetchAll();
    
    echo "Schools containing 'isenga':\n";
    if (empty($isenga_schools)) {
        echo "No schools found with 'isenga' in the name.\n";
    } else {
        foreach ($isenga_schools as $school) {
            echo "ID: " . $school['id'] . " - Name: " . $school['name'] . " - District: " . $school['district'] . "\n";
        }
    }
    
    echo "\nAll schools in database:\n";
    $stmt = $pdo->query("SELECT id, name, district FROM schools ORDER BY name");
    $all_schools = $stmt->fetchAll();
    
    foreach ($all_schools as $school) {
        echo "ID: " . $school['id'] . " - Name: " . $school['name'] . " - District: " . $school['district'] . "\n";
    }
    
    // Check user's preferred schools
    echo "\nUser's preferred schools:\n";
    $stmt = $pdo->prepare("SELECT ups.user_id, s.name as school_name, s.id as school_id FROM user_preferred_schools ups JOIN schools s ON ups.school_id = s.id WHERE ups.user_id = ?");
    $stmt->execute([$_SESSION['user_id'] ?? 1]);
    $user_schools = $stmt->fetchAll();
    
    if (empty($user_schools)) {
        echo "No preferred schools found for user.\n";
    } else {
        foreach ($user_schools as $school) {
            echo "User ID: " . $school['user_id'] . " - School: " . $school['school_name'] . " (ID: " . $school['school_id'] . ")\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "=== End Schools Check ===\n";
?> 