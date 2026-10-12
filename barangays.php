<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();
$message = '';

if (isset($_GET['delete'])) {
    $db->execute("DELETE FROM barangays WHERE id = ?", [(int)$_GET['delete']]);
    $message = "Barangay deleted.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $zone = trim($_POST['zone']);
    $population = (int)$_POST['population'];

    if (!empty($_POST['id'])) {
        $db->execute(
            "UPDATE barangays SET name=?, zone=?, population=? WHERE id=?",
            [$name, $zone, $population, (int)$_POST['id']]
        );
        $message = "Barangay updated.";
    } else {
        $db->execute(
            "INSERT INTO barangays (name, zone, population) VALUES (?, ?, ?)",
            [$name, $zone, $population]
        );
        $message = "Barangay added.";
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $db->queryOne("SELECT * FROM barangays WHERE id = ?", [(int)$_GET['edit']]);
}

$barangays = $db->queryAll("SELECT * FROM barangays ORDER BY name");

$pageTitle = 'Barangays';
require_once 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert success">✅ <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3><?= $edit ? 'Edit Barangay' : 'Add Barangay' ?></h3>
            <p>The 31 barangays of Dagupan City</p>
        </div>
    </div>
    <div class="panel-body">
        <form method="POST" class="form-grid">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">

            <div class="form-field">
                <label>Barangay Name</label>
                <input type="text" name="name" required placeholder="e.g., Lucao"
                       value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label>Zone</label>
                <select name="zone">
                    <option value="">Select zone…</option>
                    <?php foreach (['Poblacion','North','East','West','Central'] as $z): ?>
                        <option value="<?= $z ?>" <?= ($edit['zone'] ?? '') === $z ? 'selected' : '' ?>><?= $z ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label>Population</label>
                <input type="number" name="population" min="0" placeholder="e.g., 5000"
                       value="<?= htmlspecialchars($edit['population'] ?? '') ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn"><?= $edit ? '💾 Update Barangay' : '➕ Add Barangay' ?></button>
                <?php if ($edit): ?>
                    <a href="barangays.php" class="btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>All Barangays</h3>
            <p><?= count($barangays) ?> barangay(s) registered</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>ID</th><th>Name</th><th>Zone</th><th>Population</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($barangays as $b): ?>
                    <tr>
                        <td>#<?= $b['id'] ?></td>
                        <td><strong><?= htmlspecialchars($b['name']) ?></strong></td>
                        <td><?= htmlspecialchars($b['zone'] ?? '—') ?></td>
                        <td><?= number_format($b['population'] ?? 0) ?></td>
                        <td>
                            <a href="?edit=<?= $b['id'] ?>" class="btn-sm">✏️ Edit</a>
                            <a href="?delete=<?= $b['id'] ?>" class="btn-sm danger"
                               onclick="return confirm('Delete this barangay?')">🗑️ Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>