<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();
$message = '';
$error = '';
$result = null;
$savedRouteId = null;

// Handle SAVE of optimization result
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_route'])) {
    $payload = json_decode($_POST['route_payload'] ?? '{}', true);

    if (!empty($payload['routes'])) {
        $routeName = 'Route ' . date('M j, Y g:ia');

        // Create route record
        $db->execute(
            "INSERT INTO routes (route_name, vehicle_id, rider_id, route_date, total_orders, total_distance, status)
             VALUES (?, NULL, NULL, CURDATE(), ?, ?, 'planned')",
            [
                $routeName,
                $payload['total_orders'] ?? 0,
                $payload['total_distance_km'] ?? 0
            ]
        );
        $newRouteId = $db->queryOne("SELECT LAST_INSERT_ID() as id")['id'];

        // Save each stop
        foreach ($payload['routes'] as $route) {
            foreach ($route['stops'] as $stop) {
                $db->execute(
                    "INSERT INTO route_stops (route_id, tracking_number, barangay, lat, lng, sequence, arrival_minute)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $newRouteId,
                        $stop['id'],
                        $stop['barangay'] ?? null,
                        $stop['lat'] ?? null,
                        $stop['lng'] ?? null,
                        $stop['sequence'] ?? 0,
                        $stop['arrival_minute'] ?? 0
                    ]
                );
            }
        }

        $savedRouteId = $newRouteId;
        $message = "✅ Route saved! Route #$newRouteId";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['save_route'])) {
    $orders = $db->queryAll("
        SELECT id, tracking_number, barangay
        FROM orders
        WHERE status = 'pending'
        ORDER BY id
        LIMIT 20
    ");

    if (count($orders) < 2) {
        $error = "Need at least 2 pending orders to optimize.";
    } else {
        $coords = [
            'Lucao' => [16.0325, 120.3355],
            'Mayombo' => [16.0389, 120.3278],
            'Bonuan Gueset' => [16.0597, 120.3444],
            'Bonuan Boquig' => [16.0645, 120.3512],
            'Bonuan Binloc' => [16.0701, 120.3613],
            'Poblacion Oeste' => [16.0433, 120.3333],
            'Tapuac' => [16.0470, 120.3420],
            'Herrero' => [16.0390, 120.3400],
            'Caranglaan' => [16.0333, 120.3300],
            'Lasip Grande' => [16.0300, 120.3350],
            'Malued' => [16.0520, 120.3350],
            'Pantal' => [16.0560, 120.3290],
            'Carael' => [16.0406, 120.3200],
            'Calmay' => [16.0360, 120.3160],
        ];

        $stops = [];
        foreach ($orders as $o) {
            if (!isset($coords[$o['barangay']])) continue;
            $stops[] = [
    		'id' => $o['tracking_number'],
    		'barangay' => $o['barangay'],
    		'lat' => $coords[$o['barangay']][0],
    		'lng' => $coords[$o['barangay']][1],
    		'demand' => 1,
    		'time_window_start' => 480,
    		'time_window_end' => 1380,
		];
        }

        if (count($stops) < 2) {
            $error = "Not enough orders with valid barangay coordinates.";
        } else {
            $payload = [
                'depot' => [
                    'id' => 'DEPOT', 'lat' => 16.0433, 'lng' => 120.3333, 'demand' => 0
                ],
                'stops' => $stops,
                'num_vehicles' => 2,
                'vehicle_capacity' => 20,
                'max_route_minutes' => 1440,
            ];

            $ch = curl_init('http://127.0.0.1:8001/optimize');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $error = "Cannot reach optimizer service: $curlError\nMake sure the Python server is running on port 8001.";
            } elseif ($httpCode !== 200) {
                $error = "Optimizer returned HTTP $httpCode";
            } else {
                $result = json_decode($response, true);
                $message = "Optimization complete!";
            }
        }
    }
}

$pageTitle = 'AI Route Optimizer';
require_once 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert success">
        <?= htmlspecialchars($message) ?>
        <?php if ($savedRouteId): ?>
            &nbsp;— <a href="route_map.php?id=<?= $savedRouteId ?>" style="color:inherit;text-decoration:underline;font-weight:700;">View on Map →</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($error): ?><div class="alert error">❌ <?= nl2br(htmlspecialchars($error)) ?></div><?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>🤖 AI Route Optimizer (OR-Tools)</h3>
            <p>Automatically generate the best route for today's pending orders</p>
        </div>
        <form method="POST">
            <button type="submit" class="btn">⚡ Optimize Now</button>
        </form>
    </div>
    <div class="panel-body">
        <?php if (!$result): ?>
            <div class="empty-state">
                <div class="icon">🤖</div>
                <h4>Ready to optimize</h4>
                <p>Click "Optimize Now" to solve the Vehicle Routing Problem for today's pending orders.</p>
            </div>
        <?php else: ?>

		<!-- Save Route Button -->
<form method="POST" style="margin-bottom:20px;">
    <input type="hidden" name="save_route" value="1">
    <input type="hidden" name="route_payload" value='<?= htmlspecialchars(json_encode([
        'routes' => array_map(function($r) use ($stops) {
            // Enrich each stop with its barangay + lat/lng from the original $stops array
            $stopMap = [];
            foreach ($stops as $s) { $stopMap[$s['id']] = $s; }
            $enriched = [];
            foreach ($r['stops'] as $stop) {
                $enriched[] = [
                    'id' => $stop['id'],
                    'barangay' => $stopMap[$stop['id']]['barangay'] ?? null,
                    'lat' => $stopMap[$stop['id']]['lat'] ?? null,
                    'lng' => $stopMap[$stop['id']]['lng'] ?? null,
                    'sequence' => $stop['sequence'],
                    'arrival_minute' => $stop['arrival_minute'],
                ];
            }
            return ['vehicle_id' => $r['vehicle_id'], 'stops' => $enriched];
        }, $result['routes']),
        'total_distance_km' => $result['total_distance_km'],
        'total_orders' => count($stops),
    ]), ENT_QUOTES) ?>'>
    <button type="submit" class="btn" style="background:#10b981;">💾 Save as Route</button>
</form>

            <div class="stats-grid" style="margin-bottom:20px;">
                <div class="stat-card">
                    <div class="stat-icon indigo">📏</div>
                    <div class="stat-details">
                        <p>Total Distance</p>
                        <h2><?= $result['total_distance_km'] ?> km</h2>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">⏱️</div>
                    <div class="stat-details">
                        <p>Total Duration</p>
                        <h2><?= $result['total_duration_minutes'] ?> min</h2>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow">🚚</div>
                    <div class="stat-details">
                        <p>Vehicles Used</p>
                        <h2><?= count($result['routes']) ?></h2>
                    </div>
                </div>
            </div>

            <?php foreach ($result['routes'] as $r): ?>
                <div class="panel" style="margin-bottom:16px;border:1px solid #e2e8f0;">
                    <div class="panel-header">
                        <div>
                            <h3>🚚 Vehicle #<?= $r['vehicle_id'] ?></h3>
                            <p><?= count($r['stops']) ?> stops · <?= $r['total_distance_km'] ?> km · <?= $r['total_duration_minutes'] ?> min</p>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>#</th><th>Tracking #</th><th>Arrival</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($r['stops'] as $s): ?>
                                    <tr>
                                        <td><?= $s['sequence'] + 1 ?></td>
                                        <td><strong><?= htmlspecialchars($s['id']) ?></strong></td>
                                        <td><?= sprintf('%02d:%02d', floor($s['arrival_minute']/60), $s['arrival_minute']%60) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>