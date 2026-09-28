<?php
/**
 * Doctor Dashboard - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['doctor']);

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();

$conn = getDBConnection();

// Get appointment count (pending today)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE() AND status IN ('pending', 'confirmed', 'in_progress')");
$stmt->execute();
$__row = $stmt->get_result()->fetch_assoc();
$appointmentCount = $__row['count'] ?? 0;

// Get inventory stock percentage
$stmt = $conn->prepare("SELECT 
    SUM(CASE WHEN stock_quantity >= minimum_stock THEN 1 ELSE 0 END) as sufficient,
    COUNT(*) as total
    FROM inventory_items");
$stmt->execute();
$inventoryStats = $stmt->get_result()->fetch_assoc();
$inventoryPercentage = $inventoryStats['total'] > 0 
    ? round(($inventoryStats['sufficient'] / $inventoryStats['total']) * 100) 
    : 0;

// Get pharmacy medicine count
$stmt = $conn->prepare("SELECT SUM(stock_quantity) as total FROM medicines WHERE status != 'expired'");
$stmt->execute();
$__row = $stmt->get_result()->fetch_assoc();
$pharmacyCount = $__row['total'] ?? 0;

// Get OKU equipment borrowed count
$stmt = $conn->prepare("SELECT SUM(quantity) as count FROM oku_equipment_loans WHERE status IN ('pending', 'approved')");
$stmt->execute();
$__row = $stmt->get_result()->fetch_assoc();
$okuBorrowed = $__row['count'] ?? 0;

// Get critical patients count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM medical_records WHERE is_critical = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$__row = $stmt->get_result()->fetch_assoc();
$criticalPatients = $__row['count'] ?? 0;

// Get recent activities
$stmt = $conn->prepare("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$recentActivities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications
$totalNotifications = getUnreadNotificationCount($userId);

// Get pending stock loans
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM stock_loans WHERE status = 'pending'");
$stmt->execute();
$__row = $stmt->get_result()->fetch_assoc();
$pendingStockLoans = $__row['count'] ?? 0;

// Get pending OKU loans
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM oku_equipment_loans WHERE status = 'pending'");
$stmt->execute();
$__row = $stmt->get_result()->fetch_assoc();
$pendingOKULoans = $__row['count'] ?? 0;

// Get pending emergency reports
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM emergency_reports WHERE status = 'pending'");
$stmt->execute();
$__row = $stmt->get_result()->fetch_assoc();
$pendingEmergency = $__row['count'] ?? 0;

closeDBConnection($conn);

// Notification badges
$appointmentNotification = $appointmentCount;
$inventoryNotification = $pendingStockLoans;
$okuNotification = $pendingOKULoans;
$emergencyNotification = $pendingEmergency;
$activePage = 'dashboard';

// Start output buffering for content
ob_start();
?>

<!-- Stats Matrix Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $appointmentCount; ?></h3>
            <p>Appointment Hari Ini</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $inventoryPercentage; ?>%</h3>
            <p>Stok Inventory (First Aid)</p>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width: <?php echo $inventoryPercentage; ?>%"></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-pills"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $pharmacyCount; ?></h3>
            <p>Ubat Tersedia (Pharmacy)</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-wheelchair"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $okuBorrowed; ?></h3>
            <p>Peralatan OKU Dipinjam</p>
        </div>
    </div>
    
    <div class="stat-card" style="<?php echo $criticalPatients > 0 ? 'border-color: var(--danger);' : ''; ?>">
        <div class="stat-icon" style="<?php echo $criticalPatients > 0 ? 'background: rgba(220, 53, 69, 0.2); color: var(--danger);' : ''; ?>">
            <i class="fas fa-heartbeat"></i>
        </div>
        <div class="stat-info">
            <h3 style="<?php echo $criticalPatients > 0 ? 'color: var(--danger);' : ''; ?>"><?php echo $criticalPatients; ?></h3>
            <p>Pesakit Kritikal</p>
        </div>
    </div>
</div>

<!-- Recent Activity Feed -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> Aktiviti Terkini</h3>
    </div>
    <ul class="activity-feed">
        <?php if (empty($recentActivities)): ?>
        <li style="justify-content: center; color: var(--gray-600);">
            Tiada aktiviti terkini
        </li>
        <?php else: ?>
            <?php foreach ($recentActivities as $activity): ?>
        <li>
            <div class="activity-icon">
                <i class="fas fa-<?php 
                    echo strpos($activity['activity'], 'registered') !== false ? 'user-plus' : 
                        (strpos($activity['activity'], 'Appointment') !== false ? 'calendar-check' : 
                        (strpos($activity['activity'], 'Stock') !== false ? 'box' : 'clipboard')); 
                ?>"></i>
            </div>
            <div class="activity-content">
                <p><strong><?php echo htmlspecialchars($activity['activity']); ?></strong></p>
                <p style="color: var(--gray-600);"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></p>
                <span class="time"><?php echo date('j M Y, g:i A', strtotime($activity['created_at'])); ?></span>
            </div>
        </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bolt"></i> Tindakan Segera</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="<?php echo BASE_URL; ?>/doctor/appointment.php" class="btn btn-primary">
            <i class="fas fa-calendar-plus"></i> Urus Appointment
        </a>
        <a href="<?php echo BASE_URL; ?>/doctor/inventory.php" class="btn btn-info">
            <i class="fas fa-boxes"></i> Kelulusan Stok
            <?php if ($pendingStockLoans > 0): ?>
                <span class="badge" style="margin-left: 5px;"><?php echo $pendingStockLoans; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo BASE_URL; ?>/doctor/oku-unit.php" class="btn btn-success">
            <i class="fas fa-wheelchair"></i> Kelulusan OKU
            <?php if ($pendingOKULoans > 0): ?>
                <span class="badge" style="margin-left: 5px;"><?php echo $pendingOKULoans; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo BASE_URL; ?>/doctor/emergency.php" class="btn btn-danger">
            <i class="fas fa-ambulance"></i> Kecemasan
            <?php if ($pendingEmergency > 0): ?>
                <span class="badge" style="margin-left: 5px;"><?php echo $pendingEmergency; ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
