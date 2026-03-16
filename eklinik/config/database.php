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
    } catch (mysqli_sql_exception $e) {
        error_log("MySQL connection failed: " . $e->getMessage());
        return null;
    }
    
    if ($conn->connect_error) {
        error_log("MySQL connection failed: " . $conn->connect_error);
        return null;
    }
    
    // Select database
    try {
        $conn->select_db(DB_NAME);
    } catch (mysqli_sql_exception $e) {
        error_log("Database '" . DB_NAME . "' not found. Please import the SQL file first.");
        return null;
    }
    if ($conn->error) {
        error_log("Database '" . DB_NAME . "' not found. Please import the SQL file first.");
        return null;
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
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    } catch (mysqli_sql_exception $e) {
        return ['success' => false, 'error' => 'MySQL connection failed: ' . $e->getMessage()];
    }
    
    if ($conn->connect_error) {
        return ['success' => false, 'error' => 'MySQL connection failed: ' . $conn->connect_error];
    }
    
    try {
        $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        if ($result->num_rows === 0) {
            $conn->close();
            return ['success' => false, 'error' => "Database '" . DB_NAME . "' does not exist. Please import eklinik.sql first."];
        }
    } catch (mysqli_sql_exception $e) {
        return ['success' => false, 'error' => 'Query failed: ' . $e->getMessage()];
    }
    
    try {
        $conn->select_db(DB_NAME);
    } catch (mysqli_sql_exception $e) {
        $conn->close();
        return ['success' => false, 'error' => 'Cannot select database: ' . $e->getMessage()];
    }
    if ($conn->error) {
        $conn->close();
        return ['success' => false, 'error' => 'Cannot select database: ' . $conn->error];
    }
    
    // Check if users table exists
    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
    } catch (mysqli_sql_exception $e) {
        $conn->close();
        return ['success' => false, 'error' => 'Users table not found. Please import eklinik.sql'];
    }
    if (!$result) {
        $conn->close();
        return ['success' => false, 'error' => 'Users table not found. Please import eklinik.sql'];
    }
    
    $conn->close();
    return ['success' => true, 'error' => null];
}
?>
