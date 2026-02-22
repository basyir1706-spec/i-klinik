<?php
/**
 * Password Migration Script - i-Klinik Politeknik Besut
 * This script hashes all plain text passwords in the database.
 * Run this once.
 */

require_once __DIR__ . '/../config/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security Check: Only allow CLI or Logged in Doctor
$isCli = (php_sapi_name() === 'cli');
$isDoctor = (isLoggedIn() && hasRole('doctor'));

if (!$isCli && !$isDoctor) {
    die("Access Denied. You must be logged in as a Doctor to run this script via web browser.");
}

echo "<h1>Password Migration Tool</h1>";
echo "<pre>";

try {
    $conn = getDBConnection();
    echo "Connected to database.\n";

    // Get all users
    $result = $conn->query("SELECT id, name, password FROM users");
    if (!$result) {
        throw new Exception("Failed to fetch users: " . $conn->error);
    }

    echo "Found " . $result->num_rows . " users.\n";

    $updatedCount = 0;
    $skippedCount = 0;

    while ($user = $result->fetch_assoc()) {
        $password = $user['password'];

        // Check if password looks like a BCrypt hash (starts with $2y$)
        // A simple check, might need to be more robust if other algo used
        if (substr($password, 0, 4) === '$2y$') {
            echo "User [{$user['id']}] {$user['name']}: Already hashed. Skipping.\n";
            $skippedCount++;
            continue;
        }

        // Hash the password
        $newHash = password_hash($password, PASSWORD_DEFAULT);

        // Update user
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $newHash, $user['id']);

        if ($stmt->execute()) {
            echo "User [{$user['id']}] {$user['name']}: Password hashed successfully.\n";
            $updatedCount++;
        } else {
            echo "User [{$user['id']}] {$user['name']}: Failed to update password. Error: " . $stmt->error . "\n";
        }
    }

    echo "\nMigration Complete.\n";
    echo "Updated: $updatedCount\n";
    echo "Skipped: $skippedCount\n";

    closeDBConnection($conn);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
