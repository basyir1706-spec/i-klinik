<?php
/**
 * i-Klinik Politeknik Besut
 * Main Entry Point
 */
require_once __DIR__ . '/config/config.php';

// Redirect to login or appropriate dashboard
if (isLoggedIn()) {
    if (hasRole('doctor')) {
        redirect(BASE_URL . '/doctor/dashboard.php');
    } else {
        redirect(BASE_URL . '/student/dashboard.php');
    }
} else {
    redirect(BASE_URL . '/login.php');
}
?>
