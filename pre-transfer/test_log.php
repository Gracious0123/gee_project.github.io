<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set the error log path
$logPath = __DIR__ . '/php_errors.log';
ini_set('error_log', $logPath);

// Test error logging
error_log("Test error message - " . date('Y-m-d H:i:s'));

// Test database connection
try {
    require_once 'database.php';
    error_log("Database connection successful");
    
    // Test user data
    $stmt = $pdo->prepare("SELECT u.*, s.name as school_name FROM users u LEFT JOIN schools s ON u.school_id = s.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id'] ?? 1]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("User data: " . print_r($user, true));
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}

echo "Check the log file at: " . $logPath;
?> 