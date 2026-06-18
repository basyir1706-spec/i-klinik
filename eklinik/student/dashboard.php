<?php
/**
 * Student Dashboard - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['student', 'staff']);

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();
$isOKU = $_SESSION['is_oku'] ?? false;

$conn = getDBConnection();

// Get current queue info
$queueInfo = getCurrentQueueNumber($userId);
$todayQueueCount = getTodayQueueCount();

// Get unread notifications
$totalNotifications = getUnreadNotificationCount($userId);

// Get pending appointments
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND status = 'pending'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$pendingAppointments = (($stmt->get_result()->fetch_assoc() ?: ['count' => 0])['count'] ?? 0);

// Get active stock loans
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM stock_loans WHERE user_id = ? AND status IN ('pending', 'approved')");
$stmt->bind_param("i", $userId);
$stmt->execute();
$activeLoans = (($stmt->get_result()->fetch_assoc() ?: ['count' => 0])['count'] ?? 0);

// Get today's appointments list
$stmt = $conn->prepare("SELECT a.*, u.name FROM appointments a JOIN users u ON a.user_id = u.id WHERE a.appointment_date = CURDATE() ORDER BY a.queue_number ASC");
$stmt->execute();
$todayAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);

// Notification badges
$appointmentNotification = $pendingAppointments;
$stockNotification = $activeLoans;
$activePage = 'dashboard';

// Start output buffering for content
ob_start();
?>

<!-- Queue Number Banner -->
<?php if ($queueInfo): ?>
<div class="queue-display">
    <div class="queue-label">Nombor Giliran Anda</div>
    <div class="queue-number"><?php echo $queueInfo['queue_number']; ?></div>
    <div class="queue-label">
        Status: 
        <span class="badge badge-<?php 
            echo $queueInfo['status'] === 'completed' ? 'success' : 
                ($queueInfo['status'] === 'in_progress' ? 'info' : 'warning'); 
        ?>">
            <?php echo ucfirst($queueInfo['status']); ?>
        </span>
    </div>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $todayQueueCount; ?></h3>
            <p>Giiran Hari Ini</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $pendingAppointments; ?></h3>
            <p>Temujanji Pending</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-box-open"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $activeLoans; ?></h3>
            <p>Pinjaman Aktif</p>
        </div>
    </div>
</div>

<!-- Clinic Guidelines Banner -->
<div class="banner">
    <h2><i class="fas fa-info-circle"></i> Panduan Klinik</h2>
    <p><strong>Waktu Operasi:</strong> 8:00 Pagi - 5:00 Petang</p>
    <p>Ikuti langkah-langkah berikut untuk mendapatkan rawatan dengan lancar.</p>
    
    <div class="guidelines-steps">
        <div class="step-card">
            <div class="step-number">1</div>
            <h4>Buat Temujanji</h4>
            <p>Tempah slot masa melalui sistem sebelum hadir.</p>
        </div>
        <div class="step-card">
            <div class="step-number">2</div>
            <h4>Daftar Diri</h4>
            <p>Hadir 10 minit awal dan scan kad matrik di kaunter.</p>
        </div>
        <div class="step-card">
            <div class="step-number">3</div>
            <h4>Tunggu Giliran</h4>
            <p>Pantau status giliran anda melalui dashboard ini.</p>
        </div>
        <div class="step-card">
            <div class="step-number">4</div>
            <h4>Rawatan</h4>
            <p>Masuk ke bilik doktor apabila nombor dipanggil.</p>
        </div>
        <div class="step-card">
            <div class="step-number">5</div>
            <h4>Ambil Ubat</h4>
            <p>Ambil ubat di farmasi jika diberikan preskripsi.</p>
        </div>
    </div>
</div>

<!-- Today's Queue List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list"></i> Senarai Giliran Hari Ini</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Queue #</th>
                    <th>Nama</th>
                    <th>Masa</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($todayAppointments)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--gray-600);">
                        Tiada giliran untuk hari ini
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($todayAppointments as $apt): ?>
                <tr>
                    <td><strong>#<?php echo $apt['queue_number']; ?></strong></td>
                    <td><?php echo htmlspecialchars($apt['name']); ?></td>
                    <td><?php echo formatTime($apt['appointment_time'], 'h:i A'); ?></td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $apt['status'] === 'completed' ? 'success' : 
                                ($apt['status'] === 'in_progress' ? 'info' : 
                                ($apt['status'] === 'cancelled' ? 'danger' : 'warning')); 
                        ?>">
                            <?php echo ucfirst($apt['status']); ?>
                        </span>
                    </td>
                </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
