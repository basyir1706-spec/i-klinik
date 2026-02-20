<?php
/**
 * Doctor Emergency Unit Page - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['doctor']);

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();
$success = '';
$error = '';

$conn = getDBConnection();

// Handle emergency report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_emergency'])) {
        $studentName = sanitize($_POST['student_name']);
        $matrixNo = sanitize($_POST['matrix_no']);
        $phone = sanitize($_POST['phone']);
        $department = sanitize($_POST['department']);
        $emergencyDescription = sanitize($_POST['emergency_description']);
        $driverId = isset($_POST['driver_id']) && !empty($_POST['driver_id']) ? (int)$_POST['driver_id'] : null;
        
        $stmt = $conn->prepare("INSERT INTO emergency_reports (student_name, matrix_no, phone, department, emergency_description, driver_id, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sssssi", $studentName, $matrixNo, $phone, $department, $emergencyDescription, $driverId);
        
        if ($stmt->execute()) {
            $reportId = $conn->insert_id;
            $success = 'Laporan kecemasan berjaya dihantar!';
            logActivity($userId, 'Emergency report created', 'Emergency report #' . $reportId);
            
            // Notify staff
            $staffStmt = $conn->prepare("SELECT id FROM users WHERE role IN ('doctor', 'staff')");
            $staffStmt->execute();
            $staffResult = $staffStmt->get_result();
            while ($staff = $staffResult->fetch_assoc()) {
                createNotification($staff['id'], 'Laporan Kecemasan Baru', 'Laporan kecemasan baru untuk ' . $studentName . ' telah diterima.', 'emergency');
            }
        } else {
            $error = 'Ralat berlaku semasa menghantar laporan';
        }
    }
    
    if (isset($_POST['update_driver_status'])) {
        $driverId = (int)$_POST['driver_id'];
        $status = sanitize($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE emergency_drivers SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $driverId);
        $stmt->execute();
        
        $success = 'Status pemandu dikemaskini!';
    }
    
    if (isset($_POST['assign_driver'])) {
        $reportId = (int)$_POST['report_id'];
        $driverId = (int)$_POST['driver_id'];
        
        $stmt = $conn->prepare("UPDATE emergency_reports SET driver_id = ?, status = 'in_progress' WHERE id = ?");
        $stmt->bind_param("ii", $driverId, $reportId);
        $stmt->execute();
        
        // Update driver status
        $stmt = $conn->prepare("UPDATE emergency_drivers SET status = 'on_duty' WHERE id = ?");
        $stmt->bind_param("i", $driverId);
        $stmt->execute();
        
        $success = 'Pemandu telah ditugaskan!';
    }
    
    if (isset($_POST['complete_emergency'])) {
        $reportId = (int)$_POST['report_id'];
        
        $stmt = $conn->prepare("UPDATE emergency_reports SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $reportId);
        $stmt->execute();
        
        // Get driver ID and update status
        $stmt = $conn->prepare("SELECT driver_id FROM emergency_reports WHERE id = ?");
        $stmt->bind_param("i", $reportId);
        $stmt->execute();
        $report = $stmt->get_result()->fetch_assoc();
        
        if ($report && $report['driver_id']) {
            $stmt = $conn->prepare("UPDATE emergency_drivers SET status = 'available' WHERE id = ?");
            $stmt->bind_param("i", $report['driver_id']);
            $stmt->execute();
        }
        
        $success = 'Kes kecemasan diselesaikan!';
    }
    
    if (isset($_POST['add_driver'])) {
        $driverUserId = (int)$_POST['driver_user_id'];
        $vehicleNumber = sanitize($_POST['vehicle_number']);
        $phone = sanitize($_POST['driver_phone']);
        
        $stmt = $conn->prepare("INSERT INTO emergency_drivers (user_id, vehicle_number, phone, status) VALUES (?, ?, ?, 'available')");
        $stmt->bind_param("iss", $driverUserId, $vehicleNumber, $phone);
        $stmt->execute();
        
        $success = 'Pemandu kecemasan berjaya ditambah!';
    }
    
    if (isset($_POST['add_trip_log'])) {
        $driverId = (int)$_POST['driver_id'];
        $emergencyReportId = isset($_POST['emergency_report_id']) && !empty($_POST['emergency_report_id']) ? (int)$_POST['emergency_report_id'] : null;
        $destination = sanitize($_POST['destination']);
        $notes = sanitize($_POST['trip_notes']);
        $departureTime = sanitize($_POST['departure_time']);
        
        $stmt = $conn->prepare("INSERT INTO driver_trip_logs (driver_id, emergency_report_id, departure_time, destination, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $driverId, $emergencyReportId, $departureTime, $destination, $notes);
        $stmt->execute();
        
        $success = 'Log perjalanan berjaya ditambah!';
    }
    
    if (isset($_POST['complete_trip'])) {
        $tripId = (int)$_POST['trip_id'];
        $arrivalTime = sanitize($_POST['arrival_time']);
        
        $stmt = $conn->prepare("UPDATE driver_trip_logs SET arrival_time = ? WHERE id = ?");
        $stmt->bind_param("si", $arrivalTime, $tripId);
        $stmt->execute();
        
        $success = 'Perjalanan diselesaikan!';
    }
}

// Get all emergency reports
$stmt = $conn->prepare("SELECT er.*, ed.vehicle_number, ed.phone as driver_phone, u.name as driver_name FROM emergency_reports er LEFT JOIN emergency_drivers ed ON er.driver_id = ed.id LEFT JOIN users u ON ed.user_id = u.id ORDER BY er.reported_at DESC");
$stmt->execute();
$emergencyReports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get pending emergency reports
$pendingReports = array_filter($emergencyReports, function($r) { return $r['status'] === 'pending'; });
$inProgressReports = array_filter($emergencyReports, function($r) { return $r['status'] === 'in_progress'; });

// Get all emergency drivers
$stmt = $conn->prepare("SELECT ed.*, u.name FROM emergency_drivers ed JOIN users u ON ed.user_id = u.id ORDER BY ed.status, u.name");
$stmt->execute();
$drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all staff for driver assignment
$stmt = $conn->prepare("SELECT id, name FROM users WHERE role = 'staff' ORDER BY name");
$stmt->execute();
$staffList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get trip logs
$stmt = $conn->prepare("SELECT dtl.*, ed.vehicle_number, u.name as driver_name, er.student_name FROM driver_trip_logs dtl JOIN emergency_drivers ed ON dtl.driver_id = ed.id JOIN users u ON ed.user_id = u.id LEFT JOIN emergency_reports er ON dtl.emergency_report_id = er.id ORDER BY dtl.created_at DESC LIMIT 50");
$stmt->execute();
$tripLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications
$totalNotifications = getUnreadNotificationCount($userId);
$pendingEmergencyCount = count($pendingReports);

// Notification badges
$emergencyNotification = $pendingEmergencyCount;
$activePage = 'emergency';

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

<!-- Emergency Report Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-ambulance"></i> Laporan Kecemasan</h3>
    </div>
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="student_name"><i class="fas fa-user"></i> Nama Pelajar</label>
                <input type="text" class="form-control" id="student_name" name="student_name" required>
            </div>
            <div class="form-group">
                <label for="matrix_no"><i class="fas fa-id-card"></i> No. Matrik</label>
                <input type="text" class="form-control" id="matrix_no" name="matrix_no">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="phone"><i class="fas fa-phone"></i> No. Telefon</label>
                <input type="text" class="form-control" id="phone" name="phone" required>
            </div>
            <div class="form-group">
                <label for="department"><i class="fas fa-building"></i> Jabatan / Kursus</label>
                <input type="text" class="form-control" id="department" name="department" required>
            </div>
        </div>
        <div class="form-group">
            <label for="emergency_description"><i class="fas fa-exclamation-triangle"></i> Deskripsi Kecemasan</label>
            <textarea class="form-control" id="emergency_description" name="emergency_description" rows="3" required></textarea>
        </div>
        <div class="form-group">
            <label for="driver_id"><i class="fas fa-car"></i> Pilih Pemandu Bertugas</label>
            <select class="form-control" id="driver_id" name="driver_id">
                <option value="">-- Pilih Pemandu (Pilihan) --</option>
                <?php foreach ($drivers as $driver): ?>
                <option value="<?php echo $driver['id']; ?>" <?php echo $driver['status'] !== 'available' ? 'disabled' : ''; ?>>
                    <?php echo htmlspecialchars($driver['name']); ?> (<?php echo $driver['vehicle_number']; ?>) - <?php echo ucfirst($driver['status']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" name="submit_emergency" class="btn btn-danger">
            <i class="fas fa-paper-plane"></i> Hantar Laporan Kecemasan
        </button>
    </form>
</div>

<!-- Pending Emergency Reports -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bell"></i> Laporan Kecemasan Pending</h3>
        <?php if (count($pendingReports) > 0): ?>
        <span class="badge badge-danger"><?php echo count($pendingReports); ?></span>
        <?php endif; ?>
    </div>
    <?php if (empty($pendingReports)): ?>
    <div class="empty-state">
        <i class="fas fa-check-circle"></i>
        <h3>Tiada Laporan Pending</h3>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Masa Laporan</th>
                    <th>Nama Pelajar</th>
                    <th>Matrik</th>
                    <th>Telefon</th>
                    <th>Jabatan</th>
                    <th>Kecemasan</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingReports as $report): ?>
                <tr>
                    <td><?php echo date('d/m/Y h:i A', strtotime($report['reported_at'])); ?></td>
                    <td><?php echo htmlspecialchars($report['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($report['matrix_no'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($report['phone']); ?></td>
                    <td><?php echo htmlspecialchars($report['department']); ?></td>
                    <td><?php echo htmlspecialchars(substr($report['emergency_description'], 0, 50)); ?><?php echo strlen($report['emergency_description']) > 50 ? '...' : ''; ?></td>
                    <td>
                        <button type="button" class="btn btn-primary btn-sm" 
                                onclick="openAssignDriverModal(<?php echo $report['id']; ?>, '<?php echo htmlspecialchars($report['student_name']); ?>')">
                            <i class="fas fa-car"></i> Tugaskan Pemandu
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- In Progress Emergency Reports -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-clock"></i> Sedang Diproses</h3>
    </div>
    <?php if (empty($inProgressReports)): ?>
    <div class="empty-state">
        <i class="fas fa-ambulance"></i>
        <h3>Tiada Kes Sedang Diproses</h3>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Pelajar</th>
                    <th>Pemandu</th>
                    <th>Kenderaan</th>
                    <th>Status</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inProgressReports as $report): ?>
                <tr>
                    <td><?php echo htmlspecialchars($report['student_name']); ?></td>
                    <td><?php echo $report['driver_name'] ?? 'Belum Ditugaskan'; ?></td>
                    <td><?php echo $report['vehicle_number'] ?? '-'; ?></td>
                    <td>
                        <span class="badge badge-info">Sedang Diproses</span>
                    </td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <button type="submit" name="complete_emergency" class="btn btn-success btn-sm"
                                    onclick="return confirm('Sahkan kes kecemasan telah diselesaikan?')">
                                <i class="fas fa-check"></i> Selesai
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Driver Schedule Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users"></i> Jadual Pemandu Bertugas</h3>
        <button type="button" class="btn btn-primary btn-sm" onclick="openAddDriverModal()">
            <i class="fas fa-plus"></i> Tambah Pemandu
        </button>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>No. Kenderaan</th>
                    <th>Telefon</th>
                    <th>Status</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($drivers as $driver): ?>
                <tr>
                    <td><?php echo htmlspecialchars($driver['name']); ?></td>
                    <td><?php echo htmlspecialchars($driver['vehicle_number'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($driver['phone']); ?></td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $driver['status'] === 'available' ? 'success' : 
                                ($driver['status'] === 'on_duty' ? 'info' : 'warning');
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $driver['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="driver_id" value="<?php echo $driver['id']; ?>">
                            <select name="status" class="form-control" style="display: inline; width: auto; padding: 5px 10px;" onchange="this.form.submit()">
                                <option value="available" <?php echo $driver['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="on_duty" <?php echo $driver['status'] === 'on_duty' ? 'selected' : ''; ?>>On Duty</option>
                                <option value="off_duty" <?php echo $driver['status'] === 'off_duty' ? 'selected' : ''; ?>>Off Duty</option>
                            </select>
                            <input type="hidden" name="update_driver_status" value="1">
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Trip Logs -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-route"></i> Log Perjalanan Pemandu</h3>
        <button type="button" class="btn btn-primary btn-sm" onclick="openAddTripModal()">
            <i class="fas fa-plus"></i> Tambah Log
        </button>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Pemandu</th>
                    <th>Kenderaan</th>
                    <th>Pelajar</th>
                    <th>Destinasi</th>
                    <th>Waktu Berlepas</th>
                    <th>Waktu Tiba</th>
                    <th>Nota</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tripLogs)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--gray-600);">
                        Tiada log perjalanan
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($tripLogs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['driver_name']); ?></td>
                    <td><?php echo htmlspecialchars($log['vehicle_number']); ?></td>
                    <td><?php echo htmlspecialchars($log['student_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($log['destination']); ?></td>
                    <td><?php echo $log['departure_time'] ? date('d/m/Y h:i A', strtotime($log['departure_time'])) : '-'; ?></td>
                    <td>
                        <?php if ($log['arrival_time']): ?>
                            <?php echo date('d/m/Y h:i A', strtotime($log['arrival_time'])); ?>
                        <?php else: ?>
                            <button type="button" class="btn btn-success btn-sm" onclick="openCompleteTripModal(<?php echo $log['id']; ?>)">
                                <i class="fas fa-check"></i> Tiba
                            </button>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($log['notes'] ?? '-'); ?></td>
                </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assign Driver Modal -->
<div class="modal" id="assignDriverModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-car"></i> Tugaskan Pemandu</h3>
            <button class="modal-close" onclick="closeAssignDriverModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="report_id" id="assign_report_id">
            <div style="margin-bottom: 20px; padding: 15px; background: rgba(0, 255, 0, 0.1); border-radius: 10px;">
                <strong>Pelajar:</strong> <span id="assign_student_name"></span>
            </div>
            <div class="form-group">
                <label for="assign_driver_id"><i class="fas fa-user"></i> Pilih Pemandu</label>
                <select class="form-control" id="assign_driver_id" name="driver_id" required>
                    <option value="">-- Pilih Pemandu --</option>
                    <?php foreach ($drivers as $driver): ?>
                    <option value="<?php echo $driver['id']; ?>" <?php echo $driver['status'] !== 'available' ? 'disabled' : ''; ?>>
                        <?php echo htmlspecialchars($driver['name']); ?> (<?php echo $driver['vehicle_number']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="assign_driver" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-check"></i> Tugaskan Pemandu
            </button>
        </form>
    </div>
</div>

<!-- Add Driver Modal -->
<div class="modal" id="addDriverModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Tambah Pemandu Kecemasan</h3>
            <button class="modal-close" onclick="closeAddDriverModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="driver_user_id"><i class="fas fa-user"></i> Pilih Staff</label>
                <select class="form-control" id="driver_user_id" name="driver_user_id" required>
                    <option value="">-- Pilih Staff --</option>
                    <?php foreach ($staffList as $staff): ?>
                    <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="vehicle_number"><i class="fas fa-car"></i> No. Kenderaan</label>
                <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" required>
            </div>
            <div class="form-group">
                <label for="driver_phone"><i class="fas fa-phone"></i> No. Telefon</label>
                <input type="text" class="form-control" id="driver_phone" name="driver_phone" required>
            </div>
            <button type="submit" name="add_driver" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-plus"></i> Tambah Pemandu
            </button>
        </form>
    </div>
</div>

<!-- Add Trip Modal -->
<div class="modal" id="addTripModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-route"></i> Tambah Log Perjalanan</h3>
            <button class="modal-close" onclick="closeAddTripModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="trip_driver_id"><i class="fas fa-user"></i> Pemandu</label>
                <select class="form-control" id="trip_driver_id" name="driver_id" required>
                    <option value="">-- Pilih Pemandu --</option>
                    <?php foreach ($drivers as $driver): ?>
                    <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="emergency_report_id"><i class="fas fa-ambulance"></i> Laporan Kecemasan (Pilihan)</label>
                <select class="form-control" id="emergency_report_id" name="emergency_report_id">
                    <option value="">-- Pilih Laporan --</option>
                    <?php foreach ($inProgressReports as $report): ?>
                    <option value="<?php echo $report['id']; ?>">
                        <?php echo htmlspecialchars($report['student_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="destination"><i class="fas fa-map-marker-alt"></i> Destinasi</label>
                <input type="text" class="form-control" id="destination" name="destination" placeholder="Contoh: Hospital Besut" required>
            </div>
            <div class="form-group">
                <label for="departure_time"><i class="fas fa-clock"></i> Waktu Berlepas</label>
                <input type="datetime-local" class="form-control" id="departure_time" name="departure_time" required>
            </div>
            <div class="form-group">
                <label for="trip_notes"><i class="fas fa-comment"></i> Nota</label>
                <textarea class="form-control" id="trip_notes" name="trip_notes" rows="2"></textarea>
            </div>
            <button type="submit" name="add_trip_log" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-plus"></i> Tambah Log
            </button>
        </form>
    </div>
</div>

<!-- Complete Trip Modal -->
<div class="modal" id="completeTripModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-flag-checkered"></i> Sahkan Ketibaan</h3>
            <button class="modal-close" onclick="closeCompleteTripModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="trip_id" id="complete_trip_id">
            <div class="form-group">
                <label for="arrival_time"><i class="fas fa-clock"></i> Waktu Tiba</label>
                <input type="datetime-local" class="form-control" id="arrival_time" name="arrival_time" required>
            </div>
            <button type="submit" name="complete_trip" class="btn btn-success" style="width: 100%;">
                <i class="fas fa-check"></i> Sahkan Tiba
            </button>
        </form>
    </div>
</div>

<script>
function openAssignDriverModal(reportId, studentName) {
    document.getElementById('assign_report_id').value = reportId;
    document.getElementById('assign_student_name').textContent = studentName;
    document.getElementById('assignDriverModal').classList.add('show');
}

function closeAssignDriverModal() {
    document.getElementById('assignDriverModal').classList.remove('show');
}

function openAddDriverModal() {
    document.getElementById('addDriverModal').classList.add('show');
}

function closeAddDriverModal() {
    document.getElementById('addDriverModal').classList.remove('show');
}

function openAddTripModal() {
    // Set default departure time to now
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('departure_time').value = now.toISOString().slice(0, 16);
    document.getElementById('addTripModal').classList.add('show');
}

function closeAddTripModal() {
    document.getElementById('addTripModal').classList.remove('show');
}

function openCompleteTripModal(tripId) {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('arrival_time').value = now.toISOString().slice(0, 16);
    document.getElementById('complete_trip_id').value = tripId;
    document.getElementById('completeTripModal').classList.add('show');
}

function closeCompleteTripModal() {
    document.getElementById('completeTripModal').classList.remove('show');
}

// Close modals when clicking outside
window.onclick = function(event) {
    var modals = document.getElementsByClassName('modal');
    for (var i = 0; i < modals.length; i++) {
        if (event.target === modals[i]) {
            modals[i].classList.remove('show');
        }
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
