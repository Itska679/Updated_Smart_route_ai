<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$message = '';
$error = '';

// Handle QR scan submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['qr_code'])) {
    $qr = trim($_POST['qr_code']);

    $order = $db->queryOne("SELECT * FROM orders WHERE tracking_number = ?", [$qr]);

    if (!$order) {
        $error = "❌ No order found with tracking number: " . htmlspecialchars($qr);
    } elseif ($order['status'] === 'delivered') {
        $error = "⚠️ Order " . htmlspecialchars($qr) . " is already marked as delivered.";
    } else {
        $db->execute(
            "UPDATE orders SET status = 'delivered' WHERE id = ?",
            [$order['id']]
        );
        $message = "✅ Order " . htmlspecialchars($qr) . " marked as delivered.";
    }
}

// Get rider's assigned routes (if role is rider)
$myRoutes = [];
if ($_SESSION['role'] === 'rider') {
    $myRoutes = $db->queryAll("
        SELECT r.*, v.plate_number
        FROM routes r
        LEFT JOIN vehicles v ON r.vehicle_id = v.id
        WHERE r.rider_id = ? AND r.route_date = CURDATE()
    ", [$_SESSION['user_id']]);
}

$pageTitle = 'Deliver Package';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert success"><?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert error"><?= $error ?></div>
<?php endif; ?>

<?php if ($_SESSION['role'] === 'rider' && !empty($myRoutes)): ?>
<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Today's Routes</h3>
            <p>Your assignments for <?= date('F j, Y') ?></p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Route</th><th>Vehicle</th><th>Orders</th><th>Distance</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($myRoutes as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['route_name']) ?></strong></td>
                        <td><?= htmlspecialchars($r['plate_number'] ?? '—') ?></td>
                        <td><?= $r['total_orders'] ?></td>
                        <td><?= number_format($r['total_distance'], 2) ?> km</td>
                        <td><span class="badge <?= $r['status'] ?>"><?= str_replace('_',' ', $r['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Scan Package QR Code</h3>
            <p>Point your camera at the QR code on the package to mark it as delivered</p>
        </div>
    </div>
    <div class="panel-body" style="text-align:center;">

        <div id="qr-reader" style="width:100%;max-width:500px;margin:0 auto 20px;"></div>

        <div style="margin-bottom:20px;">
            <button type="button" class="btn" id="startBtn" onclick="startScanner()">📷 Start Camera</button>
            <button type="button" class="btn-secondary" id="stopBtn" onclick="stopScanner()" style="display:none;">⏹️ Stop Camera</button>
        </div>

        <div style="border-top:1px solid #e2e8f0;padding-top:20px;max-width:500px;margin:0 auto;">
            <p style="font-size:13px;color:#64748b;margin-bottom:12px;">
                📵 Camera not working? Enter the tracking number manually:
            </p>
            <form method="POST" style="display:flex;gap:8px;">
                <input type="text" name="qr_code" placeholder="e.g., 2GO-00123" required
                       style="flex:1;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:14px;">
                <button type="submit" class="btn">Mark Delivered</button>
            </form>
        </div>

    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrCode = null;

async function startScanner() {
    try {
        html5QrCode = new Html5Qrcode("qr-reader");
        await html5QrCode.start(
            { facingMode: "environment" }, // rear camera on mobile
            { fps: 10, qrbox: { width: 250, height: 250 } },
            onScanSuccess,
            () => {} // ignore scan errors (frequent, not important)
        );
        document.getElementById('startBtn').style.display = 'none';
        document.getElementById('stopBtn').style.display = 'inline-block';
    } catch (err) {
        alert("Camera error: " + err + "\n\nTip: Make sure you're using HTTPS or localhost, and allow camera permissions.");
    }
}

async function stopScanner() {
    if (html5QrCode) {
        await html5QrCode.stop();
        html5QrCode.clear();
        document.getElementById('startBtn').style.display = 'inline-block';
        document.getElementById('stopBtn').style.display = 'none';
    }
}

function onScanSuccess(decodedText) {
    // Pause scanner briefly to avoid multiple triggers
    stopScanner();

    // Auto-submit the QR code to the server
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="qr_code" value="${decodedText.replace(/"/g, '&quot;')}">`;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>