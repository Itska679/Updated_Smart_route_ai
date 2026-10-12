<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();
$message = '';

if (isset($_GET['delete'])) {
    $db->execute("DELETE FROM orders WHERE id = ?", [(int)$_GET['delete']]);
    $message = "Order deleted successfully.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tracking  = trim($_POST['tracking_number']);
    $recipient = trim($_POST['recipient_name']);
    $barangay  = trim($_POST['barangay']);
    $address   = trim($_POST['address']);
    $status    = $_POST['status'];

    if (!empty($_POST['id'])) {
        $db->execute(
            "UPDATE orders SET tracking_number=?, recipient_name=?, barangay=?, address=?, status=? WHERE id=?",
            [$tracking, $recipient, $barangay, $address, $status, (int)$_POST['id']]
        );
        $message = "Order updated successfully.";
    } else {
        $db->execute(
            "INSERT INTO orders (tracking_number, recipient_name, barangay, address, status) VALUES (?, ?, ?, ?, ?)",
            [$tracking, $recipient, $barangay, $address, $status]
        );
        $message = "Order created successfully.";
    }
}

$editOrder = null;
if (isset($_GET['edit'])) {
    $editOrder = $db->queryOne("SELECT * FROM orders WHERE id = ?", [(int)$_GET['edit']]);
}

$orders = $db->queryAll("SELECT * FROM orders ORDER BY created_at DESC");
$barangays = $db->queryAll("SELECT name FROM barangays ORDER BY name");

$pageTitle = 'Orders';
require_once 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert success">✅ <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3><?= $editOrder ? 'Edit Order' : 'Add New Order' ?></h3>
            <p><?= $editOrder ? 'Update the details of this delivery' : 'Register a new package for delivery' ?></p>
        </div>
    </div>
    <div class="panel-body">
        <form method="POST" class="form-grid">
            <input type="hidden" name="id" value="<?= $editOrder['id'] ?? '' ?>">

            <div class="form-field">
                <label>Tracking Number</label>
                <input type="text" name="tracking_number" required placeholder="e.g., 2GO-00123"
                       value="<?= htmlspecialchars($editOrder['tracking_number'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label>Recipient Name</label>
                <input type="text" name="recipient_name" required placeholder="Full name"
                       value="<?= htmlspecialchars($editOrder['recipient_name'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label>Barangay</label>
                <select name="barangay" required>
                    <option value="">Select barangay…</option>
                    <?php foreach ($barangays as $b): ?>
                        <option value="<?= htmlspecialchars($b['name']) ?>"
                            <?= ($editOrder['barangay'] ?? '') === $b['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label>Street Address</label>
                <input type="text" name="address" placeholder="House no., street"
                       value="<?= htmlspecialchars($editOrder['address'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label>Status</label>
                <select name="status" required>
                    <?php foreach (['pending','in_transit','delivered','failed'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($editOrder['status'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_',' ',$s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn"><?= $editOrder ? '💾 Update Order' : '➕ Create Order' ?></button>
                <?php if ($editOrder): ?>
                    <a href="orders.php" class="btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>All Orders</h3>
            <p><?= count($orders) ?> total order(s) in the system</p>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="icon">📦</div>
                <h4>No orders yet</h4>
                <p>Add your first order using the form above.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tracking #</th>
                        <th>Recipient</th>
                        <th>Barangay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td>#<?= $o['id'] ?></td>
                            <td><strong><?= htmlspecialchars($o['tracking_number']) ?></strong></td>
                            <td><?= htmlspecialchars($o['recipient_name']) ?></td>
                            <td><?= htmlspecialchars($o['barangay']) ?></td>
                            <td><span class="badge <?= $o['status'] ?>"><?= str_replace('_',' ', $o['status']) ?></span></td>
                            <td>
                                <a href="?edit=<?= $o['id'] ?>" class="btn-sm">✏️ Edit</a>
                                <a href="print_receipt.php?id=<?= $o['id'] ?>" target="_blank" class="btn-sm">🖨️ Print</a>
                                <a href="?delete=<?= $o['id'] ?>" class="btn-sm danger"
                                onclick="return confirm('Delete this order?')">🗑️ Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>