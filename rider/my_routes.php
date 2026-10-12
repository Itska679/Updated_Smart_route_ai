<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

$routes = $db->queryAll("
    SELECT r.*, v.plate_number
    FROM routes r
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    WHERE r.rider_id = ?
    ORDER BY r.route_date DESC
    LIMIT 10
", [$_SESSION['user_id']]);

$pageTitle = 'My Routes';
require_once __DIR__ . '/includes/header.php';
?>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>🗺️ My Assigned Routes</h3>
            <p>Routes assigned to you by the dispatcher</p>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (empty($routes)): ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <h4>No routes assigned yet</h4>
                <p>Check back later — the dispatcher will assign your routes here.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Route</th><th>Date</th><th>Vehicle</th><th>Orders</th><th>Distance</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['route_name']) ?></strong></td>
                            <td><?= date('M j, Y', strtotime($r['route_date'])) ?></td>
                            <td><?= htmlspecialchars($r['plate_number'] ?? '—') ?></td>
                            <td><?= $r['total_orders'] ?></td>
                            <td><?= number_format($r['total_distance'], 2) ?> km</td>
                            <td><span class="badge <?= $r['status'] ?>"><?= str_replace('_',' ', $r['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</main>
</body>
</html>