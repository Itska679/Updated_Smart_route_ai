<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();

$date = $_GET['date'] ?? date('Y-m-d');

$routes = $db->queryAll("
    SELECT r.*, v.plate_number, u.username AS rider_name
    FROM routes r
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    LEFT JOIN users u ON r.rider_id = u.id
    WHERE r.route_date = ?
    ORDER BY r.route_name
", [$date]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Route Sheet — <?= htmlspecialchars($date) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 30px; color: #1e293b; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #4f46e5; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 22px; color: #0f172a; }
        .header p { font-size: 13px; color: #64748b; margin-top: 4px; }
        .header .meta { text-align: right; font-size: 12px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 15px; }
        th { background: #f1f5f9; padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; color: #475569; border-bottom: 2px solid #cbd5e1; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .status { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .planned { background: #dbeafe; color: #1d4ed8; }
        .in_progress { background: #fef3c7; color: #b45309; }
        .completed { background: #d1fae5; color: #047857; }
        .print-btn { display: block; margin: 25px auto; padding: 12px 24px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .summary { margin-top: 25px; padding: 15px; background: #f8fafc; border-radius: 8px; display: flex; gap: 40px; font-size: 13px; }
        .summary strong { color: #0f172a; }
        @media print { .print-btn { display: none; } body { padding: 15px; } }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🚚 2GO Express — Dagupan City</h1>
            <p>Daily Route Sheet</p>
        </div>
        <div class="meta">
            <strong>Date:</strong> <?= date('F j, Y', strtotime($date)) ?><br>
            Generated: <?= date('M j, Y g:i A') ?>
        </div>
    </div>

    <?php if (empty($routes)): ?>
        <p style="text-align:center;color:#94a3b8;padding:40px;">No routes scheduled for this date.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Route Name</th>
                    <th>Vehicle</th>
                    <th>Rider</th>
                    <th>Orders</th>
                    <th>Distance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; $totalOrders = 0; $totalDistance = 0; foreach ($routes as $r): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($r['route_name']) ?></strong></td>
                        <td><?= htmlspecialchars($r['plate_number'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['rider_name'] ?? '—') ?></td>
                        <td><?= $r['total_orders'] ?></td>
                        <td><?= number_format($r['total_distance'], 2) ?> km</td>
                        <td><span class="status <?= $r['status'] ?>"><?= str_replace('_',' ', $r['status']) ?></span></td>
                    </tr>
                    <?php $totalOrders += $r['total_orders']; $totalDistance += $r['total_distance']; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary">
            <div><strong>Total Routes:</strong> <?= count($routes) ?></div>
            <div><strong>Total Orders:</strong> <?= $totalOrders ?></div>
            <div><strong>Total Distance:</strong> <?= number_format($totalDistance, 2) ?> km</div>
        </div>
    <?php endif; ?>

    <button class="print-btn" onclick="window.print()">🖨️ Print Route Sheet</button>
</body>
</html>