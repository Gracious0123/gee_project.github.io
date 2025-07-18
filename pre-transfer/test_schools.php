<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

require_once 'database.php';

try {
    // Get table structure
    $stmt = $pdo->query("DESCRIBE schools");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Schools table structure: " . print_r($columns, true));
    
    // Get sample school data
    $stmt = $pdo->query("SELECT * FROM schools LIMIT 1");
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Sample school data: " . print_r($school, true));
    
    echo "Check the log file for results";
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}
?> 