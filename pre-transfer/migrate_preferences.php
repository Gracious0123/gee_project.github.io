<?php
require_once 'database.php';

// 1. Create the new normalized tables if they don't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS user_preferred_districts (
    user_id INT NOT NULL,
    district VARCHAR(100) NOT NULL,
    PRIMARY KEY (user_id, district),
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_preferred_schools (
    user_id INT NOT NULL,
    school_id INT NOT NULL,
    PRIMARY KEY (user_id, school_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (school_id) REFERENCES schools(id)
)");

// 2. Migrate data from users table
$stmt = $pdo->query("SELECT id, preferred_districts, preferred_schools FROM users");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    $user_id = $user['id'];

    // Migrate preferred districts
    if (!empty($user['preferred_districts'])) {
        $districts = array_map('trim', explode(',', $user['preferred_districts']));
        foreach ($districts as $district) {
            if ($district !== '') {
                $insert = $pdo->prepare("INSERT IGNORE INTO user_preferred_districts (user_id, district) VALUES (?, ?)");
                $insert->execute([$user_id, $district]);
            }
        }
    }

    // Migrate preferred schools
    if (!empty($user['preferred_schools'])) {
        $schools = array_map('trim', explode(',', $user['preferred_schools']));
        foreach ($schools as $school_id) {
            if ($school_id !== '' && is_numeric($school_id)) {
                $insert = $pdo->prepare("INSERT IGNORE INTO user_preferred_schools (user_id, school_id) VALUES (?, ?)");
                $insert->execute([$user_id, $school_id]);
            }
        }
    }
}

// 3. Drop old columns if they exist
try {
    $pdo->exec("ALTER TABLE users DROP COLUMN preferred_districts");
    echo "Dropped column preferred_districts.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Check that column/key exists') === false) {
        echo "Error dropping preferred_districts: " . $e->getMessage() . "\n";
    }
}
try {
    $pdo->exec("ALTER TABLE users DROP COLUMN preferred_schools");
    echo "Dropped column preferred_schools.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Check that column/key exists') === false) {
        echo "Error dropping preferred_schools: " . $e->getMessage() . "\n";
    }
}

echo "Migration complete.\n"; 