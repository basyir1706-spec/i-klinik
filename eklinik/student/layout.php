<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - i-Klinik</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body class="bg-mesh-green">
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-heartbeat"></i> i-Klinik</h2>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="<?php echo BASE_URL; ?>/student/dashboard.php" class="<?php echo ($activePage ?? '') === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/student/appointment.php" class="<?php echo ($activePage ?? '') === 'appointment' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Appointment</span>
                        <?php if (($appointmentNotification ?? 0) > 0): ?>
                            <span class="badge"><?php echo $appointmentNotification; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/student/stock-loan.php" class="<?php echo ($activePage ?? '') === 'stock-loan' ? 'active' : ''; ?>">
                        <i class="fas fa-box"></i>
                        <span>Pinjaman Stok</span>
                        <?php if (($stockNotification ?? 0) > 0): ?>
                            <span class="badge"><?php echo $stockNotification; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php if ($isOKU ?? false): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/student/oku-loan.php" class="<?php echo ($activePage ?? '') === 'oku-loan' ? 'active' : ''; ?>">
                        <i class="fas fa-wheelchair"></i>
                        <span>Pinjaman OKU</span>
                        <?php if (($okuNotification ?? 0) > 0): ?>
                            <span class="badge"><?php echo $okuNotification; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navbar -->
            <nav class="top-navbar">
                <h1 class="page-title"><?php echo $pageTitle ?? 'Dashboard'; ?></h1>
                
                <div class="user-menu">
                    <div class="user-menu-toggle" onclick="toggleUserDropdown()">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <div class="name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                            <div class="role"><?php echo ucfirst($_SESSION['role']); ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: var(--gray-600);"></i>
                    </div>
                    
                    <div class="user-dropdown" id="userDropdown">
                        <a href="<?php echo BASE_URL; ?>/student/profile.php">
                            <i class="fas fa-user"></i> Profil Saya
                        </a>
                        <a href="<?php echo BASE_URL; ?>/student/notifications.php">
                            <i class="fas fa-bell"></i> Notifikasi
                            <?php if (($totalNotifications ?? 0) > 0): ?>
                                <span class="badge" style="float: right;"><?php echo $totalNotifications; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo BASE_URL; ?>/logout.php" style="color: var(--danger);">
                            <i class="fas fa-sign-out-alt"></i> Log Keluar
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Content Area -->
            <div class="content-area">
                <?php echo $content; ?>
            </div>
        </div>
    </div>
    
    <script>
        function toggleUserDropdown() {
            document.getElementById('userDropdown').classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.closest('.user-menu')) {
                var dropdowns = document.getElementsByClassName('user-dropdown');
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>
</body>
</html>
