<?php
session_start();
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new Auth();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($auth->login($username, $password)) {
    // Redirect based on role
    if ($_SESSION['role'] === 'rider') {
        header("Location: rider/deliver.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
	} else {
        header("Location: index.php?error=1");
    }
    exit;
}