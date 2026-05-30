<?php
require_once "../../includes/config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["fee" => 0]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$amount = floatval($_POST['amount'] ?? 0);

$stmt = $conn->prepare("
    SELECT fee 
    FROM fee_rules 
    WHERE tenant_id = ?
      AND min_amount <= ?
      AND max_amount >= ?
    LIMIT 1
");

$stmt->execute([$tenant_id, $amount, $amount]);
$rule = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    "fee" => $rule['fee'] ?? 0
]);
exit;