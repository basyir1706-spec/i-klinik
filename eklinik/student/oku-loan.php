<?php
/**
 * Student OKU Equipment Loan Page - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['student', 'staff']);

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();
$isOKU = isset($_SESSION['is_oku']) ? $_SESSION['is_oku'] : false;
$success = '';
$error = '';

$conn = getDBConnection();

// Handle form submission for OKU equipment loan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_loan'])) {
    $equipmentId = (int)$_POST['equipment_id'];
    $quantity = (int)$_POST['quantity'];
    $borrowDate = sanitize($_POST['borrow_date']);
    $notes = sanitize($_POST['notes']);
    
    if (empty($borrowDate)) {
        $borrowDate = date('Y-m-d');
    }
    
    // Check equipment availability
    $stmt = $conn->prepare("SELECT * FROM oku_equipment WHERE id = ?");
    $stmt->bind_param("i", $equipmentId);
    $stmt->execute();
    $equipment = $stmt->get_result()->fetch_assoc();
    
    if ($equipment && $equipment['available_quantity'] >= $quantity) {
        $stmt = $conn->prepare("INSERT INTO oku_equipment_loans (user_id, equipment_id, quantity, borrow_date, notes, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iiiss", $userId, $equipmentId, $quantity, $borrowDate, $notes);
        
        if ($stmt->execute()) {
            $success = 'Permohonan pinjaman peralatan OKU berjaya dihantar!';
            logActivity($userId, 'OKU equipment loan requested', 'Requested ' . $quantity . ' x ' . $equipment['name']);
            
            // Notify staff/doctors
            $staffStmt = $conn->prepare("SELECT id FROM users WHERE role IN ('doctor', 'staff')");
            $staffStmt->execute();
            $staffResult = $staffStmt->get_result();
            while ($staff = $staffResult->fetch_assoc()) {
                createNotification($staff['id'], 'Permohonan Pinjaman OKU', $userName . ' memohon ' . $quantity . ' x ' . $equipment['name'], 'oku');
            }
        } else {
            $error = 'Ralat berlaku semasa menghantar permohonan';
        }
    } else {
        $error = 'Peralatan tidak mencukupi';
    }
}

// Get all OKU equipment
$stmt = $conn->prepare("SELECT * FROM oku_equipment ORDER BY category, name");
$stmt->execute();
$okuEquipment = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's OKU equipment loans
$stmt = $conn->prepare("SELECT oel.*, e.name as equipment_name, e.category, u.name as approved_by_name FROM oku_equipment_loans oel JOIN oku_equipment e ON oel.equipment_id = e.id LEFT JOIN users u ON oel.approved_by = u.id WHERE oel.user_id = ? ORDER BY oel.created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$okuLoans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications
$totalNotifications = getUnreadNotificationCount($userId);

// Count pending loans
$pendingLoans = 0;
foreach ($okuLoans as $loan) {
    if ($loan['status'] === 'pending') $pendingLoans++;
}

// Notification badges
$okuNotification = $pendingLoans;
$activePage = 'oku-loan';

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

<!-- Request OKU Equipment Loan Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-wheelchair"></i> Pinjam Peralatan OKU</h3>
    </div>
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="equipment_id"><i class="fas fa-box"></i> Pilih Peralatan</label>
                <select class="form-control" id="equipment_id" name="equipment_id" required>
                    <option value="">-- Pilih Peralatan --</option>
                    <?php 
                    $currentCategory = '';
                    foreach ($okuEquipment as $item): 
                        if ($currentCategory !== $item['category']):
                            if ($currentCategory !== '') echo '</optgroup>';
                            echo '<optgroup label="' . htmlspecialchars((isset($item['category']) ? $item['category'] : 'Lain-lain')) . '">';
                            $currentCategory = $item['category'];
                        endif;
                    ?>
                    <option value="<?php echo $item['id']; ?>" data-available="<?php echo $item['available_quantity']; ?>">
                        <?php echo htmlspecialchars($item['name']); ?> (Tersedia: <?php echo $item['available_quantity']; ?>)
                    </option>
                    <?php endforeach; ?>
                    <?php if ($currentCategory !== '') echo '</optgroup>'; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="quantity"><i class="fas fa-hashtag"></i> Kuantiti</label>
                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="borrow_date"><i class="fas fa-calendar"></i> Tarikh Pinjaman</label>
                <input type="date" class="form-control" id="borrow_date" name="borrow_date" 
                       min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="notes"><i class="fas fa-comment"></i> Nota (Pilihan)</label>
            <textarea class="form-control" id="notes" name="notes" rows="2" 
                      placeholder="Sebab pinjaman atau nota tambahan"></textarea>
        </div>
        <button type="submit" name="request_loan" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Hantar Permohonan
        </button>
    </form>
</div>

<!-- My OKU Equipment Loans -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list-alt"></i> Sejarah Pinjaman Peralatan OKU</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Peralatan</th>
                    <th>Kategori</th>
                    <th>Kuantiti</th>
                    <th>Tarikh Pinjam</th>
                    <th>Tarikh Pulang</th>
                    <th>Disahkan Oleh</th>
                    <th>Status</th>
                    <th>Nota</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($okuLoans)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--gray-600);">
                        Tiada pinjaman peralatan OKU
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($okuLoans as $loan): ?>
                <tr>
                    <td><?php echo htmlspecialchars($loan['equipment_name']); ?></td>
                    <td><?php echo htmlspecialchars((isset($loan['category']) ? $loan['category'] : '-')); ?></td>
                    <td><?php echo $loan['quantity']; ?></td>
                    <td><?php echo formatDate($loan['borrow_date']); ?></td>
                    <td><?php echo $loan['return_date'] ? formatDate($loan['return_date']) : '-'; ?></td>
                    <td><?php echo (isset($loan['approved_by_name']) ? $loan['approved_by_name'] : '-'); ?></td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $loan['status'] === 'returned' ? 'success' : 
                                ($loan['status'] === 'approved' ? 'info' : 
                                ($loan['status'] === 'rejected' ? 'danger' : 'warning')); 
                        ?>">
                            <?php echo ucfirst($loan['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars((isset($loan['notes']) ? $loan['notes'] : '-')); ?></td>
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
