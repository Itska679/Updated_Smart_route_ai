<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();
$message = '';

if (isset($_GET['delete'])) {
    $db->execute("DELETE FROM vehicles WHERE id = ?", [(int)$_GET['delete']]);
    $message = "Vehicle deleted.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plate = trim($_POST['plate_number']);
    $type = trim($_POST['vehicle_type']);
    $capacity = (int)$_POST['capacity'];
    $status = $_POST['status'];

    if (!empty($_POST['id'])) {
        $db->execute(
            "UPDATE vehicles SET plate_number=?, vehicle_type=?, capacity=?, status=? WHERE id=?",
            [$plate, $type, $capacity, $status, (int)$_POST['id']]
        );
        $message = "Vehicle updated.";
    } else {
        $db->execute(
            "INSERT INTO vehicles (plate_number, vehicle_type, capacity, status) VALUES (?, ?, ?, ?)",
            [$plate, $type, $capacity, $status]
        );
        $message = "Vehicle added.";
    }
}

$editVehicle = null;
if (isset($_GET['edit'])) {
    $editVehicle = $db->queryOne("SELECT * FROM vehicles WHERE id = ?", [(int)$_GET['edit']]);
}

$vehicles = $db->queryAll("SELECT * FROM vehicles ORDER BY id DESC");

$pageTitle = 'Vehicles';
require_once 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert success">✅ <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3><?= $editVehicle ? 'Edit Vehicle' : 'Add New Vehicle' ?></h3>
            <p>Manage the 2GO Express delivery fleet in Dagupan</p>
        </div>
    </div>
    <div class="panel-body">
        <form method="POST" class="form-grid">
            <input type="hidden" name="id" value="<?= $editVehicle['id'] ?? '' ?>">

            <div class="form-field">
                <label>Plate Number</label>
                <input type="text" name="plate_number" required placeholder="e.g., ABC-1234"
                       value="<?= htmlspecialchars($editVehicle['plate_number'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label>Vehicle Type</label>
                <select name="vehicle_type" required>
                    <option value="">Select type…</option>
                    <?php foreach (['Van','Truck','Motorcycle','Tricycle'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($editVehicle['vehicle_type'] ?? '') === $t ? 'selected' : '' ?>>
                            <?= $t ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label>Capacity (parcels)</label>
                <input type="number" name="capacity" min="1" required placeholder="e.g., 50"
                       value="<?= htmlspecialchars($editVehicle['capacity'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label>Status</label>
                <select name="status" required>
                    <?php foreach (['available','in_use','maintenance'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($editVehicle['status'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_',' ',$s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn"><?= $editVehicle ? '💾 Update Vehicle' : '➕ Add Vehicle' ?></button>
                <?php if ($editVehicle): ?>
                    <a href="vehicles.php" class="btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Fleet</h3>
            <p><?= count($vehicles) ?> vehicle(s) registered</p>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (empty($vehicles)): ?>
            <div class="empty-state">
                <div class="icon">🚐</div>
                <h4>No vehicles yet</h4>
                <p>Add your first vehicle to build the fleet.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>ID</th><th>Plate</th><th>Type</th><th>Capacity</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicles as $v): ?>
                        <tr>
                            <td>#<?= $v['id'] ?></td>
                            <td><strong><?= htmlspecialchars($v['plate_number']) ?></strong></td>
                            <td><?= htmlspecialchars($v['vehicle_type']) ?></td>
                            <td><?= $v['capacity'] ?> parcels</td>
                            <td><span class="badge <?= $v['status'] ?>"><?= str_replace('_',' ', $v['status']) ?></span></td>
                            <td>
                                <a href="?edit=<?= $v['id'] ?>" class="btn-sm">✏️ Edit</a>
                                <a href="?delete=<?= $v['id'] ?>" class="btn-sm danger"
                                   onclick="return confirm('Delete this vehicle?')">🗑️ Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>