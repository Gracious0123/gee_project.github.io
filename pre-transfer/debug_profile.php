<?php
require_once 'database.php';

echo "=== Profile Query Debug ===\n";

try {
    // First, check what user IDs exist
    $stmt = $pdo->query("SELECT id, first_name, last_name, school_id FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    echo "Available users:\n";
    foreach ($users as $u) {
        echo "ID: " . $u['id'] . " - " . $u['first_name'] . " " . $u['last_name'] . " (School ID: " . ($u['school_id'] ?? 'NULL') . ")\n";
    }
    
    // Use the first user with a school assignment, or the first user available
    $user_id = null;
    foreach ($users as $u) {
        if (!empty($u['school_id'])) {
            $user_id = $u['id'];
            break;
        }
    }
    
    if (!$user_id && !empty($users)) {
        $user_id = $users[0]['id'];
    }
    
    if (!$user_id) {
        echo "\nNo users found in database.\n";
        exit;
    }
    
    echo "\nTesting with user ID: $user_id\n";
    
    // Use the exact same query as profile.php
    $stmt = $pdo->prepare("
        SELECT u.*, s.name as school_name, s.district as school_district, s.region as school_region,
               s.address as school_address, s.contact_number as school_contact,
               s.email as school_email, s.principal_name as school_principal
        FROM users u
        LEFT JOIN schools s ON u.school_id = s.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "User not found!\n";
        exit;
    }

    echo "User data for ID $user_id:\n";
    echo "User ID: " . $user['id'] . "\n";
    echo "Name: " . $user['first_name'] . " " . $user['last_name'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "School ID: " . ($user['school_id'] ?? 'NULL') . "\n";
    echo "School Name: " . ($user['school_name'] ?? 'NULL') . "\n";
    echo "School District: " . ($user['school_district'] ?? 'NULL') . "\n";
    echo "School Region: " . ($user['school_region'] ?? 'NULL') . "\n";
    echo "School Address: " . ($user['school_address'] ?? 'NULL') . "\n";
    echo "School Contact: " . ($user['school_contact'] ?? 'NULL') . "\n";
    echo "School Email: " . ($user['school_email'] ?? 'NULL') . "\n";
    echo "School Principal: " . ($user['school_principal'] ?? 'NULL') . "\n";

    // Check if school exists
    if ($user['school_id']) {
        echo "\nChecking school details directly:\n";
        $stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$user['school_id']]);
        $school = $stmt->fetch();
        
        if ($school) {
            echo "School found:\n";
            echo "ID: " . $school['id'] . "\n";
            echo "Name: " . $school['name'] . "\n";
            echo "District: " . ($school['district'] ?? 'NULL') . "\n";
            echo "Region: " . ($school['region'] ?? 'NULL') . "\n";
            echo "Address: " . ($school['address'] ?? 'NULL') . "\n";
            echo "Contact: " . ($school['contact_number'] ?? 'NULL') . "\n";
            echo "Email: " . ($school['email'] ?? 'NULL') . "\n";
            echo "Principal: " . ($school['principal_name'] ?? 'NULL') . "\n";
        } else {
            echo "School not found for ID: " . $user['school_id'] . "\n";
        }
    } else {
        echo "\nNo school assigned to user.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "=== End Debug ===\n";
?> 