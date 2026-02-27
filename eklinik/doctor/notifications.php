<?php
/**
 * Doctor Notifications Page - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['doctor', 'staff']);

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();
$activePage = 'notifications';

// Start output buffering for content
ob_start();
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bell"></i> Notifikasi</h3>
    </div>
    <div style="text-align: center; padding: 40px;">
        <i class="fas fa-tools" style="font-size: 4rem; color: var(--warning); margin-bottom: 20px;"></i>
        <h2>Halaman Dalam Pembinaan</h2>
        <p>Maaf, halaman ini sedang dikemaskini. Sila cuba lagi kemudian.</p>
        <a href="<?php echo BASE_URL; ?>/doctor/dashboard.php" class="btn btn-primary" style="margin-top: 20px;">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
