<?php
require_once "../includes/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];

if ($_POST && isset($_POST['id'])) {

    $id = intval($_POST['id']);

    // 🔒 DELETE ONLY OWN TENANT DATA
    $stmt = $conn->prepare("
        DELETE FROM transactions 
        WHERE id = ? AND tenant_id = ?
    ");

    $stmt->execute([$id, $tenant_id]);
}

header("Location: transactions.php");
exit;