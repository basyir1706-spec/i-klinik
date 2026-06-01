<?php
/**
 * Doctor Patient Management Page - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['doctor']);

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();
$success = '';
$error = '';

$conn = getDBConnection();

// Handle update patient info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    $patientId = (int)$_POST['patient_id'];
    $phone = sanitize($_POST['phone']);
    $parentPhone = sanitize($_POST['parent_phone']);
    $bloodType = sanitize($_POST['blood_type']);
    $department = sanitize($_POST['department']);
    $course = sanitize($_POST['course']);
    $okuDisability = sanitize($_POST['oku_disability']);
    
    $stmt = $conn->prepare("UPDATE users SET phone = ?, parent_phone = ?, blood_type = ?, department = ?, course = ?, oku_disability = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $phone, $parentPhone, $bloodType, $department, $course, $okuDisability, $patientId);
    
    if ($stmt->execute()) {
        $success = 'Maklumat pesakit berjaya dikemaskini!';
        logActivity($userId, 'Patient info updated', 'Updated info for patient ID ' . $patientId);
    } else {
        $error = 'Ralat berlaku semasa mengemaskini maklumat';
    }
}

// Search functionality
$searchQuery = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchQuery = sanitize($_GET['search']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'student' AND (name LIKE ? OR matrix_no LIKE ? OR ic_number LIKE ?) ORDER BY name ASC");
    $searchParam = "%{$searchQuery}%";
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'student' ORDER BY name ASC LIMIT 100");
    $stmt->execute();
}
$allPatients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get patient list with last visit
$stmt = $conn->prepare("SELECT u.*, MAX(a.appointment_date) as last_visit, COUNT(a.id) as total_visits
    FROM users u
    LEFT JOIN appointments a ON u.id = a.user_id
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY last_visit IS NULL, last_visit DESC");
$stmt->execute();
$patientList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications
$totalNotifications = getUnreadNotificationCount($userId);

// Notification badges
$activePage = 'patient';

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

<!-- Search Box -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-search"></i> Cari Warga Polibesut</h3>
    </div>
    <div class="search-box">
        <form method="GET" action="">
            <input type="text" name="search" placeholder="Cari nama, nombor matrik, atau IC..." 
                   value="<?php echo htmlspecialchars($searchQuery); ?>">
            <button type="submit" class="btn btn-primary btn-sm" style="position: absolute; right: 5px; top: 5px;">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>
</div>

<!-- Patient List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-procedures"></i> Senarai Patient</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Phone</th>
                    <th>IC Number</th>
                    <th>Blood Type</th>
                    <th>Last Visit</th>
                    <th>Total Visits</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patientList)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--gray-600);">
                        Tiada data pesakit
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($patientList as $patient): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($patient['name']); ?>
                        <?php if ($patient['is_oku']): ?>
                            <span class="badge badge-info">OKU</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars(!empty($patient['phone']) ? $patient['phone'] : '-'); ?></td>
                    <td><?php echo htmlspecialchars(!empty($patient['ic_number']) ? $patient['ic_number'] : '-'); ?></td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $patient['blood_type'] === 'O-' ? 'danger' : 'primary';
                        ?>">
                            <?php echo !empty($patient['blood_type']) ? $patient['blood_type'] : '-'; ?>
                        </span>
                    </td>
                    <td><?php echo $patient['last_visit'] ? formatDate($patient['last_visit']) : 'Never'; ?></td>
                    <td><?php echo $patient['total_visits'] ?? 0; ?></td>
                    <td>
                        <button type="button" class="btn btn-info btn-sm" 
                                onclick="openPatientModal(<?php echo htmlspecialchars(json_encode($patient)); ?>)">
                            <i class="fas fa-eye"></i> Lihat
                        </button>
                    </td>
                </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Patient Detail Modal -->
<div class="modal" id="patientModal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-user"></i> Maklumat Pesakit</h3>
            <button class="modal-close" onclick="closePatientModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="patient_id" id="modal_patient_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nama</label>
                    <input type="text" class="form-control" id="modal_name" disabled>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Nombor Matrik/ID</label>
                    <input type="text" class="form-control" id="modal_matrix_no" disabled>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> No. Telefon</label>
                    <input type="text" class="form-control" id="modal_phone" name="phone">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone-alt"></i> No. Tel Ibu Bapa/Penjaga</label>
                    <input type="text" class="form-control" id="modal_parent_phone" name="parent_phone">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-id-badge"></i> No. IC</label>
                    <input type="text" class="form-control" id="modal_ic_number" disabled>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-tint"></i> Jenis Darah</label>
                    <select class="form-control" id="modal_blood_type" name="blood_type">
                        <option value="">Pilih</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Jabatan / Kursus</label>
                    <input type="text" class="form-control" id="modal_department" name="department">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-graduation-cap"></i> Kursus</label>
                    <input type="text" class="form-control" id="modal_course" name="course">
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-wheelchair"></i> Status Penyakit / Kecacatan (OKU)</label>
                <textarea class="form-control" id="modal_oku_disability" name="oku_disability" rows="2"></textarea>
            </div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(0, 255, 0, 0.2);">
                <h4 style="color: var(--primary-green); margin-bottom: 15px;">
                    <i class="fas fa-history"></i> Sejarah Rawatan
                </h4>
                <div id="medical_history" style="max-height: 200px; overflow-y: auto;">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
            
            <button type="submit" name="update_patient" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
                <i class="fas fa-save"></i> Kemaskini Maklumat
            </button>
        </form>
    </div>
</div>

<script>
function openPatientModal(patient) {
    document.getElementById('modal_patient_id').value = patient.id;
    document.getElementById('modal_name').value = patient.name;
    document.getElementById('modal_matrix_no').value = patient.matrix_no || '-';
    document.getElementById('modal_phone').value = patient.phone || '';
    document.getElementById('modal_parent_phone').value = patient.parent_phone || '';
    document.getElementById('modal_ic_number').value = patient.ic_number || '-';
    document.getElementById('modal_blood_type').value = patient.blood_type || '';
    document.getElementById('modal_department').value = patient.department || '';
    document.getElementById('modal_course').value = patient.course || '';
    document.getElementById('modal_oku_disability').value = patient.oku_disability || '';
    
    document.getElementById('patientModal').classList.add('show');
}

function closePatientModal() {
    document.getElementById('patientModal').classList.remove('show');
}

// Close modal when clicking outside
window.onclick = function(event) {
    var modal = document.getElementById('patientModal');
    if (event.target === modal) {
        closePatientModal();
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
