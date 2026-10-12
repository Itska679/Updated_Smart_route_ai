<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();

// ===== STATS =====
$totalOrders     = $db->queryOne("SELECT COUNT(*) as count FROM orders")['count'];
$delivered       = $db->queryOne("SELECT COUNT(*) as count FROM orders WHERE status = 'delivered'")['count'];
$inTransit       = $db->queryOne("SELECT COUNT(*) as count FROM orders WHERE status = 'in_transit'")['count'];
$pending         = $db->queryOne("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")['count'];
$failed          = $db->queryOne("SELECT COUNT(*) as count FROM orders WHERE status = 'failed'")['count'];
$totalVehicles   = $db->queryOne("SELECT COUNT(*) as count FROM vehicles")['count'];
$totalBarangays  = $db->queryOne("SELECT COUNT(*) as count FROM barangays")['count'];
$totalFloodZones = $db->queryOne("SELECT COUNT(*) as count FROM flood_zones")['count'];
$activeRoutes    = $db->queryOne("SELECT COUNT(*) as count FROM routes WHERE status IN ('planned','in_progress')")['count'];

// ===== DELIVERY TRENDS (last 7 days) =====
$trendRows = $db->queryAll("
    SELECT DATE(created_at) as day, COUNT(*) as total
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day
");

// Fill missing days with 0
$trendMap = [];
foreach ($trendRows as $r) { $trendMap[$r['day']] = (int)$r['total']; }
$trendLabels = [];
$trendData   = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('D, M j', strtotime($day));
    $trendData[]   = $trendMap[$day] ?? 0;
}

