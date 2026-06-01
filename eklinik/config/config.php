<?php
/**
 * Main Configuration File
 * i-Klinik Politeknik Besut
 */

// Error Reporting for Development
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/database.php';

// Base URL
define('BASE_URL', 'http://localhost/eklinik');

// Site Name
define('SITE_NAME', 'i-Klinik Politeknik Besut');

// Helper Functions

// Redirect to a specific URL
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user has specific role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user role
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Get current user name
function getCurrentUserName() {
    return !empty($_SESSION['name']) ? $_SESSION['name'] : 'User';
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php');
    }
}

// Require specific role
function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!in_array($_SESSION['role'], $roles)) {
        redirect(BASE_URL . '/unauthorized.php');
    }
}

// Sanitize input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Get unread notification count
function getUnreadNotificationCount($userId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    closeDBConnection($conn);
    return $row['count'] ?? 0;
}

// Get current queue number for today's appointments
function getCurrentQueueNumber($userId) {
    $conn = getDBConnection();
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT queue_number, status FROM appointments WHERE user_id = ? AND appointment_date = ? ORDER BY queue_number ASC");
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        closeDBConnection($conn);
        return $row;
    }
    closeDBConnection($conn);
    return null;
}

// Get today's queue count
function getTodayQueueCount() {
    $conn = getDBConnection();
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND status != 'cancelled'");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    closeDBConnection($conn);
    return $row['count'] ?? 0;
}

// Format date
function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

// Format time
function formatTime($time, $format = 'h:i A') {
    return date($format, strtotime($time));
}

// Get user info by ID
function getUserInfo($userId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    closeDBConnection($conn);
    return $user;
}

// Create notification
function createNotification($userId, $title, $message, $type = 'general') {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    $stmt->execute();
    $insertId = $conn->insert_id;
    closeDBConnection($conn);
    return $insertId;
}

// Log activity
function logActivity($userId, $activity, $description = '') {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $activity, $description);
    $stmt->execute();
    closeDBConnection($conn);
}

// Get next queue number for today
function getNextQueueNumber() {
    $conn = getDBConnection();
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COALESCE(MAX(queue_number), 0) + 1 as next_queue FROM appointments WHERE appointment_date = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    closeDBConnection($conn);
    return $row['next_queue'] ?? 1;
}
?>
