<?php
/**
 * Database Configuration
 * i-Klinik Politeknik Besut
 */

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'eklinik');

// Create connection with error handling
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        error_log("MySQL connection failed: " . $conn->connect_error);
        // Show a user-friendly error message and stop execution
        die("<h1>Connection Error</h1><p>Sorry, we are experiencing technical difficulties. Please try again later.</p><!-- Debug: " . $conn->connect_error . " -->");
    }
    
    // Select database
    $conn->select_db(DB_NAME);
    if ($conn->error) {
        error_log("Database '" . DB_NAME . "' not found. Please import the SQL file first.");
        die("<h1>Configuration Error</h1><p>Database not found. Please contact the administrator.</p><!-- Debug: Database '" . DB_NAME . "' not found -->");
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
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
