<?php
/**
 * Database Configuration
 * i-Klinik Politeknik Besut
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'eklinik');

// Create connection with error handling
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

        if ($conn->connect_error) {
            throw new Exception("MySQL connection failed: " . $conn->connect_error);
        }

        // Select database
        if (!$conn->select_db(DB_NAME)) {
            throw new Exception("Database '" . DB_NAME . "' not found. Please import the SQL file first.");
        }

        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Throwable $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        throw new Exception($e->getMessage());
    }
}

// Close connection
function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

// Check if database exists and is accessible
function checkDatabaseConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        return ['success' => false, 'error' => 'MySQL connection failed: ' . $conn->connect_error];
    }
    
    $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    if ($result->num_rows === 0) {
        $conn->close();
        return ['success' => false, 'error' => "Database '" . DB_NAME . "' does not exist. Please import eklinik.sql first."];
    }
    
    $conn->select_db(DB_NAME);
    if ($conn->error) {
        $conn->close();
        return ['success' => false, 'error' => 'Cannot select database: ' . $conn->error];
    }
    
    // Check if users table exists
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if (!$result) {
        $conn->close();
        return ['success' => false, 'error' => 'Users table not found. Please import eklinik.sql'];
    }
    
    $conn->close();
    return ['success' => true, 'error' => null];
}
?>
