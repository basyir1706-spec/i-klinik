<?php
/**
 * Logout - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/config/config.php';

// Log activity before logout
if (isLoggedIn()) {
    logActivity(getCurrentUserId(), 'User logged out', 'Logout successful');
}

// Destroy session
session_destroy();

// Redirect to login
redirect(BASE_URL . '/login.php');
?>
