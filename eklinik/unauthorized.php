<?php
/**
 * Unauthorized Access Page - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Dilarang - i-Klinik</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-mesh-green">
    <div class="login-container">
        <div class="login-box" style="text-align: center;">
            <i class="fas fa-lock" style="font-size: 4rem; color: var(--danger); margin-bottom: 20px;"></i>
            <h1 style="color: var(--danger); margin-bottom: 15px;">Akses Dilarang</h1>
            <p style="color: var(--gray-300); margin-bottom: 30px;">
                Anda tidak mempunyai kebenaran untuk mengakses halaman ini.
            </p>
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Kembali ke Login
            </a>
        </div>
    </div>
</body>
</html>
