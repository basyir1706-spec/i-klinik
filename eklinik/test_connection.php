<?php
session_start();
/**
 * Test Connection & Debug Page
 * i-Klinik Politeknik Besut
 */

echo "<h1>i-Klinik Debug Page</h1>";
echo "<hr>";

// Test 1: Database Connection
echo "<h2>1. Database Connection Test</h2>";
try {
    $conn = new mysqli('localhost', 'root', '', 'eklinik');
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
if (isset($conn) && $conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p style='color: green;'>✓ Users table exists with {$row['count']} users</p>";
        
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
}

// Test 3: Session Test
echo "<h2>3. Session Test</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p style='color: green;'>✓ Sessions are working</p>";
} else {
    echo "<p style='color: red;'>❌ Sessions are not working</p>";
}

// Test 4: PHP Info
echo "<h2>4. PHP Version</h2>";
echo "<p style='color: blue;'>PHP Version: " . phpversion() . "</p>";

// Test 5: BASE_URL Test
echo "<h2>5. Base URL Test</h2>";
$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$http_host/eklinik";
echo "<p>Your BASE_URL should be: <code>$base_url</code></p>";
echo "<p>Current URL: <code>" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$http_host$request_uri</code></p>";

echo "<hr>";
echo "<h2>Test Login Credentials:</h2>";
echo "<ul>";
echo "<li><strong>Student:</strong> Matrix: 34DIT24F1001 | Password: 123456</li>";
echo "<li><strong>Doctor:</strong> Email: doctor@polibesut.edu.my | Password: 123456</li>";
echo "<li><strong>Staff:</strong> Email: staff@polibesut.edu.my | Password: 123456</li>";
echo "</ul>";

echo "<hr>";
echo "<a href='login.php'>Go to Login Page</a>";
?>
<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    h1 { color: #022c2e; }
    h2 { color: #009a44; margin-top: 30px; }
    table { width: 100%; margin: 20px 0; background: white; }
    th { background: #022c2e; color: white; }
    code { background: #e0e0e0; padding: 2px 6px; border-radius: 3px; }
</style>
