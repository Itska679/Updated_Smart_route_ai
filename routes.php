<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();
$message = '';

if (isset($_GET['delete'])) {
    $db->execute("DELETE FROM routes WHERE id = ?", [(int)$_GET['delete']]);
    $message = "Route deleted.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['route_name']);
    $vehicleId = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;
    $riderId = !empty($_POST['rider_id']) ? (int)$_POST['rider_id'] : null;
    $date = $_POST['route_date'];
    $orders = (int)$_POST['total_orders'];
    $distance = (float)$_POST['total_distance'];
    $status = $_POST['status'];

    if (!empty($_POST['id'])) {
        $db->execute(
            "UPDATE routes SET route_name=?, vehicle_id=?, rider_id=?, route_date=?, total_orders=?, total_distance=?, status=? WHERE id=?",
            [$name, $vehicleId, $riderId, $date, $orders, $distance, $status, (int)$_POST['id']]
        );
        $message = "Route updated.";
    } else {
        $db->execute(
            "INSERT INTO routes (route_name, vehicle_id, rider_id, route_date, total_orders, total_distance, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$name, $vehicleId, $riderId, $date, $orders, $distance, $status]
        );
        $message = "Route created.";
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $db->queryOne("SELECT * FROM routes WHERE id = ?", [(int)$_GET['edit']]);
}

$routes = $db->queryAll("
    SELECT r.*, v.plate_number, u.username AS rider_name
    FROM routes r
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    LEFT JOIN users u ON r.rider_id = u.id
    ORDER BY r.route_date DESC
");
$vehicles = $db->queryAll("SELECT id, plate_number FROM vehicles ORDER BY plate_number");
$riders = $db->queryAll("SELECT id, username FROM users WHERE role = 'rider' ORDER BY username");

$pageTitle = 'Routes';
require_once 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert success">✅ <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3><?= $edit ? 'Edit Route' : 'Create Route' ?></h3>
            <p>Assign vehicles and riders to delivery routes</p>
        </div>
    </div>
    <div class="panel-body">
        <form method="POST" class="form-grid">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">

            <div class="form-field">
                <label>Route Name</label>
                <input type="text" name="route_name" required placeholder="e.g., Lucao–Mayombo Loop"
                       value="<?= htmlspecialchars($edit['route_name'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label>Vehicle</label>
                <select name="vehicle_id">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= ($edit['vehicle_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['plate_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label>Rider</label>
                <select name="rider_id">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($riders as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= ($edit['rider_id'] ?? '') == $r['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label>Route Date</label>
                <input type="date" name="route_date" required
                       value="<?= htmlspecialchars($edit['route_date'] ?? date('Y-m-d')) ?>">
            </div>

            <div class="form-field">
                <label>Total Orders</label>
                <input type="number" name="total_orders" min="0" placeholder="0"
                       value="<?= htmlspecialchars($edit['total_orders'] ?? 0) ?>">
            </div>

            <div class="form-field">
                <label>Total Distance (km)</label>
                <input type="number" step="0.01" name="total_distance" min="0" placeholder="0.00"
                       value="<?= htmlspecialchars($edit['total_distance'] ?? 0) ?>">
            </div>

            <div class="form-field">
                <label>Status</label>
                <select name="status" required>
                    <?php foreach (['planned','in_progress','completed'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($edit['status'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_',' ',$s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn"><?= $edit ? '💾 Update Route' : '➕ Create Route' ?></button>
                <?php if ($edit): ?>
                    <a href="routes.php" class="btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
    <div>
        <h3>All Routes</h3>
        <p><?= count($routes) ?> route(s) scheduled</p>
    </div>
    <a href="print_routes.php?date=<?= date('Y-m-d') ?>" target="_blank" class="btn">🖨️ Print Today's Sheet</a>
</div>
    <div class="table-wrap">
        <?php if (empty($routes)): ?>
            <div class="empty-state">
                <div class="icon">🗺️</div>
                <h4>No routes yet</h4>
                <p>Create your first delivery route above.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>ID</th><th>Route</th><th>Date</th><th>Vehicle</th><th>Rider</th><th>Orders</th><th>Distance</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as $r): ?>
                        <tr>
                            <td>#<?= $r['id'] ?></td>
                            <td><strong><?= htmlspecialchars($r['route_name']) ?></strong></td>
                            <td><?= date('M j, Y', strtotime($r['route_date'])) ?></td>
                            <td><?= htmlspecialchars($r['plate_number'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['rider_name'] ?? '—') ?></td>
                            <td><?= $r['total_orders'] ?></td>
                            <td><?= number_format($r['total_distance'], 2) ?> km</td>
                            <td><span class="badge <?= $r['status'] ?>"><?= str_replace('_',' ', $r['status']) ?></span></td>
                            <td>
    				<a href="route_map.php?id=<?= $r['id'] ?>" class="btn-sm">🗺️ Map</a>
    				<a href="?edit=<?= $r['id'] ?>" class="btn-sm">✏️ Edit</a>
    				<a href="?delete=<?= $r['id'] ?>" class="btn-sm danger"
       					onclick="return confirm('Delete this route?')">🗑️ Delete</a>
				</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>