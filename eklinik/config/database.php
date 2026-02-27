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
    // Enable error reporting for mysqli
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        $conn->set_charset("utf8mb4");

        // Select database
        try {
            $conn->select_db(DB_NAME);
        } catch (mysqli_sql_exception $e) {
             throw new Exception("Database '" . DB_NAME . "' not found. Please import the SQL file first.");
        }

        return $conn;
    } catch (mysqli_sql_exception $e) {
        error_log("MySQL connection failed: " . $e->getMessage());
        throw new Exception("Connection failed: " . $e->getMessage());
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
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

        $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        if ($result->num_rows === 0) {
            $conn->close();
            return ['success' => false, 'error' => "Database '" . DB_NAME . "' does not exist. Please import eklinik.sql first."];
        }

        $conn->select_db(DB_NAME);

        // Check if users table exists
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        if (!$result) {
            $conn->close();
            return ['success' => false, 'error' => 'Users table not found. Please import eklinik.sql'];
        }

        $conn->close();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'MySQL connection failed: ' . $e->getMessage()];
    }
}
?>
