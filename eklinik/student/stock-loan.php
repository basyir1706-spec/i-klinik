<?php
/**
 * Student Stock Loan Page - i-Klinik Politeknik Besut
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

// Handle form submission for stock loan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_loan'])) {
    $itemId = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    $borrowDate = sanitize($_POST['borrow_date']);
    $notes = sanitize($_POST['notes']);
    
    if (empty($borrowDate)) {
        $borrowDate = date('Y-m-d');
    }
    
    // Check item stock
    $stmt = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    
    if ($item && $item['stock_quantity'] >= $quantity) {
        $stmt = $conn->prepare("INSERT INTO stock_loans (user_id, item_id, quantity, borrow_date, notes, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iiiss", $userId, $itemId, $quantity, $borrowDate, $notes);
        
        if ($stmt->execute()) {
            $success = 'Permohonan pinjaman stok berjaya dihantar!';
            logActivity($userId, 'Stock loan requested', 'Requested ' . $quantity . ' x ' . $item['name']);
            
            // Notify staff/doctors
            $staffStmt = $conn->prepare("SELECT id FROM users WHERE role IN ('doctor', 'staff')");
            $staffStmt->execute();
            $staffResult = $staffStmt->get_result();
            while ($staff = $staffResult->fetch_assoc()) {
                createNotification($staff['id'], 'Permohonan Pinjaman Stok', $userName . ' memohon ' . $quantity . ' x ' . $item['name'], 'inventory');
            }
        } else {
            $error = 'Ralat berlaku semasa menghantar permohonan';
        }
    } else {
        $error = 'Stok tidak mencukupi';
    }
}

// Get all inventory items with categories
$stmt = $conn->prepare("SELECT i.*, c.name as category_name FROM inventory_items i LEFT JOIN inventory_categories c ON i.category_id = c.id ORDER BY c.name, i.name");
$stmt->execute();
$inventoryItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's stock loans
$stmt = $conn->prepare("SELECT sl.*, i.name as item_name, i.unit, u.name as approved_by_name FROM stock_loans sl JOIN inventory_items i ON sl.item_id = i.id LEFT JOIN users u ON sl.approved_by = u.id WHERE sl.user_id = ? ORDER BY sl.created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stockLoans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications
$totalNotifications = getUnreadNotificationCount($userId);

// Count pending loans
$pendingLoans = 0;
foreach ($stockLoans as $loan) {
    if ($loan['status'] === 'pending') $pendingLoans++;
}

// Notification badges
$stockNotification = $pendingLoans;
$activePage = 'stock-loan';

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

<!-- Request Stock Loan Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-hand-holding-medical"></i> Pinjam Stok First Aid</h3>
    </div>
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="item_id"><i class="fas fa-box"></i> Pilih Item</label>
                <select class="form-control" id="item_id" name="item_id" required>
                    <option value="">-- Pilih Item --</option>
                    <?php 
                    $currentCategory = '';
                    foreach ($inventoryItems as $item): 
                        if ($currentCategory !== $item['category_name']):
                            if ($currentCategory !== '') echo '</optgroup>';
                            echo '<optgroup label="' . htmlspecialchars(!empty($item['category_name']) ? $item['category_name'] : 'Lain-lain') . '">';
                            $currentCategory = $item['category_name'];
                        endif;
                    ?>
                    <option value="<?php echo $item['id']; ?>" data-stock="<?php echo $item['stock_quantity']; ?>">
                        <?php echo htmlspecialchars($item['name']); ?> (Stok: <?php echo $item['stock_quantity']; ?> <?php echo $item['unit']; ?>)
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

<!-- My Stock Loans -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list-alt"></i> Sejarah Pinjaman Stok</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Kuantiti</th>
                    <th>Tarikh Pinjam</th>
                    <th>Tarikh Pulang</th>
                    <th>Disahkan Oleh</th>
                    <th>Status</th>
                    <th>Nota</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stockLoans)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--gray-600);">
                        Tiada pinjaman stok
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($stockLoans as $loan): ?>
                <tr>
                    <td><?php echo htmlspecialchars($loan['item_name']); ?></td>
                    <td><?php echo $loan['quantity']; ?></td>
                    <td><?php echo formatDate($loan['borrow_date']); ?></td>
                    <td><?php echo $loan['return_date'] ? formatDate($loan['return_date']) : '-'; ?></td>
                    <td><?php echo !empty($loan['approved_by_name']) ? $loan['approved_by_name'] : '-'; ?></td>
                    <td>
                        <span class="badge badge-<?php 
                            echo $loan['status'] === 'returned' ? 'success' : 
                                ($loan['status'] === 'approved' ? 'info' : 
                                ($loan['status'] === 'rejected' ? 'danger' : 'warning')); 
                        ?>">
                            <?php echo ucfirst($loan['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars(!empty($loan['notes']) ? $loan['notes'] : '-'); ?></td>
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
