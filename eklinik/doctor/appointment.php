<?php
/**
 * Doctor Appointment Page - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['doctor']);

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();
$success = '';
$error = '';

$conn = getDBConnection();

// Handle form submission for updating patient/appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_patient'])) {
        // Update patient info after consultation
        $appointmentId = (int)$_POST['appointment_id'];
        $diagnosis = sanitize($_POST['diagnosis']);
        $treatment = sanitize($_POST['treatment']);
        $prescription = sanitize($_POST['prescription']);
        $isCritical = isset($_POST['is_critical']) ? 1 : 0;
        $notes = sanitize($_POST['notes']);
        
        // Update appointment status
        $stmt = $conn->prepare("UPDATE appointments SET status = 'completed', doctor_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $userId, $appointmentId);
        $stmt->execute();
        
        // Get patient ID
        $stmt = $conn->prepare("SELECT user_id FROM appointments WHERE id = ?");
        $stmt->bind_param("i", $appointmentId);
        $stmt->execute();
        $patientId_row = $stmt->get_result()->fetch_assoc();
        $patientId = $patientId_row['user_id'] ?? null;
        
        // Create medical record
        $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, appointment_id, diagnosis, treatment, prescription, notes, is_critical) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissssi", $patientId, $userId, $appointmentId, $diagnosis, $treatment, $prescription, $notes, $isCritical);
        $stmt->execute();
        
        $success = 'Maklumat pesakit berjaya dikemaskini!';
        logActivity($userId, 'Patient record updated', 'Updated record for appointment #' . $appointmentId);
        
        // Notify patient
        createNotification($patientId, 'Temujanji Selesai', 'Temujanji anda telah selesai. Sila ambil ubat di farmasi jika ada preskripsi.', 'appointment');
    }
    
    if (isset($_POST['confirm_appointment'])) {
        $appointmentId = (int)$_POST['appointment_id'];
        $stmt = $conn->prepare("UPDATE appointments SET status = 'confirmed', doctor_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $userId, $appointmentId);
        $stmt->execute();
        $success = 'Temujanji disahkan!';
    }
    
    if (isset($_POST['start_consultation'])) {
        $appointmentId = (int)$_POST['appointment_id'];
        $stmt = $conn->prepare("UPDATE appointments SET status = 'in_progress' WHERE id = ?");
        $stmt->bind_param("i", $appointmentId);
        $stmt->execute();
        $success = 'Sesi rawatan dimulakan!';
    }
}

// Get today's appointments for this doctor
$stmt = $conn->prepare("SELECT a.*, u.name, u.matrix_no, u.phone, u.blood_type, u.is_oku FROM appointments a JOIN users u ON a.user_id = u.id WHERE (a.doctor_id = ? OR a.doctor_id IS NULL) AND a.appointment_date = CURDATE() ORDER BY a.queue_number ASC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$todayAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get current queue number being served
$currentQueue = null;
foreach ($todayAppointments as $apt) {
    if ($apt['status'] === 'in_progress') {
        $currentQueue = $apt;
        break;
    }
}

// Get unread notifications
$totalNotifications = getUnreadNotificationCount($userId);
$pendingAppointments = 0;
foreach ($todayAppointments as $apt) {
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
<div class="banner" style="text-align: center;">
    <h2><i class="fas fa-ticket-alt"></i> Nombor Giliran Semasa</h2>
    <?php if ($currentQueue): ?>
    <div class="queue-number" style="font-size: 4rem; margin: 10px 0;">#<?php echo $currentQueue['queue_number']; ?></div>
    <p>Pesakit: <strong><?php echo htmlspecialchars($currentQueue['name']); ?></strong></p>
    <?php else: ?>
    <div class="queue-number" style="font-size: 4rem; margin: 10px 0;">-</div>
    <p>Tiada pesakit sedang dirawat</p>
    <?php endif; ?>
</div>

<!-- Today's Appointment List -->
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
                    <th>Matrik/ID</th>
                    <th>Masa</th>
                    <th>Gejala</th>
                    <th>Status</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($todayAppointments)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--gray-600);">
                        Tiada temujanji untuk hari ini
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($todayAppointments as $apt): ?>
                <tr>
                    <td><strong>#<?php echo $apt['queue_number']; ?></strong></td>
                    <td>
                        <?php echo htmlspecialchars($apt['name']); ?>
                        <?php if ($apt['is_oku']): ?>
                            <span class="badge badge-info" style="margin-left: 5px;">OKU</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($apt['matrix_no'] ?? '-'); ?></td>
                    <td><?php echo formatTime($apt['appointment_time'], 'h:i A'); ?></td>
                    <td><?php echo htmlspecialchars(substr($apt['symptoms'] ?? '', 0, 40)); ?><?php echo strlen($apt['symptoms'] ?? '') > 40 ? '...' : ''; ?></td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $apt['status'] === 'completed' ? 'success' : 
                                ($apt['status'] === 'in_progress' ? 'info' : 
                                ($apt['status'] === 'confirmed' ? 'primary' : 'warning')); 
                        ?>">
                            <?php echo ucfirst($apt['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($apt['status'] === 'pending'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                            <button type="submit" name="confirm_appointment" class="btn btn-success btn-sm">
                                <i class="fas fa-check"></i> Sahkan
                            </button>
                        </form>
                        <?php elseif ($apt['status'] === 'confirmed'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                            <button type="submit" name="start_consultation" class="btn btn-primary btn-sm">
                                <i class="fas fa-play"></i> Rawat
                            </button>
                        </form>
                        <?php elseif ($apt['status'] === 'in_progress'): ?>
                        <button type="button" class="btn btn-info btn-sm" onclick="openTreatmentModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['name']); ?>')">
                            <i class="fas fa-stethoscope"></i> Rawatan
                        </button>
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

<!-- Treatment Modal -->
<div class="modal" id="treatmentModal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-stethoscope"></i> Rawatan Pesakit</h3>
            <button class="modal-close" onclick="closeTreatmentModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="appointment_id" id="modal_appointment_id">
            <div style="margin-bottom: 20px; padding: 15px; background: rgba(0, 255, 0, 0.1); border-radius: 10px;">
                <strong>Pesakit:</strong> <span id="modal_patient_name"></span>
            </div>
            
            <div class="form-group">
                <label for="diagnosis"><i class="fas fa-clipboard-list"></i> Diagnosis</label>
                <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="treatment"><i class="fas fa-user-md"></i> Rawatan</label>
                <textarea class="form-control" id="treatment" name="treatment" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="prescription"><i class="fas fa-pills"></i> Preskripsi Ubat</label>
                <textarea class="form-control" id="prescription" name="prescription" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_critical" id="is_critical"> 
                    <i class="fas fa-heartbeat"></i> Pesakit Kritikal
                </label>
            </div>
            
            <div class="form-group">
                <label for="notes"><i class="fas fa-comment"></i> Nota Tambahan</label>
                <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
            </div>
            
            <button type="submit" name="update_patient" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> Simpan Rekod
            </button>
        </form>
    </div>
</div>

<script>
function openTreatmentModal(appointmentId, patientName) {
    document.getElementById('modal_appointment_id').value = appointmentId;
    document.getElementById('modal_patient_name').textContent = patientName;
    document.getElementById('treatmentModal').classList.add('show');
}

function closeTreatmentModal() {
    document.getElementById('treatmentModal').classList.remove('show');
}

// Close modal when clicking outside
window.onclick = function(event) {
    var modal = document.getElementById('treatmentModal');
    if (event.target === modal) {
        closeTreatmentModal();
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
