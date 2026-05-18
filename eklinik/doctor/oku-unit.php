<?php
/**
 * Doctor OKU Unit Page - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['doctor']);

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();
$success = '';
$error = '';

$conn = getDBConnection();

// Handle approve/reject OKU equipment loan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_loan'])) {
        $loanId = (int)$_POST['loan_id'];
        $equipmentId = (int)$_POST['equipment_id'];
        $quantity = (int)$_POST['quantity'];
        
        // Update loan status
        $stmt = $conn->prepare("UPDATE oku_equipment_loans SET status = 'approved', approved_by = ?, return_date = DATE_ADD(borrow_date, INTERVAL 14 DAY) WHERE id = ?");
        $stmt->bind_param("ii", $userId, $loanId);
        $stmt->execute();
        
        // Update equipment available quantity
        $stmt = $conn->prepare("UPDATE oku_equipment SET available_quantity = available_quantity - ? WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $equipmentId);
        $stmt->execute();
        
        $success = 'Permohonan pinjaman diluluskan!';
        
        // Get loan info for notification
        $stmt = $conn->prepare("SELECT user_id FROM oku_equipment_loans WHERE id = ?");
        $stmt->bind_param("i", $loanId);
        $stmt->execute();
        $loanUser = $stmt->get_result()->fetch_assoc();
        createNotification($loanUser['user_id'], 'Pinjaman OKU Diluluskan', 'Permohonan pinjaman peralatan OKU anda telah diluluskan. Sila ambil di klinik.', 'oku');
    }
    
    if (isset($_POST['reject_loan'])) {
        $loanId = (int)$_POST['loan_id'];
        $reason = sanitize($_POST['rejection_reason']);
        
        $stmt = $conn->prepare("UPDATE oku_equipment_loans SET status = 'rejected', approved_by = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("isi", $userId, $reason, $loanId);
        $stmt->execute();
        
        $success = 'Permohonan pinjaman ditolak';
        
        // Get loan info for notification
        $stmt = $conn->prepare("SELECT user_id FROM oku_equipment_loans WHERE id = ?");
        $stmt->bind_param("i", $loanId);
        $stmt->execute();
        $loanUser = $stmt->get_result()->fetch_assoc();
        createNotification($loanUser['user_id'], 'Pinjaman OKU Ditolak', 'Permohonan pinjaman peralatan OKU anda telah ditolak. Sebab: ' . $reason, 'oku');
    }
    
    if (isset($_POST['return_equipment'])) {
        $loanId = (int)$_POST['loan_id'];
        $equipmentId = (int)$_POST['equipment_id'];
        $quantity = (int)$_POST['quantity'];
        
        // Update loan status
        $stmt = $conn->prepare("UPDATE oku_equipment_loans SET status = 'returned' WHERE id = ?");
        $stmt->bind_param("i", $loanId);
        $stmt->execute();
        
        // Update equipment available quantity
        $stmt = $conn->prepare("UPDATE oku_equipment SET available_quantity = available_quantity + ? WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $equipmentId);
        $stmt->execute();
        
        $success = 'Peralatan telah dipulangkan!';
    }
    
    if (isset($_POST['update_equipment'])) {
        $equipmentId = (int)$_POST['equipment_id'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $category = sanitize($_POST['category']);
        $quantity = (int)$_POST['quantity'];
        
        $stmt = $conn->prepare("UPDATE oku_equipment SET name = ?, description = ?, category = ?, quantity = ? WHERE id = ?");
        $stmt->bind_param("sssii", $name, $description, $category, $quantity, $equipmentId);
        $stmt->execute();
        
        $success = 'Peralatan OKU berjaya dikemaskini!';
    }
    
    if (isset($_POST['add_equipment'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $category = sanitize($_POST['category']);
        $quantity = (int)$_POST['quantity'];
        
        $stmt = $conn->prepare("INSERT INTO oku_equipment (name, description, category, quantity, available_quantity) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $name, $description, $category, $quantity, $quantity);
        $stmt->execute();
        
        $success = 'Peralatan OKU berjaya ditambah!';
    }
}

// Get pending OKU loans
$stmt = $conn->prepare("SELECT oel.*, e.name as equipment_name, e.category, u.name as user_name, u.matrix_no FROM oku_equipment_loans oel JOIN oku_equipment e ON oel.equipment_id = e.id JOIN users u ON oel.user_id = u.id WHERE oel.status = 'pending' ORDER BY oel.created_at DESC");
$stmt->execute();
$pendingLoans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get active OKU loans
$stmt = $conn->prepare("SELECT oel.*, e.name as equipment_name, e.category, u.name as user_name, u.matrix_no FROM oku_equipment_loans oel JOIN oku_equipment e ON oel.equipment_id = e.id JOIN users u ON oel.user_id = u.id WHERE oel.status = 'approved' ORDER BY oel.borrow_date DESC");
$stmt->execute();
$activeLoans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all OKU equipment
$stmt = $conn->prepare("SELECT * FROM oku_equipment ORDER BY category, name");
$stmt->execute();
$okuEquipment = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications
$totalNotifications = getUnreadNotificationCount($userId);
$pendingOKULoans = count($pendingLoans);

// Notification badges
$okuNotification = $pendingOKULoans;
$activePage = 'oku-unit';

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

<!-- Pending OKU Loan Requests -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bell"></i> Permohonan Pinjaman OKU (Perlu Kelulusan)</h3>
        <?php if (count($pendingLoans) > 0): ?>
        <span class="badge badge-danger"><?php echo count($pendingLoans); ?></span>
        <?php endif; ?>
    </div>
    <?php if (empty($pendingLoans)): ?>
    <div class="empty-state">
        <i class="fas fa-check-circle"></i>
        <h3>Tiada Permohonan Pending</h3>
        <p>Semua permohonan telah diproses</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Pemohon</th>
                    <th>Matrik/ID</th>
                    <th>Peralatan</th>
                    <th>Kategori</th>
                    <th>Kuantiti</th>
                    <th>Tarikh Pinjam</th>
                    <th>Nota</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingLoans as $loan): ?>
                <tr>
                    <td><?php echo htmlspecialchars($loan['user_name']); ?></td>
                    <td><?php echo htmlspecialchars(($loan['matrix_no'] ?? '-')); ?></td>
                    <td><?php echo htmlspecialchars($loan['equipment_name']); ?></td>
                    <td><?php echo htmlspecialchars(($loan['category'] ?? '-')); ?></td>
                    <td><?php echo $loan['quantity']; ?></td>
                    <td><?php echo formatDate($loan['borrow_date']); ?></td>
                    <td><?php echo htmlspecialchars(($loan['notes'] ?? '-')); ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                            <input type="hidden" name="equipment_id" value="<?php echo $loan['equipment_id']; ?>">
                            <input type="hidden" name="quantity" value="<?php echo $loan['quantity']; ?>">
                            <button type="submit" name="approve_loan" class="btn btn-success btn-sm" 
                                    onclick="return confirm('Luluskan permohonan ini?')">
                                <i class="fas fa-check"></i> Lulus
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger btn-sm" 
                                onclick="openRejectModal(<?php echo $loan['id']; ?>)">
                            <i class="fas fa-times"></i> Tolak
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Active OKU Loans -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-clock"></i> Pinjaman Aktif</h3>
    </div>
    <?php if (empty($activeLoans)): ?>
    <div class="empty-state">
        <i class="fas fa-box-open"></i>
        <h3>Tiada Pinjaman Aktif</h3>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Peminjam</th>
                    <th>Matrik/ID</th>
                    <th>Peralatan</th>
                    <th>Kuantiti</th>
                    <th>Tarikh Pinjam</th>
                    <th>Tarikh Pulang</th>
                    <th>Status</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeLoans as $loan): ?>
                <tr>
                    <td><?php echo htmlspecialchars($loan['user_name']); ?></td>
                    <td><?php echo htmlspecialchars(($loan['matrix_no'] ?? '-')); ?></td>
                    <td><?php echo htmlspecialchars($loan['equipment_name']); ?></td>
                    <td><?php echo $loan['quantity']; ?></td>
                    <td><?php echo formatDate($loan['borrow_date']); ?></td>
                    <td><?php echo $loan['return_date'] ? formatDate($loan['return_date']) : '-'; ?></td>
                    <td>
                        <span class="badge badge-info">Dipinjam</span>
                    </td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                            <input type="hidden" name="equipment_id" value="<?php echo $loan['equipment_id']; ?>">
                            <input type="hidden" name="quantity" value="<?php echo $loan['quantity']; ?>">
                            <button type="submit" name="return_equipment" class="btn btn-success btn-sm"
                                    onclick="return confirm('Sahkan peralatan telah dipulangkan?')">
                                <i class="fas fa-undo"></i> Pulang
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

<!-- OKU Equipment List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-wheelchair"></i> Senarai Peralatan OKU</h3>
        <button type="button" class="btn btn-primary btn-sm" onclick="openAddEquipmentModal()">
            <i class="fas fa-plus"></i> Tambah Peralatan
        </button>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th>Kuantiti</th>
                    <th>Tersedia</th>
                    <th>Status</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($okuEquipment as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars(($item['category'] ?? '-')); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo $item['available_quantity']; ?></td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $item['available_quantity'] > 0 ? 'success' : 'danger';
                        ?>">
                            <?php echo ucfirst($item['status']); ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-info btn-sm" 
                                onclick="openEditEquipmentModal(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal" id="rejectModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle"></i> Tolak Permohonan</h3>
            <button class="modal-close" onclick="closeRejectModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="loan_id" id="reject_loan_id">
            <div class="form-group">
                <label for="rejection_reason"><i class="fas fa-comment"></i> Sebab Penolakan</label>
                <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
            </div>
            <button type="submit" name="reject_loan" class="btn btn-danger" style="width: 100%;">
                <i class="fas fa-times"></i> Tolak Permohonan
            </button>
        </form>
    </div>
</div>

<!-- Add Equipment Modal -->
<div class="modal" id="addEquipmentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Tambah Peralatan OKU</h3>
            <button class="modal-close" onclick="closeAddEquipmentModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="add_name"><i class="fas fa-box"></i> Nama Peralatan</label>
                <input type="text" class="form-control" id="add_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="add_category"><i class="fas fa-tag"></i> Kategori</label>
                <input type="text" class="form-control" id="add_category" name="category" required>
            </div>
            <div class="form-group">
                <label for="add_quantity"><i class="fas fa-hashtag"></i> Kuantiti</label>
                <input type="number" class="form-control" id="add_quantity" name="quantity" min="1" required>
            </div>
            <div class="form-group">
                <label for="add_description"><i class="fas fa-comment"></i> Penerangan</label>
                <textarea class="form-control" id="add_description" name="description" rows="3"></textarea>
            </div>
            <button type="submit" name="add_equipment" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-plus"></i> Tambah Peralatan
            </button>
        </form>
    </div>
</div>

<!-- Edit Equipment Modal -->
<div class="modal" id="editEquipmentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Peralatan OKU</h3>
            <button class="modal-close" onclick="closeEditEquipmentModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="equipment_id" id="edit_equipment_id">
            <div class="form-group">
                <label for="edit_name"><i class="fas fa-box"></i> Nama Peralatan</label>
                <input type="text" class="form-control" id="edit_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="edit_category"><i class="fas fa-tag"></i> Kategori</label>
                <input type="text" class="form-control" id="edit_category" name="category" required>
            </div>
            <div class="form-group">
                <label for="edit_quantity"><i class="fas fa-hashtag"></i> Kuantiti</label>
                <input type="number" class="form-control" id="edit_quantity" name="quantity" min="1" required>
            </div>
            <div class="form-group">
                <label for="edit_description"><i class="fas fa-comment"></i> Penerangan</label>
                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
            </div>
            <button type="submit" name="update_equipment" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> Kemaskini Peralatan
            </button>
        </form>
    </div>
</div>

<script>
function openRejectModal(loanId) {
    document.getElementById('reject_loan_id').value = loanId;
    document.getElementById('rejectModal').classList.add('show');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('show');
}

function openAddEquipmentModal() {
    document.getElementById('addEquipmentModal').classList.add('show');
}

function closeAddEquipmentModal() {
    document.getElementById('addEquipmentModal').classList.remove('show');
}

function openEditEquipmentModal(equipment) {
    document.getElementById('edit_equipment_id').value = equipment.id;
    document.getElementById('edit_name').value = equipment.name;
    document.getElementById('edit_category').value = equipment.category || '';
    document.getElementById('edit_quantity').value = equipment.quantity;
    document.getElementById('edit_description').value = equipment.description || '';
    document.getElementById('editEquipmentModal').classList.add('show');
}

function closeEditEquipmentModal() {
    document.getElementById('editEquipmentModal').classList.remove('show');
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
