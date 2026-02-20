<?php
/**
 * Doctor Inventory (First Aid) Page - i-Klinik Politeknik Besut
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['doctor']);

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();
$success = '';
$error = '';

$conn = getDBConnection();

// Handle approve/reject stock loan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_loan'])) {
        $loanId = (int)$_POST['loan_id'];
        $itemId = (int)$_POST['item_id'];
        $quantity = (int)$_POST['quantity'];
        
        // Update loan status
        $stmt = $conn->prepare("UPDATE stock_loans SET status = 'approved', approved_by = ? WHERE id = ?");
        $stmt->bind_param("ii", $userId, $loanId);
        $stmt->execute();
        
        // Deduct from inventory
        $stmt = $conn->prepare("UPDATE inventory_items SET stock_quantity = stock_quantity - ? WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $itemId);
        $stmt->execute();
        
        $success = 'Permohonan pinjaman diluluskan!';
        
        // Get loan info for notification
        $stmt = $conn->prepare("SELECT user_id FROM stock_loans WHERE id = ?");
        $stmt->bind_param("i", $loanId);
        $stmt->execute();
        $loanUser = $stmt->get_result()->fetch_assoc();
        createNotification($loanUser['user_id'], 'Pinjaman Stok Diluluskan', 'Permohonan pinjaman stok first aid anda telah diluluskan. Sila ambil di klinik.', 'inventory');
    }
    
    if (isset($_POST['reject_loan'])) {
        $loanId = (int)$_POST['loan_id'];
        $reason = sanitize($_POST['rejection_reason']);
        
        $stmt = $conn->prepare("UPDATE stock_loans SET status = 'rejected', approved_by = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("isi", $userId, $reason, $loanId);
        $stmt->execute();
        
        $success = 'Permohonan pinjaman ditolak';
        
        // Get loan info for notification
        $stmt = $conn->prepare("SELECT user_id FROM stock_loans WHERE id = ?");
        $stmt->bind_param("i", $loanId);
        $stmt->execute();
        $loanUser = $stmt->get_result()->fetch_assoc();
        createNotification($loanUser['user_id'], 'Pinjaman Stok Ditolak', 'Permohonan pinjaman stok anda telah ditolak. Sebab: ' . $reason, 'inventory');
    }
    
    if (isset($_POST['return_item'])) {
        $loanId = (int)$_POST['loan_id'];
        $itemId = (int)$_POST['item_id'];
        $quantity = (int)$_POST['quantity'];
        
        // Update loan status
        $stmt = $conn->prepare("UPDATE stock_loans SET status = 'returned' WHERE id = ?");
        $stmt->bind_param("i", $loanId);
        $stmt->execute();
        
        // Add back to inventory
        $stmt = $conn->prepare("UPDATE inventory_items SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $itemId);
        $stmt->execute();
        
        $success = 'Item telah dipulangkan!';
    }
    
    if (isset($_POST['update_item'])) {
        $itemId = (int)$_POST['item_id'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $stockQuantity = (int)$_POST['stock_quantity'];
        $minimumStock = (int)$_POST['minimum_stock'];
        $unit = sanitize($_POST['unit']);
        
        // Determine status
        $status = 'available';
        if ($stockQuantity == 0) {
            $status = 'out_of_stock';
        } elseif ($stockQuantity < $minimumStock) {
            $status = 'low_stock';
        }
        
        $stmt = $conn->prepare("UPDATE inventory_items SET name = ?, description = ?, stock_quantity = ?, minimum_stock = ?, unit = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssiiisi", $name, $description, $stockQuantity, $minimumStock, $unit, $status, $itemId);
        $stmt->execute();
        
        $success = 'Item inventory berjaya dikemaskini!';
    }
    
    if (isset($_POST['add_item'])) {
        $categoryId = (int)$_POST['category_id'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $stockQuantity = (int)$_POST['stock_quantity'];
        $minimumStock = (int)$_POST['minimum_stock'];
        $unit = sanitize($_POST['unit']);
        
        $status = 'available';
        if ($stockQuantity == 0) {
            $status = 'out_of_stock';
        } elseif ($stockQuantity < $minimumStock) {
            $status = 'low_stock';
        }
        
        $stmt = $conn->prepare("INSERT INTO inventory_items (category_id, name, description, stock_quantity, minimum_stock, unit, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiiis", $categoryId, $name, $description, $stockQuantity, $minimumStock, $unit, $status);
        $stmt->execute();
        
        $success = 'Item inventory berjaya ditambah!';
    }
}

// Get inventory stats
$stmt = $conn->prepare("SELECT 
    SUM(CASE WHEN stock_quantity >= minimum_stock THEN 1 ELSE 0 END) as sufficient,
    COUNT(*) as total,
    SUM(stock_quantity) as total_stock
    FROM inventory_items");
$stmt->execute();
$inventoryStats = $stmt->get_result()->fetch_assoc();
$inventoryPercentage = $inventoryStats['total'] > 0 
    ? round(($inventoryStats['sufficient'] / $inventoryStats['total']) * 100) 
    : 0;

// Get pending stock loans
$stmt = $conn->prepare("SELECT sl.*, i.name as item_name, i.unit, u.name as user_name, u.matrix_no FROM stock_loans sl JOIN inventory_items i ON sl.item_id = i.id JOIN users u ON sl.user_id = u.id WHERE sl.status = 'pending' ORDER BY sl.created_at DESC");
$stmt->execute();
$pendingLoans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get active stock loans
$stmt = $conn->prepare("SELECT sl.*, i.name as item_name, i.unit, u.name as user_name, u.matrix_no FROM stock_loans sl JOIN inventory_items i ON sl.item_id = i.id JOIN users u ON sl.user_id = u.id WHERE sl.status = 'approved' ORDER BY sl.borrow_date DESC");
$stmt->execute();
$activeLoans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all inventory items with categories
$stmt = $conn->prepare("SELECT i.*, c.name as category_name FROM inventory_items i LEFT JOIN inventory_categories c ON i.category_id = c.id ORDER BY c.name, i.name");
$stmt->execute();
$inventoryItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories
$stmt = $conn->prepare("SELECT * FROM inventory_categories ORDER BY name");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications
$totalNotifications = getUnreadNotificationCount($userId);
$pendingStockLoans = count($pendingLoans);

// Notification badges
$inventoryNotification = $pendingStockLoans;
$activePage = 'inventory';

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

<!-- Inventory Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $inventoryStats['total'] ?? 0; ?></h3>
            <p>Jenis Item</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-cubes"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $inventoryStats['total_stock'] ?? 0; ?></h3>
            <p>Jumlah Stok</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-percentage"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $inventoryPercentage; ?>%</h3>
            <p>Stok Mencukupi</p>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width: <?php echo $inventoryPercentage; ?>%"></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($pendingLoans); ?></h3>
            <p>Permohonan Pending</p>
        </div>
    </div>
</div>

<!-- Pending Stock Loan Requests -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bell"></i> Permohonan Pinjaman Stok (Perlu Kelulusan)</h3>
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
                    <th>Item</th>
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
                    <td><?php echo htmlspecialchars($loan['matrix_no'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($loan['item_name']); ?></td>
                    <td><?php echo $loan['quantity']; ?> <?php echo $loan['unit']; ?></td>
                    <td><?php echo formatDate($loan['borrow_date']); ?></td>
                    <td><?php echo htmlspecialchars($loan['notes'] ?? '-'); ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                            <input type="hidden" name="item_id" value="<?php echo $loan['item_id']; ?>">
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

<!-- Active Stock Loans -->
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
                    <th>Item</th>
                    <th>Kuantiti</th>
                    <th>Tarikh Pinjam</th>
                    <th>Tarikh Pulang</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeLoans as $loan): ?>
                <tr>
                    <td><?php echo htmlspecialchars($loan['user_name']); ?></td>
                    <td><?php echo htmlspecialchars($loan['matrix_no'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($loan['item_name']); ?></td>
                    <td><?php echo $loan['quantity']; ?> <?php echo $loan['unit']; ?></td>
                    <td><?php echo formatDate($loan['borrow_date']); ?></td>
                    <td><?php echo $loan['return_date'] ? formatDate($loan['return_date']) : '-'; ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                            <input type="hidden" name="item_id" value="<?php echo $loan['item_id']; ?>">
                            <input type="hidden" name="quantity" value="<?php echo $loan['quantity']; ?>">
                            <button type="submit" name="return_item" class="btn btn-success btn-sm"
                                    onclick="return confirm('Sahkan item telah dipulangkan?')">
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

<!-- Inventory Items List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-boxes"></i> Senarai Inventory (First Aid)</h3>
        <button type="button" class="btn btn-primary btn-sm" onclick="openAddItemModal()">
            <i class="fas fa-plus"></i> Tambah Item
        </button>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th>Stok</th>
                    <th>Min. Stok</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $currentCategory = '';
                foreach ($inventoryItems as $item): 
                    if ($currentCategory !== $item['category_name']):
                        if ($currentCategory !== '') echo '</tbody><tbody>';
                        echo '<tr><td colspan="7" style="background: rgba(0, 255, 0, 0.1); font-weight: bold; color: var(--primary-green);">' . htmlspecialchars($item['category_name'] ?? 'Lain-lain') . '</td></tr>';
                        $currentCategory = $item['category_name'];
                    endif;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars($item['category_name'] ?? '-'); ?></td>
                    <td><?php echo $item['stock_quantity']; ?></td>
                    <td><?php echo $item['minimum_stock']; ?></td>
                    <td><?php echo $item['unit']; ?></td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $item['status'] === 'available' ? 'success' : 
                                ($item['status'] === 'low_stock' ? 'warning' : 'danger');
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-info btn-sm" 
                                onclick="openEditItemModal(<?php echo htmlspecialchars(json_encode($item)); ?>)">
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

<!-- Add Item Modal -->
<div class="modal" id="addItemModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Tambah Item Inventory</h3>
            <button class="modal-close" onclick="closeAddItemModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="add_category_id"><i class="fas fa-tag"></i> Kategori</label>
                <select class="form-control" id="add_category_id" name="category_id" required>
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="add_name"><i class="fas fa-box"></i> Nama Item</label>
                <input type="text" class="form-control" id="add_name" name="name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="add_stock_quantity"><i class="fas fa-hashtag"></i> Stok Awal</label>
                    <input type="number" class="form-control" id="add_stock_quantity" name="stock_quantity" min="0" required>
                </div>
                <div class="form-group">
                    <label for="add_minimum_stock"><i class="fas fa-exclamation-triangle"></i> Stok Minimum</label>
                    <input type="number" class="form-control" id="add_minimum_stock" name="minimum_stock" min="1" required>
                </div>
            </div>
            <div class="form-group">
                <label for="add_unit"><i class="fas fa-ruler"></i> Unit</label>
                <input type="text" class="form-control" id="add_unit" name="unit" placeholder="Contoh: tablet, bottle, pack" required>
            </div>
            <div class="form-group">
                <label for="add_description"><i class="fas fa-comment"></i> Penerangan</label>
                <textarea class="form-control" id="add_description" name="description" rows="3"></textarea>
            </div>
            <button type="submit" name="add_item" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-plus"></i> Tambah Item
            </button>
        </form>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal" id="editItemModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Item Inventory</h3>
            <button class="modal-close" onclick="closeEditItemModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="item_id" id="edit_item_id">
            <div class="form-group">
                <label for="edit_name"><i class="fas fa-box"></i> Nama Item</label>
                <input type="text" class="form-control" id="edit_name" name="name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_stock_quantity"><i class="fas fa-hashtag"></i> Stok</label>
                    <input type="number" class="form-control" id="edit_stock_quantity" name="stock_quantity" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_minimum_stock"><i class="fas fa-exclamation-triangle"></i> Stok Minimum</label>
                    <input type="number" class="form-control" id="edit_minimum_stock" name="minimum_stock" min="1" required>
                </div>
            </div>
            <div class="form-group">
                <label for="edit_unit"><i class="fas fa-ruler"></i> Unit</label>
                <input type="text" class="form-control" id="edit_unit" name="unit" required>
            </div>
            <div class="form-group">
                <label for="edit_description"><i class="fas fa-comment"></i> Penerangan</label>
                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
            </div>
            <button type="submit" name="update_item" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> Kemaskini Item
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

function openAddItemModal() {
    document.getElementById('addItemModal').classList.add('show');
}

function closeAddItemModal() {
    document.getElementById('addItemModal').classList.remove('show');
}

function openEditItemModal(item) {
    document.getElementById('edit_item_id').value = item.id;
    document.getElementById('edit_name').value = item.name;
    document.getElementById('edit_stock_quantity').value = item.stock_quantity;
    document.getElementById('edit_minimum_stock').value = item.minimum_stock;
    document.getElementById('edit_unit').value = item.unit;
    document.getElementById('edit_description').value = item.description || '';
    document.getElementById('editItemModal').classList.add('show');
}

function closeEditItemModal() {
    document.getElementById('editItemModal').classList.remove('show');
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
