<?php
require_once __DIR__ . '/../../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$current = basename($_SERVER['PHP_SELF'], '.php');
$pageTitle = $pageTitle ?? 'Rider Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — 2GO Rider</title>
	<link rel="icon" type="image/png" href="/smart-route/assets/img/2go-logo.png">
	<link rel="apple-touch-icon" href="/smart-route/assets/img/2go-logo.png">
    <base href="/smart-route/">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <style>
          .rider-topbar {
            background: linear-gradient(135deg, #ec008c, #c70077);
            color: #fff;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(79, 70, 229, 0.25);
        }
        .rider-topbar .logo { font-weight: 700; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .rider-topbar .user { font-size: 13px; text-align: right; }
        .rider-topbar .user strong { display: block; }
        .rider-topbar .user small { opacity: 0.85; }
        .rider-nav {
            display: flex;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 68px;
            z-index: 99;
            overflow-x: auto;
        }
        .rider-nav a {
            flex: 1;
            text-align: center;
            padding: 14px 16px;
            font-size: 13.5px;
            font-weight: 600;
            color: #64748b;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
            min-width: 120px;
        }
          .rider-nav a.active {
            color: #ec008c;
            border-bottom-color: #ec008c;
        }
        .rider-nav a:hover { color: #ec008c; }
        .rider-content {
            padding: 22px;
            max-width: 900px;
            margin: 0 auto;
            min-height: calc(100vh - 200px);
        }
        @media (max-width: 600px) {
            .rider-content { padding: 16px; }
            .rider-topbar { padding: 12px 16px; }
        }
    </style>
</head>
<body style="background:#f4f6fb;margin:0;">

<div class="rider-topbar">
    <div class="logo">🚚 Smart-Route Rider</div>
    <div class="user">
        <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
        <small><?= htmlspecialchars($_SESSION['role']) ?></small>
    </div>
</div>

<nav class="rider-nav">
    <a href="rider/deliver.php" class="<?= $current === 'deliver' ? 'active' : '' ?>">📷 Scan Delivery</a>
    <a href="rider/my_routes.php" class="<?= $current === 'my_routes' ? 'active' : '' ?>">🗺️ My Routes</a>
    <a href="logout.php" style="flex:0;min-width:auto;color:#dc2626;">🚪 Logout</a>
</nav>

<main class="rider-content">