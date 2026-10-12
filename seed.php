<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();
$message = '';
$error = '';

// Coordinates for Dagupan barangays
$coords = [
    'Lucao'           => [16.0325, 120.3355],
    'Mayombo'         => [16.0389, 120.3278],
    'Bonuan Gueset'   => [16.0597, 120.3444],
    'Bonuan Boquig'   => [16.0645, 120.3512],
    'Bonuan Binloc'   => [16.0701, 120.3613],
    'Poblacion Oeste' => [16.0433, 120.3333],
    'Tapuac'          => [16.0470, 120.3420],
    'Herrero'         => [16.0390, 120.3400],
    'Caranglaan'      => [16.0333, 120.3300],
    'Lasip Grande'    => [16.0300, 120.3350],
    'Malued'          => [16.0520, 120.3350],
    'Pantal'          => [16.0560, 120.3290],
    'Carael'          => [16.0406, 120.3200],
    'Calmay'          => [16.0360, 120.3160],
];

$barangayList = array_keys($coords);

// Sample names for recipients
$firstNames = ['Juan', 'Maria', 'Pedro', 'Ana', 'Carlo', 'Rosa', 'Miguel', 'Elena', 'Jose', 'Liza',
               'Ramon', 'Carmen', 'Antonio', 'Sofia', 'Ben', 'Grace', 'Rico', 'Nina', 'Danilo', 'Joy',
               'Ferdinand', 'Lorna', 'Rogelio', 'Marites', 'Nestor', 'Cecilia', 'Alfredo', 'Perla', 'Eduardo', 'Delia'];
$lastNames = ['Dela Cruz', 'Santos', 'Reyes', 'Lopez', 'Garcia', 'Mendoza', 'Torres', 'Bautista', 'Ramos', 'Villanueva',
              'Aquino', 'Castillo', 'Flores', 'Gonzales', 'Rivera', 'Domingo', 'Navarro', 'Salazar', 'Pascual', 'Fernandez'];

