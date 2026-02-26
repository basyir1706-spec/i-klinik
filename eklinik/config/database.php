<?php
/**
 * Database Configuration
 * i-Klinik Politeknik Besut
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'eklinik');

// Enable MySQLi exception reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Create connection with error handling
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        $conn->select_db(DB_NAME);
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        // Log error but re-throw so caller knows it failed
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
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
        // Temporarily disable strict mode for this check function to avoid crashing setup checker
        $driver = new mysqli_driver();
        $old_mode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_OFF);

        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);

        if ($conn->connect_error) {
            mysqli_report($old_mode); // Restore
            return ['success' => false, 'error' => 'MySQL connection failed: ' . $conn->connect_error];
        }

        $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        if ($result && $result->num_rows === 0) {
            $conn->close();
            mysqli_report($old_mode); // Restore
            return ['success' => false, 'error' => "Database '" . DB_NAME . "' does not exist. Please import eklinik.sql first."];
        }

        $conn->select_db(DB_NAME);
        if ($conn->error) {
            $conn->close();
            mysqli_report($old_mode); // Restore
            return ['success' => false, 'error' => 'Cannot select database: ' . $conn->error];
        }

        // Check if users table exists
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        if (!$result) {
            $conn->close();
            mysqli_report($old_mode); // Restore
            return ['success' => false, 'error' => 'Users table not found. Please import eklinik.sql'];
        }

        $conn->close();
        mysqli_report($old_mode); // Restore
        return ['success' => true, 'error' => null];

    } catch (Exception $e) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Re-enable default just in case
        return ['success' => false, 'error' => 'Connection error: ' . $e->getMessage()];
    }
}
?>
