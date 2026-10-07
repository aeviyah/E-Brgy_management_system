<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Barangay System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-header">
        <div class="brand">🏛️ E-Barangay Admin Shell</div>
        <div class="user-profile">
            <span>👤 <?= htmlspecialchars($_SESSION['username']); ?> (<strong><?= htmlspecialchars($_SESSION['role']); ?></strong>)</span>
            <a href="logout.php" class="btn-logout-sm">Logout</a>
        </div>
    </div>
    <div class="app-body"></div>