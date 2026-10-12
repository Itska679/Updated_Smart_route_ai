<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();
$message = '';

if (isset($_GET['delete'])) {
    $db->execute("DELETE FROM flood_zones WHERE id = ?", [(int)$_GET['delete']]);
    $message = "Flood zone deleted.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = trim($_POST['location_name']);
    $barangayId = !empty($_POST['barangay_id']) ? (int)$_POST['barangay_id'] : null;
    $severity = $_POST['severity'];
    $notes = trim($_POST['notes']);

    if (!empty($_POST['id'])) {
        $db->execute(
            "UPDATE flood_zones SET location_name=?, barangay_id=?, severity=?, notes=? WHERE id=?",
            [$location, $barangayId, $severity, $notes, (int)$_POST['id']]
        );
        $message = "Flood zone updated.";
    } else {
        $db->execute(
            "INSERT INTO flood_zones (location_name, barangay_id, severity, notes) VALUES (?, ?, ?, ?)",
            [$location, $barangayId, $severity, $notes]
        );
        $message = "Flood zone added.";
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $db->queryOne("SELECT * FROM flood_zones WHERE id = ?", [(int)$_GET['edit']]);
}

$zones = $db->queryAll("
    SELECT fz.*, b.name AS barangay_name
    FROM flood_zones fz
    LEFT JOIN barangays b ON fz.barangay_id = b.id
    ORDER BY FIELD(fz.severity, 'high','medium','low'), fz.location_name
");
$barangays = $db->queryAll("SELECT id, name FROM barangays ORDER BY name");

$pageTitle = 'Flood Zones';
require_once 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert success">✅ <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3><?= $edit ? 'Edit Flood Zone' : 'Add Flood Zone' ?></h3>
            <p>Mark flood-prone areas so the routing engine can avoid them</p>
        </div>
    </div>
    <div class="panel-body">
        <form method="POST" class="form-grid">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">

            <div class="form-field">
                <label>Location Name</label>
                <input type="text" name="location_name" required placeholder="e.g., Downtown to Lucao corridor"
                       value="<?= htmlspecialchars($edit['location_name'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label>Barangay</label>
                <select name="barangay_id">
                    <option value="">— Select barangay —</option>
                    <?php foreach ($barangays as $b): ?>
                        <option value="<?= $b['id'] ?>"
                            <?= ($edit['barangay_id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label>Severity</label>
                <select name="severity" required>
                    <?php foreach (['low','medium','high'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($edit['severity'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label>Notes</label>
                <input type="text" name="notes" placeholder="e.g., River overflow during monsoon"
                       value="<?= htmlspecialchars($edit['notes'] ?? '') ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn"><?= $edit ? '💾 Update Zone' : '➕ Add Zone' ?></button>
                <?php if ($edit): ?>
                    <a href="flood_zones.php" class="btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Flood-Prone Areas</h3>
            <p><?= count($zones) ?> area(s) tracked — sorted by severity</p>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (empty($zones)): ?>
            <div class="empty-state">
                <div class="icon">🌊</div>
                <h4>No flood zones yet</h4>
                <p>Mark areas that flood to make routes safer.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>ID</th><th>Location</th><th>Barangay</th><th>Severity</th><th>Notes</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($zones as $z): ?>
                        <tr>
                            <td>#<?= $z['id'] ?></td>
                            <td><strong><?= htmlspecialchars($z['location_name']) ?></strong></td>
                            <td><?= htmlspecialchars($z['barangay_name'] ?? '—') ?></td>
                            <td><span class="badge severity-<?= $z['severity'] ?>"><?= $z['severity'] ?></span></td>
                            <td><?= htmlspecialchars($z['notes'] ?? '—') ?></td>
                            <td>
                                <a href="?edit=<?= $z['id'] ?>" class="btn-sm">✏️ Edit</a>
                                <a href="?delete=<?= $z['id'] ?>" class="btn-sm danger"
                                   onclick="return confirm('Delete this flood zone?')">🗑️ Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>