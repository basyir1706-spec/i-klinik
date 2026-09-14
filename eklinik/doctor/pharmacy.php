<?php
/**
 * Doctor Pharmacy Page - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['doctor']);

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();
$success = '';
$error = '';

$conn = getDBConnection();

// Handle add new medicine
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_medicine'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $stockQuantity = (int)$_POST['stock_quantity'];
        $price = (float)$_POST['price'];
        $expiryDate = sanitize($_POST['expiry_date']);
        $category = sanitize($_POST['category']);
        
        // Determine status
        $status = 'available';
        $today = date('Y-m-d');
        if ($stockQuantity == 0) {
            $status = 'out_of_stock';
        } elseif ($stockQuantity < 10) {
            $status = 'low_stock';
        }
        if ($expiryDate < $today) {
            $status = 'expired';
        }
        
        $stmt = $conn->prepare("INSERT INTO medicines (name, description, stock_quantity, price, expiry_date, category, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssidsss", $name, $description, $stockQuantity, $price, $expiryDate, $category, $status);
        
        if ($stmt->execute()) {
            $success = 'Ubat berjaya ditambah!';
            logActivity($userId, 'Medicine added', 'Added new medicine: ' . $name);
        } else {
            $error = 'Ralat berlaku semasa menambah ubat';
        }
    }
    
    if (isset($_POST['update_medicine'])) {
        $medicineId = (int)$_POST['medicine_id'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $stockQuantity = (int)$_POST['stock_quantity'];
        $price = (float)$_POST['price'];
        $expiryDate = sanitize($_POST['expiry_date']);
        $category = sanitize($_POST['category']);
        
        // Determine status
        $status = 'available';
        $today = date('Y-m-d');
        if ($stockQuantity == 0) {
            $status = 'out_of_stock';
        } elseif ($stockQuantity < 10) {
            $status = 'low_stock';
        }
        if ($expiryDate < $today) {
            $status = 'expired';
        }
        
        $stmt = $conn->prepare("UPDATE medicines SET name = ?, description = ?, stock_quantity = ?, price = ?, expiry_date = ?, category = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssidsssi", $name, $description, $stockQuantity, $price, $expiryDate, $category, $status, $medicineId);
        
        if ($stmt->execute()) {
            $success = 'Ubat berjaya dikemaskini!';
            logActivity($userId, 'Medicine updated', 'Updated medicine ID: ' . $medicineId);
        } else {
            $error = 'Ralat berlaku semasa mengemaskini ubat';
        }
    }
    
    if (isset($_POST['dispense_medicine'])) {
        $medicineId = (int)$_POST['medicine_id'];
        $patientId = (int)$_POST['patient_id'];
        $quantity = (int)$_POST['dispense_quantity'];
        $dosage = sanitize($_POST['dosage']);
        $instructions = sanitize($_POST['instructions']);
        $appointmentId = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
        
        // Check stock
        $stmt = $conn->prepare("SELECT stock_quantity FROM medicines WHERE id = ?");
        $stmt->bind_param("i", $medicineId);
        $stmt->execute();
        $medicine = $stmt->get_result()->fetch_assoc();
        
        if ($medicine && $medicine['stock_quantity'] >= $quantity) {
            // Deduct stock
            $stmt = $conn->prepare("UPDATE medicines SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $stmt->bind_param("ii", $quantity, $medicineId);
            $stmt->execute();
            
            // Create dispensing record
            $stmt = $conn->prepare("INSERT INTO medicine_dispensing (appointment_id, medicine_id, patient_id, quantity, dosage, instructions, dispensed_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiissi", $appointmentId, $medicineId, $patientId, $quantity, $dosage, $instructions, $userId);
            $stmt->execute();
            
            $success = 'Ubat berjaya diberikan kepada pesakit!';
            
            // Notify patient
            createNotification($patientId, 'Ubat Diberikan', 'Anda telah menerima ubat dari farmasi. Sila ikut arahan pengambilan.', 'pharmacy');
        } else {
            $error = 'Stok ubat tidak mencukupi';
        }
    }
}

// Get all medicines
$stmt = $conn->prepare("SELECT * FROM medicines ORDER BY category, name");
$stmt->execute();
$medicines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all students for dispensing
$stmt = $conn->prepare("SELECT id, name, matrix_no FROM users WHERE role = 'student' ORDER BY name");
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get today's appointments for dispensing
$stmt = $conn->prepare("SELECT a.id, a.queue_number, u.name, u.matrix_no FROM appointments a JOIN users u ON a.user_id = u.id WHERE a.appointment_date = CURDATE() AND a.status = 'completed' ORDER BY a.queue_number");
$stmt->execute();
$completedAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent dispensing records
$stmt = $conn->prepare("SELECT md.*, m.name as medicine_name, u.name as patient_name, u2.name as dispensed_by_name FROM medicine_dispensing md JOIN medicines m ON md.medicine_id = m.id JOIN users u ON md.patient_id = u.id LEFT JOIN users u2 ON md.dispensed_by = u2.id ORDER BY md.dispensed_at DESC LIMIT 20");
$stmt->execute();
$dispensingRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications
$totalNotifications = getUnreadNotificationCount($userId);

// Notification badges
$activePage = 'pharmacy';

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

<!-- Add New Medicine Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle"></i> Tambah Ubat Baru</h3>
    </div>
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="name"><i class="fas fa-pills"></i> Nama Ubat</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="category"><i class="fas fa-tag"></i> Kategori</label>
                <input type="text" class="form-control" id="category" name="category" placeholder="Contoh: Painkiller, Antibiotic">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="stock_quantity"><i class="fas fa-box"></i> Stok</label>
                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" min="0" required>
            </div>
            <div class="form-group">
                <label for="price"><i class="fas fa-ringgit"></i> Harga (RM)</label>
                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="0.00">
            </div>
            <div class="form-group">
                <label for="expiry_date"><i class="fas fa-calendar"></i> Tarikh Luput</label>
                <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
            </div>
        </div>
        <div class="form-group">
            <label for="description"><i class="fas fa-comment"></i> Penerangan</label>
            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
        </div>
        <button type="submit" name="add_medicine" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Ubat
        </button>
    </form>
</div>

<!-- Medicine Inventory -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-pills"></i> Medicine Inventory</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Medicine Name</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Price (RM)</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($medicines)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--gray-600);">
                        Tiada ubat dalam inventori
                    </td>
                </tr>
                <?php else: ?>
                    <?php 
                    $currentCategory = '';
                    foreach ($medicines as $med): 
                        if ($currentCategory !== $med['category']):
                            if ($currentCategory !== '') echo '</tbody><tbody>';
                            echo '<tr><td colspan="7" style="background: rgba(0, 255, 0, 0.1); font-weight: bold; color: var(--primary-green);">' . htmlspecialchars($med['category'] ?? 'Lain-lain') . '</td></tr>';
                            $currentCategory = $med['category'];
                        endif;
                    ?>
                <tr>
                    <td><?php echo htmlspecialchars($med['name']); ?></td>
                    <td><?php echo htmlspecialchars(($med['category'] ?? '-')); ?></td>
                    <td><?php echo $med['stock_quantity']; ?></td>
                    <td>RM <?php echo number_format($med['price'], 2); ?></td>
                    <td><?php echo formatDate($med['expiry_date']); ?></td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $med['status'] === 'available' ? 'success' : 
                                ($med['status'] === 'low_stock' ? 'warning' : 
                                ($med['status'] === 'expired' ? 'danger' : 'danger'));
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $med['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-info btn-sm" onclick="openEditMedicineModal(<?php echo htmlspecialchars(json_encode($med)); ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="openDispenseModal(<?php echo $med['id']; ?>, '<?php echo htmlspecialchars($med['name']); ?>', <?php echo $med['stock_quantity']; ?>)">
                            <i class="fas fa-hand-holding-medical"></i> Beri
                        </button>
                    </td>
                </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Dispensing Records -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> Rekod Pemberian Ubat</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tarikh</th>
                    <th>Pesakit</th>
                    <th>Ubat</th>
                    <th>Kuantiti</th>
                    <th>Dosage</th>
                    <th>Diberikan Oleh</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dispensingRecords)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--gray-600);">
                        Tiada rekod pemberian ubat
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($dispensingRecords as $record): ?>
                <tr>
                    <td><?php echo formatDate($record['dispensed_at']); ?></td>
                    <td><?php echo htmlspecialchars($record['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($record['medicine_name']); ?></td>
                    <td><?php echo $record['quantity']; ?></td>
                    <td><?php echo htmlspecialchars(($record['dosage'] ?? '-')); ?></td>
                    <td><?php echo htmlspecialchars(($record['dispensed_by_name'] ?? '-')); ?></td>
                </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Medicine Modal -->
<div class="modal" id="editMedicineModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Ubat</h3>
            <button class="modal-close" onclick="closeEditMedicineModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="medicine_id" id="edit_medicine_id">
            <div class="form-group">
                <label for="edit_name"><i class="fas fa-pills"></i> Nama Ubat</label>
                <input type="text" class="form-control" id="edit_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="edit_category"><i class="fas fa-tag"></i> Kategori</label>
                <input type="text" class="form-control" id="edit_category" name="category">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_stock_quantity"><i class="fas fa-box"></i> Stok</label>
                    <input type="number" class="form-control" id="edit_stock_quantity" name="stock_quantity" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_price"><i class="fas fa-ringgit"></i> Harga (RM)</label>
                    <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_expiry_date"><i class="fas fa-calendar"></i> Tarikh Luput</label>
                    <input type="date" class="form-control" id="edit_expiry_date" name="expiry_date" required>
                </div>
            </div>
            <div class="form-group">
                <label for="edit_description"><i class="fas fa-comment"></i> Penerangan</label>
                <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
            </div>
            <button type="submit" name="update_medicine" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> Kemaskini Ubat
            </button>
        </form>
    </div>
</div>

<!-- Dispense Medicine Modal -->
<div class="modal" id="dispenseModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-hand-holding-medical"></i> Berikan Ubat</h3>
            <button class="modal-close" onclick="closeDispenseModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="medicine_id" id="dispense_medicine_id">
            <div style="margin-bottom: 20px; padding: 15px; background: rgba(0, 255, 0, 0.1); border-radius: 10px;">
                <strong>Ubat:</strong> <span id="dispense_medicine_name"></span>
                <br><strong>Stok Tersedia:</strong> <span id="dispense_stock"></span>
            </div>
            
            <div class="form-group">
                <label for="appointment_id"><i class="fas fa-calendar-check"></i> Temujanji (Pilihan)</label>
                <select class="form-control" id="appointment_id" name="appointment_id">
                    <option value="">-- Pilih Temujanji --</option>
                    <?php foreach ($completedAppointments as $apt): ?>
                    <option value="<?php echo $apt['id']; ?>">
                        #<?php echo $apt['queue_number']; ?> - <?php echo htmlspecialchars($apt['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="patient_id"><i class="fas fa-user"></i> Pesakit</label>
                <select class="form-control" id="patient_id" name="patient_id" required>
                    <option value="">-- Pilih Pesakit --</option>
                    <?php foreach ($students as $student): ?>
                    <option value="<?php echo $student['id']; ?>">
                        <?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['matrix_no']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="dispense_quantity"><i class="fas fa-hashtag"></i> Kuantiti</label>
                <input type="number" class="form-control" id="dispense_quantity" name="dispense_quantity" min="1" required>
            </div>
            
            <div class="form-group">
                <label for="dosage"><i class="fas fa-prescription"></i> Dosage / Arahan</label>
                <input type="text" class="form-control" id="dosage" name="dosage" placeholder="Contoh: 1 tablet 3 kali sehari">
            </div>
            
            <div class="form-group">
                <label for="instructions"><i class="fas fa-comment"></i> Arahan Tambahan</label>
                <textarea class="form-control" id="instructions" name="instructions" rows="2" placeholder="Contoh: Ambil selepas makan"></textarea>
            </div>
            
            <button type="submit" name="dispense_medicine" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-check"></i> Berikan Ubat
            </button>
        </form>
    </div>
</div>

<script>
function openEditMedicineModal(medicine) {
    document.getElementById('edit_medicine_id').value = medicine.id;
    document.getElementById('edit_name').value = medicine.name;
    document.getElementById('edit_category').value = medicine.category || '';
    document.getElementById('edit_stock_quantity').value = medicine.stock_quantity;
    document.getElementById('edit_price').value = medicine.price;
    document.getElementById('edit_expiry_date').value = medicine.expiry_date;
    document.getElementById('edit_description').value = medicine.description || '';
    document.getElementById('editMedicineModal').classList.add('show');
}

function closeEditMedicineModal() {
    document.getElementById('editMedicineModal').classList.remove('show');
}

function openDispenseModal(medicineId, medicineName, stock) {
    document.getElementById('dispense_medicine_id').value = medicineId;
    document.getElementById('dispense_medicine_name').textContent = medicineName;
    document.getElementById('dispense_stock').textContent = stock;
    document.getElementById('dispense_quantity').max = stock;
    document.getElementById('dispenseModal').classList.add('show');
}

function closeDispenseModal() {
    document.getElementById('dispenseModal').classList.remove('show');
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
