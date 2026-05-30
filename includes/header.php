<?php
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>CashTrack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<div class="navbar">
    <a href="/app/dashboard.php" class="logo-link">
        💰 Cashin Cashout System
    </a>
</div>

<div class="sidebar">

    <a href="/app/dashboard.php">📊 Dashboard</a>
    <a href="/app/transactions.php">💰 Transactions</a>
    <a href="/app/customers.php">👥 Customers</a>
    <a href="/app/add-transaction.php">➕ Add Transaction</a>

    <hr>

    <a href="/app/settings.php">⚙️ Settings</a>

    <hr>

    <a href="/auth/logout.php" class="danger-link">🚪 Logout</a>

</div>

<div class="content">