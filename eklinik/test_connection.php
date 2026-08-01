<?php
/**
 * Test Connection & Debug Page
 * i-Klinik Politeknik Besut
 */

echo "<h1>i-Klinik Debug Page</h1>";
echo "<hr>";

// Test 1: Database Connection
echo "<h2>1. Database Connection Test</h2>";
try {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli('127.0.0.1', 'root', '', 'eklinik');
    if ($conn->connect_error) {
        echo "<p style='color: red;'>❌ Connection failed: " . $conn->connect_error . "</p>";
    } else {
        echo "<p style='color: green;'>✓ Database connection successful!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Connection failed: " . $e->getMessage() . "</p>";
}

// Test 2: Check Users Table
echo "<h2>2. Users Table Test</h2>";
if (isset($conn) && $conn && !$conn->connect_error) {
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Users table exists!</p>";
        
        // List all users
        echo "<h3>All Users:</h3>";
        $result = $conn->query("SELECT id, name, email, matrix_no, role, password FROM users");
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Matrix No</th><th>Role</th><th>Password</th></tr>";
        while ($user = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['name']}</td>";
            echo "<td>" . ($user['email'] ?? '-') . "</td>";
            echo "<td>" . ($user['matrix_no'] ?? '-') . "</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$user['password']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ Users table doesn't exist or query failed</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ Skipping table test due to connection failure</p>";
}

// Test 3: Test Credentials
echo "<h2>3. Test Credentials</h2>";
echo "<ul>";
echo "<li><strong>Student:</strong> Matrix: 34DIT24F1001 (Password: 123456)</li>";
echo "<li><strong>Doctor:</strong> Email: doctor@polibesut.edu.my (Password: 123456)</li>";
echo "<li><strong>Staff:</strong> Email: staff@polibesut.edu.my (Password: 123456)</li>";
echo "</ul>";

if (isset($conn) && $conn) {
    $conn->close();
}
?>
