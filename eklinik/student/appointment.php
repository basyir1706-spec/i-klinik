<?php
/**
 * Student Appointment Page - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['student', 'staff']);

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();
$isOKU = $_SESSION['is_oku'] ?? false;
$success = '';
$error = '';

$conn = getDBConnection();

// Handle form submission for new appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_appointment'])) {
    // CSRF Check
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ralat keselamatan: Token CSRF tidak sah.';
    } else {
        $appointmentDate = sanitize($_POST['appointment_date']);
        $appointmentTime = sanitize($_POST['appointment_time']);
        $symptoms = sanitize($_POST['symptoms']);
        
        if (empty($appointmentDate) || empty($appointmentTime)) {
            $error = 'Sila pilih tarikh dan masa temujanji';
        } else {
            $queueNumber = getNextQueueNumber();

            $stmt = $conn->prepare("INSERT INTO appointments (user_id, appointment_date, appointment_time, queue_number, symptoms, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("issis", $userId, $appointmentDate, $appointmentTime, $queueNumber, $symptoms);

            if ($stmt->execute()) {
                $success = 'Temujanji berjaya dibuat!';
                logActivity($userId, 'Appointment created', 'New appointment created for ' . $appointmentDate);

                // Create notification
                createNotification($userId, 'Temujanji Dicipta', 'Temujanji anda untuk ' . $appointmentDate . ' telah berjaya dicipta.', 'appointment');
            } else {
                $error = 'Ralat berlaku semasa mencipta temujanji';
            }
        }
    }
}

// Handle cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    // CSRF Check for GET action
    if (!verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        $error = 'Ralat keselamatan: Token CSRF tidak sah.';
    } else {
        $aptId = (int)$_GET['cancel'];
        $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $aptId, $userId);
        $stmt->execute();
        $success = 'Temujanji dibatalkan';
    }
}

// Get current queue info
$queueInfo = getCurrentQueueNumber($userId);

// Get all appointments for this user
$stmt = $conn->prepare("SELECT a.*, u.name as doctor_name FROM appointments a LEFT JOIN users u ON a.doctor_id = u.id WHERE a.user_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications
$totalNotifications = getUnreadNotificationCount($userId);
$pendingAppointments = 0;
foreach ($appointments as $apt) {
    if ($apt['status'] === 'pending') $pendingAppointments++;
}

// Notification badges
$appointmentNotification = $pendingAppointments;
$activePage = 'appointment';

// Start output buffering for content
ob_start();
?>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <?php echo $success; ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo $error; ?>
</div>
<?php endif; ?>

<!-- Current Queue Number Banner -->
<?php if ($queueInfo && $queueInfo['appointment_date'] === date('Y-m-d')): ?>
<div class="banner" style="text-align: center;">
    <h2><i class="fas fa-ticket-alt"></i> Nombor Giliran Semasa</h2>
    <div class="queue-number" style="font-size: 4rem; margin: 10px 0;">#<?php echo $queueInfo['queue_number']; ?></div>
    <p>Status: 
        <span class="badge badge-<?php 
            echo $queueInfo['status'] === 'completed' ? 'success' : 
                ($queueInfo['status'] === 'in_progress' ? 'info' : 'warning'); 
        ?>">
            <?php echo ucfirst($queueInfo['status']); ?>
        </span>
    </p>
</div>
<?php endif; ?>

<!-- Create New Appointment Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-plus"></i> Buat Temujanji Baru</h3>
    </div>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div class="form-row">
            <div class="form-group">
                <label for="appointment_date"><i class="fas fa-calendar"></i> Tarikh Temujanji</label>
                <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                       min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label for="appointment_time"><i class="fas fa-clock"></i> Masa Temujanji</label>
                <select class="form-control" id="appointment_time" name="appointment_time" required>
                    <option value="">Pilih Masa</option>
                    <option value="08:00">8:00 Pagi</option>
                    <option value="08:30">8:30 Pagi</option>
                    <option value="09:00">9:00 Pagi</option>
                    <option value="09:30">9:30 Pagi</option>
                    <option value="10:00">10:00 Pagi</option>
                    <option value="10:30">10:30 Pagi</option>
                    <option value="11:00">11:00 Pagi</option>
                    <option value="11:30">11:30 Pagi</option>
                    <option value="12:00">12:00 Tengahari</option>
                    <option value="14:00">2:00 Petang</option>
                    <option value="14:30">2:30 Petang</option>
                    <option value="15:00">3:00 Petang</option>
                    <option value="15:30">3:30 Petang</option>
                    <option value="16:00">4:00 Petang</option>
                    <option value="16:30">4:30 Petang</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="symptoms"><i class="fas fa-notes-medical"></i> Gejala / Sebab Berjumpa Doktor</label>
            <textarea class="form-control" id="symptoms" name="symptoms" rows="3" 
                      placeholder="Terangkan gejala atau sebab anda ingin berjumpa doktor"></textarea>
        </div>
        <button type="submit" name="create_appointment" class="btn btn-primary">
            <i class="fas fa-plus"></i> Cipta Temujanji
        </button>
    </form>
</div>

<!-- Appointment History -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> Sejarah Temujanji</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tarikh</th>
                    <th>Masa</th>
                    <th>Queue #</th>
                    <th>Gejala</th>
                    <th>Doktor</th>
                    <th>Status</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--gray-600);">
                        Tiada temujanji dijumpai
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $apt): ?>
                <tr>
                    <td><?php echo formatDate($apt['appointment_date']); ?></td>
                    <td><?php echo formatTime($apt['appointment_time'], 'h:i A'); ?></td>
                    <td>#<?php echo $apt['queue_number']; ?></td>
                    <td><?php echo htmlspecialchars(substr($apt['symptoms'] ?? '', 0, 50)); ?><?php echo strlen($apt['symptoms'] ?? '') > 50 ? '...' : ''; ?></td>
                    <td><?php echo $apt['doctor_name'] ?? '-'; ?></td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $apt['status'] === 'completed' ? 'success' : 
                                ($apt['status'] === 'in_progress' ? 'info' : 
                                ($apt['status'] === 'cancelled' ? 'danger' : 'warning')); 
                        ?>">
                            <?php echo ucfirst($apt['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($apt['status'] === 'pending' || $apt['status'] === 'confirmed'): ?>
                        <a href="?cancel=<?php echo $apt['id']; ?>&csrf_token=<?php echo generateCSRFToken(); ?>" class="btn btn-danger btn-sm"
                           onclick="return confirm('Adakah anda pasti ingin membatalkan temujanji ini?')">
                            <i class="fas fa-times"></i> Batal
                        </a>
                        <?php else: ?>
                        <span style="color: var(--gray-600);">-</span>
                        <?php endif; ?>
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
