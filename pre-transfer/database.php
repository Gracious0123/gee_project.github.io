<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Database connection configuration
$host = 'localhost';
$db = 'teacher_match';
$user = 'root';
$password = '';
$charset = 'utf8mb4';

error_log("=== Database Connection Start ===");
error_log("Attempting to connect to database: $db on host: $host");

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // First try to connect to MySQL without selecting a database
    error_log("Attempting initial connection to MySQL...");
    $pdo = new PDO("mysql:host=$host", $user, $password, $options);
    error_log("Initial connection successful");
    
    // Check if database exists, if not create it
    error_log("Checking if database exists...");
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db'");
    if ($stmt->rowCount() == 0) {
        error_log("Database does not exist, creating it...");
        $pdo->exec("CREATE DATABASE `$db` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci");
        error_log("Database created successfully");
    } else {
        error_log("Database already exists");
    }
    
    // Now connect to the specific database
    error_log("Connecting to specific database...");
    $pdo = new PDO("$dsn;dbname=$db", $user, $password, $options);
    error_log("Connected to database successfully");
    
    // Check if tables exist, if not create them
    error_log("Checking and creating tables if needed...");
    $tables = [
        'schools' => "CREATE TABLE IF NOT EXISTS schools (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            address TEXT NOT NULL,
            district VARCHAR(100) NOT NULL,
            region VARCHAR(100) NOT NULL,
            contact_number VARCHAR(20),
            email VARCHAR(100),
            principal_name VARCHAR(100),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('teacher', 'admin', 'school_admin') NOT NULL,
            school_id INT,
            preferred_districts TEXT,
            preferred_schools TEXT,
            preferred_subject VARCHAR(100),
            preferred_grade_level VARCHAR(50),
            status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id)
        )",
        
        'messages' => "CREATE TABLE IF NOT EXISTS messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            sender_id INT NOT NULL,
            recipient_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id),
            FOREIGN KEY (recipient_id) REFERENCES users(id)
        )",
        
        'transfer_requests' => "CREATE TABLE IF NOT EXISTS transfer_requests (
            id INT PRIMARY KEY AUTO_INCREMENT,
            requester_id INT NOT NULL,
            target_id INT NOT NULL,
            requester_school_id INT NOT NULL,
            target_school_id INT NOT NULL,
            status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (requester_id) REFERENCES users(id),
            FOREIGN KEY (target_id) REFERENCES users(id),
            FOREIGN KEY (requester_school_id) REFERENCES schools(id),
            FOREIGN KEY (target_school_id) REFERENCES schools(id)
        )",

        'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            type ENUM('request', 'message', 'request_accepted', 'request_declined', 'system') NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255),
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )"
    ];
    
    foreach ($tables as $table => $sql) {
        $pdo->exec($sql);
    }
    
} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}
?>
