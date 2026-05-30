<?php
require_once "../includes/config.php";


if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];

$id = $_GET['id'] ?? null;

//
// 🔍 GET TRANSACTION
//
$stmt = $conn->prepare("
    SELECT * FROM transactions 
    WHERE id = ? AND tenant_id = ?
    LIMIT 1
");
$stmt->execute([$id, $tenant_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    die("Transaction not found");
}

//
// 💾 UPDATE
//
if ($_POST && isset($_POST['update'])) {
    $isFree = isset($_POST['free_transaction']);

    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $customer = $_POST['customer_name'];
    $notes = $_POST['notes'];
    $status = $_POST['status'];
    $payment_status = $_POST['payment_status']; // ✅ FIXED

    // get fee rule
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

    // apply rule OR free override
    $fee = $isFree ? 0 : ($rule['fee'] ?? 0);

    // 💾 UPDATE TRANSACTION
    $stmt = $conn->prepare("
        UPDATE transactions 
        SET type = ?,
            amount = ?,
            fee = ?,
            customer_name = ?,
            notes = ?,
            status = ?,
            payment_status = ?
        WHERE id = ? AND tenant_id = ?
    ");

    $stmt->execute([
        $type,
        $amount,
        $fee,
        $customer,
        $notes,
        $status,
        $payment_status,
        $id,
        $tenant_id
    ]);

    header("Location: /app/transactions.php");
    exit;
}

include "../includes/header.php";
?>

<h2>Edit Transaction</h2>

<form method="POST">

    <select name="type">
        <option value="cash_in" <?= $transaction['type']=='cash_in'?'selected':'' ?>>Cash In</option>
        <option value="cash_out" <?= $transaction['type']=='cash_out'?'selected':'' ?>>Cash Out</option>
    </select>

    <br><br>

    <input type="number" name="amount"
           value="<?= $transaction['amount'] ?>" required>

    <label class="checkbox">
        <input type="checkbox" id="freeTransaction" name="free_transaction">
        <span>Free Transaction</span>
    </label>

    <br><br>

    <select name="customer_id">
        <?php
        $stmt = $conn->prepare("SELECT id, name FROM customers WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        foreach ($stmt->fetchAll() as $c):
        ?>
            <option value="<?= $c['id'] ?>"
                <?= $transaction['customer_id'] == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <select name="status">
        <option value="claimed" <?= $transaction['status']=='claimed'?'selected':'' ?>>Claimed</option>
        <option value="unclaimed" <?= $transaction['status']=='unclaimed'?'selected':'' ?>>Unclaimed</option>
    </select>

    <label>Payment Status</label>
    <select name="payment_status">
        <option value="unpaid" <?= $transaction['payment_status']=='unpaid'?'selected':'' ?>>
            Unpaid
        </option>
        <option value="paid" <?= $transaction['payment_status']=='paid'?'selected':'' ?>>
            Paid
        </option>
    </select>

    <br><br>

    <textarea name="notes"><?= htmlspecialchars($transaction['notes']) ?></textarea>

    <br><br>

    <button type="submit" name="update">Update Transaction</button>

</form>
<?php include "../includes/footer.php"; ?>