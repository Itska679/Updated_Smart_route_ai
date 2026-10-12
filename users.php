<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();
$message = '';
$error = '';

if (isset($_GET['delete'])) {
    if ((int)$_GET['delete'] === (int)$_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        $db->execute("DELETE FROM users WHERE id = ?", [(int)$_GET['delete']]);
        $message = "User deleted successfully.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $role     = $_POST['role'];
    $password = $_POST['password'];

    if (!empty($_POST['id'])) {
        // UPDATE
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->execute(
                "UPDATE users SET username=?, email=?, role=?, password=? WHERE id=?",
                [$username, $email, $role, $hash, (int)$_POST['id']]
            );
        } else {
            $db->execute(
                "UPDATE users SET username=?, email=?, role=? WHERE id=?",
                [$username, $email, $role, (int)$_POST['id']]
            );
        }
        $message = "User updated successfully.";
    } else {
        // CREATE
        if (empty($password)) {
            $error = "Password is required for new users.";
        } else {
            // Check duplicate
            $exists = $db->queryOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
            if ($exists) {
                $error = "Username or email already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->execute(
                    "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)",
                    [$username, $email, $hash, $role]
                );
                $message = "User created successfully.";
            }
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $db->queryOne("SELECT * FROM users WHERE id = ?", [(int)$_GET['edit']]);
}

$users = $db->queryAll("SELECT id, username, email, role, created_at FROM users ORDER BY role, username");
$totalAdmins = 0; $totalRiders = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin') $totalAdmins++;
    if ($u['role'] === 'rider') $totalRiders++;
}

$pageTitle = 'Users & Riders';
require_once 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert success">✅ <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert error">❌ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon indigo">👤</div>
        <div class="stat-details">
            <p>Total Users</p>
            <h2><?= count($users) ?></h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">🛡️</div>
        <div class="stat-details">
            <p>Admins</p>
            <h2><?= $totalAdmins ?></h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">🏍️</div>
        <div class="stat-details">
            <p>Riders</p>
            <h2><?= $totalRiders ?></h2>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3><?= $edit ? 'Edit User' : 'Add New User' ?></h3>
            <p><?= $edit ? 'Leave password blank to keep the current one' : 'Create an admin or rider account' ?></p>
        </div>
    </div>
    <div class="panel-body">
        <form method="POST" class="form-grid">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">

            <div class="form-field">
                <label>Username</label>
                <input type="text" name="username" required placeholder="e.g., rider_juan"
                       value="<?= htmlspecialchars($edit['username'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label>Email</label>
                <input type="email" name="email" required placeholder="name@example.com"
                       value="<?= htmlspecialchars($edit['email'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label>Role</label>
                <select name="role" required>
                    <option value="rider" <?= ($edit['role'] ?? '') === 'rider' ? 'selected' : '' ?>>Rider</option>
                    <option value="admin" <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <div class="form-field">
                <label>Password <?= $edit ? '(leave blank to keep)' : '' ?></label>
                <input type="password" name="password" placeholder="••••••••"
                       <?= $edit ? '' : 'required' ?>>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn"><?= $edit ? '💾 Update User' : '➕ Create User' ?></button>
                <?php if ($edit): ?>
                    <a href="users.php" class="btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>All Users</h3>
            <p><?= count($users) ?> account(s) in the system</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>#<?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'admin' ? 'in_use' : 'available' ?>">
                                <?= $u['role'] ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <a href="?edit=<?= $u['id'] ?>" class="btn-sm">✏️ Edit</a>
                            <a href="?delete=<?= $u['id'] ?>" class="btn-sm danger"
                               onclick="return confirm('Delete this user?')">🗑️ Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>