// ===== TOP BARANGAYS BY VOLUME =====
$topBarangays = $db->queryAll("
    SELECT barangay, COUNT(*) as total
    FROM orders
    WHERE barangay IS NOT NULL AND barangay != ''
    GROUP BY barangay
    ORDER BY total DESC
    LIMIT 8
");

// ===== STATUS BREAKDOWN =====
$statusBreakdown = [
    'Delivered' => (int)$delivered,
    'In Transit' => (int)$inTransit,
    'Pending'    => (int)$pending,
    'Failed'     => (int)$failed
];

$recentOrders = $db->queryAll("SELECT * FROM orders ORDER BY created_at DESC LIMIT 6");

$pageTitle = 'Dashboard Overview';
require_once 'includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon indigo">📦</div>
        <div class="stat-details"><p>Total Orders</p><h2><?= number_format($totalOrders) ?></h2></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div class="stat-details"><p>Delivered</p><h2><?= number_format($delivered) ?></h2></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">🚚</div>
        <div class="stat-details"><p>In Transit</p><h2><?= number_format($inTransit) ?></h2></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">⏳</div>
        <div class="stat-details"><p>Pending</p><h2><?= number_format($pending) ?></h2></div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon indigo">🚐</div>
        <div class="stat-details"><p>Fleet Size</p><h2><?= number_format($totalVehicles) ?></h2></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">📍</div>
        <div class="stat-details"><p>Barangays Covered</p><h2><?= number_format($totalBarangays) ?></h2></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">🌊</div>
        <div class="stat-details"><p>Flood Zones</p><h2><?= number_format($totalFloodZones) ?></h2></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">🗺️</div>
        <div class="stat-details"><p>Active Routes</p><h2><?= number_format($activeRoutes) ?></h2></div>
    </div>
</div>

<!-- CHARTS ROW -->
<div class="charts-grid">
    <div class="panel">
        <div class="panel-header">
            <div>
                <h3>📈 Delivery Trend (Last 7 Days)</h3>
                <p>Number of orders created per day</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="chart-wrap">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <h3>📊 Order Status Breakdown</h3>
                <p>Distribution of current statuses</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="chart-wrap">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- TOP BARANGAYS -->
<div class="panel">
    <div class="panel-header">
        <div>
            <h3>🏆 Top Barangays by Volume</h3>
            <p>Where most deliveries are going</p>
        </div>
    </div>
    <div class="panel-body">
        <?php if (empty($topBarangays)): ?>
            <div class="empty-state">
                <div class="icon">📊</div>
                <h4>No order data yet</h4>
                <p>Add some orders to see barangay rankings.</p>
            </div>
        <?php else: ?>
            <div class="chart-wrap" style="height:320px;">
                <canvas id="barangayChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MAP PANEL -->
<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Live Delivery Map — Dagupan City</h3>
            <p>Barangay coverage and active flood zones</p>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn-sm" onclick="toggleLayer('barangayLayer')">📍 Barangays</button>
            <button class="btn-sm danger" onclick="toggleLayer('floodLayer')">🌊 Flood Zones</button>
        </div>
    </div>
    <div id="map" style="height:480px;width:100%;"></div>
</div>

<!-- RECENT ORDERS -->
<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Recent Orders</h3>
            <p>Latest deliveries entering the system</p>
        </div>
        <a href="orders.php" class="btn">View All Orders</a>
    </div>
    <div class="table-wrap">
        <?php if (empty($recentOrders)): ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <h4>No orders yet</h4>
                <p>Add your first order to get started.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Tracking #</th><th>Recipient</th><th>Barangay</th>
                        <th>Status</th><th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($o['tracking_number']) ?></strong></td>
                            <td><?= htmlspecialchars($o['recipient_name']) ?></td>
                            <td><?= htmlspecialchars($o['barangay']) ?></td>
                            <td><span class="badge <?= $o['status'] ?>"><?= str_replace('_',' ', $o['status']) ?></span></td>
                            <td><?= date('M j, Y g:i A', strtotime($o['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
// ========== CHARTS ==========
const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: { font: { family: 'inherit', size: 12 }, color: '#475569' }
        }
    }
};

// Delivery Trend (line chart)
const trendCtx = document.getElementById('trendChart').getContext('2d');
const gradient = trendCtx.createLinearGradient(0, 0, 0, 300);
gradient.addColorStop(0, 'rgba(236, 0, 140, 0.25)');
gradient.addColorStop(1, 'rgba(236, 0, 140, 0.0)');

new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [{
            label: 'Orders',
            data: <?= json_encode($trendData) ?>,
            borderColor: '#ec008c',
            backgroundColor: gradient,
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#ec008c',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        ...chartDefaults,
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0, color: '#64748b' },
                grid: { color: '#f1f5f9' }
            },
            x: {
                ticks: { color: '#64748b' },
                grid: { display: false }
            }
        }
    }
});

// Status Breakdown (doughnut)
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($statusBreakdown)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($statusBreakdown)) ?>,
            backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#ef4444'],
            borderColor: '#fff',
            borderWidth: 3,
            hoverOffset: 8
        }]
    },
    options: {
        ...chartDefaults,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 16, font: { size: 12 }, color: '#475569' }
            }
        }
    }
});

<?php if (!empty($topBarangays)): ?>
// Top Barangays (horizontal bar)
const barangayCtx = document.getElementById('barangayChart').getContext('2d');
new Chart(barangayCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($topBarangays, 'barangay')) ?>,
        datasets: [{
            label: 'Orders',
            data: <?= json_encode(array_map('intval', array_column($topBarangays, 'total'))) ?>,
            backgroundColor: [
    		'#ec008c','#f472b6','#fb7fb5','#fca5c4',
    		'#fbcfe0','#c70077','#a30061','#7a0048'
		],
            borderRadius: 8,
            barThickness: 26
        }]
    },
    options: {
        ...chartDefaults,
        indexAxis: 'y',
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: { precision: 0, color: '#64748b' },
                grid: { color: '#f1f5f9' }
            },
            y: {
                ticks: { color: '#1e293b', font: { weight: '600' } },
                grid: { display: false }
            }
        }
    }
});
<?php endif; ?>