$streets = ['Rizal St.', 'Bonifacio St.', 'Mabini St.', 'Quezon Blvd.', 'Perez Blvd.', 'Arellano St.',
            'Zamora St.', 'Burgos St.', 'Gomez St.', 'Del Pilar St.', 'MH del Pilar St.', 'A.B. Fernandez Ave.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed'])) {
    try {
        // ---- Clear existing demo data (keep the admin user) ----
        $db->execute("DELETE FROM route_stops");
        $db->execute("DELETE FROM routes");
        $db->execute("DELETE FROM orders");
        $db->execute("DELETE FROM vehicles");
        $db->execute("DELETE FROM users WHERE role = 'rider'");

        // ---- Insert riders ----
        $riders = [
            ['rider_juan',   'juan@2go-dagupan.test',   'rider123'],
            ['rider_maria',  'maria@2go-dagupan.test',  'rider123'],
            ['rider_pedro',  'pedro@2go-dagupan.test',  'rider123'],
            ['rider_rosa',   'rosa@2go-dagupan.test',   'rider123'],
            ['rider_carlo',  'carlo@2go-dagupan.test',  'rider123'],
        ];
        $riderIds = [];
        foreach ($riders as $r) {
            $hash = password_hash($r[2], PASSWORD_DEFAULT);
            $db->execute(
                "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'rider')",
                [$r[0], $r[1], $hash]
            );
            $riderIds[] = $db->queryOne("SELECT LAST_INSERT_ID() as id")['id'];
        }

        // ---- Insert vehicles ----
        $vehicles = [
            ['ABC-1234', 'Van',        50],
            ['XYZ-5678', 'Truck',      100],
            ['DEF-9012', 'Motorcycle', 10],
            ['GHI-3456', 'Van',        45],
            ['JKL-7890', 'Tricycle',   15],
            ['MNO-2345', 'Truck',      80],
        ];
        $vehicleIds = [];
        foreach ($vehicles as $v) {
            $db->execute(
                "INSERT INTO vehicles (plate_number, vehicle_type, capacity, status) VALUES (?, ?, ?, 'available')",
                $v
            );
            $vehicleIds[] = $db->queryOne("SELECT LAST_INSERT_ID() as id")['id'];
        }

        // ---- Insert orders (30 orders across 3 days) ----
        $orderIds = [];
        for ($i = 0; $i < 30; $i++) {
            $tracking  = '2GO-' . str_pad((string)(100 + $i), 5, '0', STR_PAD_LEFT);
            $recipient = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
            $barangay  = $barangayList[array_rand($barangayList)];
            $address   = rand(1, 250) . ' ' . $streets[array_rand($streets)];

            // Distribute statuses realistically
            $rand = rand(1, 100);
            if ($rand <= 40)      $status = 'pending';
            elseif ($rand <= 70)  $status = 'in_transit';
            elseif ($rand <= 95)  $status = 'delivered';
            else                  $status = 'failed';

            // Spread created_at over the last 3 days
            $daysAgo = rand(0, 2);
            $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days +" . rand(8, 18) . " hours +" . rand(0, 59) . " minutes"));

            $db->execute(
                "INSERT INTO orders (tracking_number, recipient_name, barangay, address, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$tracking, $recipient, $barangay, $address, $status, $createdAt]
            );
            $orderIds[] = [
                'id' => $db->queryOne("SELECT LAST_INSERT_ID() as id")['id'],
                'tracking' => $tracking,
                'barangay' => $barangay,
                'status' => $status
            ];
        }

        // ---- Create 3 pre-saved routes using 15 delivered/in_transit orders ----
        $usedForRoutes = array_slice($orderIds, 0, 15);
        $routeChunks = array_chunk($usedForRoutes, 5);

        foreach ($routeChunks as $idx => $chunk) {
            $routeName = 'Route ' . chr(65 + $idx) . ' — ' . date('M j, Y');
            $routeDate = date('Y-m-d', strtotime("-{$idx} days"));
            $vehicleId = $vehicleIds[$idx % count($vehicleIds)];
            $riderId   = $riderIds[$idx % count($riderIds)];

            // Total distance approximation
            $totalDist = 0;
            for ($j = 1; $j < count($chunk); $j++) {
                $b1 = $chunk[$j - 1]['barangay'];
                $b2 = $chunk[$j]['barangay'];
                if (isset($coords[$b1]) && isset($coords[$b2])) {
                    $dlat = $coords[$b1][0] - $coords[$b2][0];
                    $dlng = $coords[$b1][1] - $coords[$b2][1];
                    $totalDist += sqrt($dlat * $dlat + $dlng * $dlng) * 111; // rough km
                }
            }
            $totalDist = round($totalDist, 2);

            $status = $idx === 0 ? 'completed' : ($idx === 1 ? 'in_progress' : 'planned');

            $db->execute(
                "INSERT INTO routes (route_name, vehicle_id, rider_id, route_date, total_orders, total_distance, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$routeName, $vehicleId, $riderId, $routeDate, count($chunk), $totalDist, $status,
                 date('Y-m-d H:i:s', strtotime("-$idx days"))]
            );
            $newRouteId = $db->queryOne("SELECT LAST_INSERT_ID() as id")['id'];

            // Insert stops in sequence
            $arrivalMinute = 8 * 60; // start 8 AM
            foreach ($chunk as $seq => $o) {
                $b = $o['barangay'];
                $lat = $coords[$b][0] ?? null;
                $lng = $coords[$b][1] ?? null;

                $db->execute(
                    "INSERT INTO route_stops (route_id, tracking_number, barangay, lat, lng, sequence, arrival_minute)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$newRouteId, $o['tracking'], $b, $lat, $lng, $seq, $arrivalMinute]
                );
                $arrivalMinute += rand(8, 20);
            }
        }

        $message = "✅ Successfully seeded: 5 riders, 6 vehicles, 30 orders, 3 routes.";
    } catch (Exception $e) {
        $error = "Seeding failed: " . $e->getMessage();
    }
}

