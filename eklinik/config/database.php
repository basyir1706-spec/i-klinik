<?php
/**
 * Database Configuration
 * i-Klinik Politeknik Besut
 */

// Database Configuration with Environment Variable Support
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'eklinik');

// Create connection with error handling
function getDBConnection() {
    try {
        // Suppress warnings for connection attempt to handle them manually via try-catch if needed,
        // but mysqli constructor throws exceptions in recent PHP versions if configured, or we check connect_error.
        // For compatibility, we'll use @ and check connect_error.
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);

        if ($conn->connect_error) {
            throw new Exception("MySQL connection failed: " . $conn->connect_error);
        }

        // Select database
        if (!$conn->select_db(DB_NAME)) {
            // Attempt to create database if it doesn't exist (optional, but good for dev)
            // For now, just throw exception
            throw new Exception("Database '" . DB_NAME . "' not found. Please import the SQL file first.");
        }

        if (!$conn->set_charset("utf8mb4")) {
             throw new Exception("Error loading character set utf8mb4: " . $conn->error);
        }

        return $conn;
    } catch (Exception $e) {
        error_log($e->getMessage());
        // Re-throw exception so caller can handle it or show error page
        throw $e;
    }
}

// Close connection
function closeDBConnection($conn) {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}

// Check if database exists and is accessible
function checkDatabaseConnection() {
    try {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);

        if ($conn->connect_error) {
            return ['success' => false, 'error' => 'MySQL connection failed: ' . $conn->connect_error];
        }

        $dbName = DB_NAME;
        // Escape DB name for safety in query
        $dbNameEscaped = $conn->real_escape_string($dbName);

        $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbNameEscaped'");
        if ($result->num_rows === 0) {
            $conn->close();
            return ['success' => false, 'error' => "Database '$dbName' does not exist. Please import eklinik.sql first."];
        }

        if (!$conn->select_db($dbName)) {
            $error = $conn->error;
            $conn->close();
            return ['success' => false, 'error' => 'Cannot select database: ' . $error];
        }

        // Check if users table exists
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        if (!$result) {
            $conn->close();
            return ['success' => false, 'error' => 'Users table not found. Please import eklinik.sql'];
        }

        $conn->close();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}
?>