// ========== LEAFLET MAP ==========
const DAGUPAN = [16.0433, 120.3333];
// Dagupan City bounding box — tighter, covers only the city proper
const DAGUPAN_BOUNDS = [
    [16.0150, 120.3050], // Southwest corner
    [16.0750, 120.3850]  // Northeast corner
];

const map = L.map('map', {
    maxBounds: DAGUPAN_BOUNDS,
    maxBoundsViscosity: 1.0,
    minZoom: 13,        // ← Raised from 12 to 13 — this is the key fix
    maxZoom: 17,
    zoomControl: true,
    scrollWheelZoom: true
});

map.fitBounds(DAGUPAN_BOUNDS);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

const barangays = [
    { name: "Lucao", lat: 16.0325, lng: 120.3355, zone: "Central" },
    { name: "Mayombo", lat: 16.0389, lng: 120.3278, zone: "Central" },
    { name: "Bonuan Gueset", lat: 16.0597, lng: 120.3444, zone: "East" },
    { name: "Bonuan Boquig", lat: 16.0645, lng: 120.3512, zone: "East" },
    { name: "Bonuan Binloc", lat: 16.0701, lng: 120.3613, zone: "East" },
    { name: "Poblacion Oeste", lat: 16.0433, lng: 120.3333, zone: "Poblacion" },
    { name: "Barangay I", lat: 16.0417, lng: 120.3372, zone: "Poblacion" },
    { name: "Barangay IV", lat: 16.0450, lng: 120.3300, zone: "Poblacion" },
    { name: "Carael", lat: 16.0406, lng: 120.3200, zone: "West" },
    { name: "Calmay", lat: 16.0360, lng: 120.3160, zone: "West" },
    { name: "Tapuac", lat: 16.0470, lng: 120.3420, zone: "Central" },
    { name: "Herrero", lat: 16.0390, lng: 120.3400, zone: "Central" },
    { name: "Caranglaan", lat: 16.0333, lng: 120.3300, zone: "Central" },
    { name: "Lasip Grande", lat: 16.0300, lng: 120.3350, zone: "Central" },
    { name: "Malued", lat: 16.0520, lng: 120.3350, zone: "North" },
    { name: "Pantal", lat: 16.0560, lng: 120.3290, zone: "North" },
    { name: "Tambac", lat: 16.0330, lng: 120.3150, zone: "West" },
    { name: "Pugaro Suit", lat: 16.0680, lng: 120.3450, zone: "East" }
];

const floodZones = [
    { name: "Downtown–Lucao Corridor", lat: 16.0345, lng: 120.3330, severity: "high" },
    { name: "Tondaligan Baywalk", lat: 16.0580, lng: 120.3420, severity: "medium" },
    { name: "Mayombo–Caranglaan", lat: 16.0360, lng: 120.3290, severity: "high" }
];

const barangayLayer = L.layerGroup();
const floodLayer = L.layerGroup();

barangays.forEach(b => {
    L.circleMarker([b.lat, b.lng], {
        radius: 7, fillColor: "#ec008c", color: "#fff",
        weight: 2, opacity: 1, fillOpacity: 0.9
    }).bindPopup(`<strong>${b.name}</strong><br>Zone: ${b.zone}`).addTo(barangayLayer);
});

const floodColors = { high: "#dc2626", medium: "#d97706", low: "#059669" };
floodZones.forEach(f => {
    L.circle([f.lat, f.lng], {
        radius: 400, color: floodColors[f.severity],
        fillColor: floodColors[f.severity], fillOpacity: 0.25, weight: 2
    }).bindPopup(`<strong>🌊 ${f.name}</strong><br>Severity: ${f.severity}`).addTo(floodLayer);
});

barangayLayer.addTo(map);
floodLayer.addTo(map);

function toggleLayer(layerName) {
    const layer = layerName === 'barangayLayer' ? barangayLayer : floodLayer;
    if (map.hasLayer(layer)) map.removeLayer(layer);
    else map.addLayer(layer);
}
</script>

<?php require_once 'includes/footer.php'; ?>