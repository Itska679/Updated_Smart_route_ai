<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

$orderId = (int)($_GET['id'] ?? 0);
$order = $db->queryOne("SELECT * FROM orders WHERE id = ?", [$orderId]);

if (!$order) {
    die("Order not found.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt — <?= htmlspecialchars($order['tracking_number']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            padding: 30px;
            max-width: 420px;
            margin: 0 auto;
            background: #fff;
            color: #000;
        }
        .header { text-align: center; padding-bottom: 15px; border-bottom: 2px dashed #000; margin-bottom: 20px; }
        .header h1 { font-size: 20px; letter-spacing: 1px; }
        .header p { font-size: 12px; margin-top: 4px; }
        .logo { font-size: 32px; margin-bottom: 5px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
        .row .label { color: #555; }
        .row .value { font-weight: bold; text-align: right; }
        .divider { border-top: 1px dashed #000; margin: 15px 0; }
        .total { font-size: 16px; font-weight: bold; text-align: center; padding: 12px; border: 2px solid #000; margin: 15px 0; }
        .footer { text-align: center; font-size: 11px; color: #555; margin-top: 25px; padding-top: 15px; border-top: 1px dashed #000; }
        .print-btn {
            display: block;
            margin: 20px auto;
            padding: 12px 24px;
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }
        @media print { .print-btn { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">🚚</div>
        <h1>2GO EXPRESS</h1>
        <p>Dagupan City Branch</p>
    </div>

    <div class="row">
        <span class="label">Tracking #:</span>
        <span class="value"><?= htmlspecialchars($order['tracking_number']) ?></span>
    </div>
    <div class="row">
        <span class="label">Date:</span>
        <span class="value"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
    </div>

    <div class="divider"></div>

    <div class="row">
        <span class="label">Recipient:</span>
        <span class="value"><?= htmlspecialchars($order['recipient_name']) ?></span>
    </div>
    <div class="row">
        <span class="label">Barangay:</span>
        <span class="value"><?= htmlspecialchars($order['barangay']) ?></span>
    </div>
    <div class="row">
        <span class="label">Address:</span>
        <span class="value"><?= htmlspecialchars($order['address'] ?? '—') ?></span>
    </div>

    <div class="divider"></div>

    <div class="row">
        <span class="label">Status:</span>
        <span class="value"><?= strtoupper(str_replace('_', ' ', $order['status'])) ?></span>
    </div>

    <div class="total">
        <?= strtoupper(str_replace('_', ' ', $order['status'])) ?>
    </div>

    <div class="footer">
        Thank you for choosing 2GO Express!<br>
        For inquiries: 2go.com.ph | (075) 123-4567
    </div>

    <button class="print-btn" onclick="window.print()">🖨️ Print Receipt</button>
</body>
</html>