// Stats for display
$userCount     = $db->queryOne("SELECT COUNT(*) as c FROM users")['c'];
$vehicleCount  = $db->queryOne("SELECT COUNT(*) as c FROM vehicles")['c'];
$orderCount    = $db->queryOne("SELECT COUNT(*) as c FROM orders")['c'];
$routeCount    = $db->queryOne("SELECT COUNT(*) as c FROM routes")['c'];
$stopCount     = $db->queryOne("SELECT COUNT(*) as c FROM route_stops")['c'];

$pageTitle = 'Sample Data Seeder';
require_once 'includes/header.php';
?>

<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>🌱 Sample Data Seeder</h3>
            <p>Populate the system with realistic Dagupan delivery data for testing and demos</p>
        </div>
    </div>
    <div class="panel-body">

        <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:16px 20px;border-radius:8px;margin-bottom:24px;">
            <strong style="color:#b45309;">⚠️ Warning</strong>
            <p style="color:#92400e;margin-top:6px;font-size:13.5px;">
                Clicking "Seed Database" will <strong>delete all existing orders, routes, vehicles, and rider accounts</strong>.
                The admin account is preserved. Use only for testing/demo purposes.
            </p>
        </div>

        <h4 style="margin-bottom:12px;font-size:14px;">What will be created:</h4>
        <ul style="margin: 0 0 24px 20px; line-height: 1.9; font-size: 13.5px; color:#334155;">
            <li>👥 <strong>5 delivery riders</strong> (rider_juan, rider_maria, ... · password: <code>rider123</code>)</li>
            <li>🚐 <strong>6 vehicles</strong> (mix of vans, trucks, motorcycles, tricycle)</li>
            <li>📦 <strong>30 orders</strong> distributed across 14 Dagupan barangays</li>
            <li>🗺️ <strong>3 pre-saved routes</strong> with 5 stops each — ready to view on the map</li>
        </ul>

        <div style="background:#f1f5f9;border-radius:8px;padding:20px;margin-bottom:24px;">
            <h4 style="margin-bottom:12px;font-size:13px;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;">Current Database Contents</h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:16px;">
                <div><div style="font-size:11px;color:#64748b;">Users</div><div style="font-size:22px;font-weight:700;"><?= $userCount ?></div></div>
                <div><div style="font-size:11px;color:#64748b;">Vehicles</div><div style="font-size:22px;font-weight:700;"><?= $vehicleCount ?></div></div>
                <div><div style="font-size:11px;color:#64748b;">Orders</div><div style="font-size:22px;font-weight:700;"><?= $orderCount ?></div></div>
                <div><div style="font-size:11px;color:#64748b;">Routes</div><div style="font-size:22px;font-weight:700;"><?= $routeCount ?></div></div>
                <div><div style="font-size:11px;color:#64748b;">Stops</div><div style="font-size:22px;font-weight:700;"><?= $stopCount ?></div></div>
            </div>
        </div>

        <form method="POST" onsubmit="return confirm('This will DELETE all orders, routes, vehicles, and riders. Continue?');">
            <input type="hidden" name="seed" value="1">
            <button type="submit" class="btn" style="background:#dc2626;padding:14px 28px;font-size:15px;">
                🌱 Seed Database (Destructive)
            </button>
        </form>

    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>After Seeding — Try These Flows</h3>
            <p>What you can demo immediately</p>
        </div>
    </div>
    <div class="panel-body">
        <ol style="margin-left:20px;line-height:2;font-size:14px;color:#334155;">
            <li>Go to <a href="dashboard.php" style="color:#4f46e5;text-decoration:underline;">Dashboard</a> — see charts populate with 30 orders</li>
            <li>Go to <a href="orders.php" style="color:#4f46e5;text-decoration:underline;">Orders</a> — 30 realistic orders across Dagupan</li>
            <li>Go to <a href="routes.php" style="color:#4f46e5;text-decoration:underline;">Routes</a> — 3 pre-saved routes; click 🗺️ Map on any</li>
            <li>Go to <a href="optimize.php" style="color:#4f46e5;text-decoration:underline;">Route Optimizer</a> — optimize the pending orders</li>
            <li>Login as a rider (e.g., <code>rider_juan</code> / <code>rider123</code>) to test QR scanning</li>
        </ol>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>