<?php
require_once "../includes/config.php";


if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];

//
// 💾 SAVE TRANSACTION
//
if ($_POST && isset($_POST['save'])) {

    $isFree = isset($_POST['free_transaction']);

    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $customer_id = $_POST['customer_id'];
    $notes = $_POST['notes'];
    $status = $_POST['status'];
    $payment_status = $_POST['payment_status'];

    // 🔥 GET FEE RULE FIRST
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

    // ✅ APPLY FREE LOGIC AFTER RULE
    $fee = $isFree ? 0 : ($rule['fee'] ?? 0);

    // 💾 INSERT
    $stmt = $conn->prepare("
        INSERT INTO transactions 
        (tenant_id, user_id, customer_id, type, amount, fee, notes, status, payment_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $tenant_id,
        $user_id,
        $customer_id,
        $type,
        $amount,
        $fee,
        $notes,
        $status,
        $payment_status
    ]);

    header("Location: /app/transactions.php");
    exit;
}

include "../includes/header.php";
?>

<h2>Add Transaction</h2>

<div class="card">

    <form method="POST">

        <label>Transaction Type</label>
        <select name="type" required>
            <option value="cash_in">Cash In</option>
            <option value="cash_out">Cash Out</option>
        </select>

        <label>Amount</label>
        <input type="number" name="amount" id="amount" placeholder="Enter amount" required>

        <!-- FEE PREVIEW CARD -->
        <div class="card" style="margin-top:10px; background:#f8fafc;">
            💰 Estimated Fee:
            <strong>₱<span id="feePreview">0.00</span></strong>
        </div>

        <small id="freeNotice" style="color:green; display:none;">
            Free transaction applied
        </small>

        <label class="checkbox">
            <input type="checkbox" id="freeTransaction" name="free_transaction">
            <span>Free Transaction</span>
        </label>

        <label>Customer</label>
        <select name="customer_id">

            <option value="">-- Select Customer --</option>

            <?php
            $stmt = $conn->prepare("
                SELECT id, name 
                FROM customers 
                WHERE tenant_id = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$tenant_id]);
            $customers = $stmt->fetchAll();

            foreach ($customers as $c):
            ?>
                <option value="<?= $c['id'] ?>">
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <label>Status</label>
        <select name="status">
            <option value="claimed">Claimed</option>
            <option value="unclaimed">Unclaimed</option>
        </select>

        <label>Notes</label>
        <textarea name="notes" placeholder="Optional notes"></textarea>

        <label>Payment Status</label>
        <select name="payment_status">
            <option value="unpaid">Unpaid</option>
            <option value="paid">Paid</option>
        </select>

        <br>

        <button type="submit" name="save">
            💾 Save Transaction
        </button>

    </form>

</div>

<script>
let currentFee = 0;

document.getElementById("amount").addEventListener("input", function () {

    let amount = this.value;

    fetch("ajax/get-fee.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "amount=" + encodeURIComponent(amount)
    })
    .then(res => res.json())
    .then(data => {

        currentFee = parseFloat(data.fee || 0);

        updateFee();

    });

});

document.getElementById("freeTransaction").addEventListener("change", function () {
    updateFee();
});

function updateFee() {

    let isFree = document.getElementById("freeTransaction").checked;

    let displayFee = isFree ? 0 : currentFee;

    document.getElementById("feePreview").innerText = displayFee.toFixed(2);

    document.getElementById("freeNotice").style.display =
        isFree ? "block" : "none";
}
</script>
<?php include "../includes/footer.php"; ?>