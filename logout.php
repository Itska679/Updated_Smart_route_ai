<?php
session_start();
require_once 'includes/auth.php';
$auth = new Auth();
$auth->logout();