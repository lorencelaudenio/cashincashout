<?php
require_once "../includes/config.php";


if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(
        CASE 
            WHEN type = 'cash_in' THEN amount
            WHEN type = 'replenish' THEN amount
            WHEN type = 'cash_out' THEN -amount
            ELSE 0
        END
    ),0) AS balance
    FROM transactions
    WHERE tenant_id = ?
");

$stmt->execute([$tenant_id]);
$available_balance = (float)$stmt->fetch()['balance'];

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
    $isReplenish = ($type === "replenish");
    $isFree = isset($_POST['free_transaction']);

    if ($isReplenish || $isFree) {
        $fee = 0;
    } else {
        $fee = $rule['fee'] ?? 0;
    }

    $isReplenish = ($type === "replenish");

    if ($isReplenish) {
        $customer_id = null;
        $status = null;
        $payment_status = null;
    }

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

    <div class="card" style="margin-bottom:10px;">
        🏦 Available Balance:
        <strong id="availableBalance">₱<?= number_format($available_balance,2) ?></strong>
    </div>

    <form method="POST">

        <label>Transaction Type</label>
        <select name="type" id="type" required>
            <option value="cash_in">Cash In</option>
            <option value="cash_out">Cash Out</option>
            <option value="replenish">Replenish (Cash Float)</option>
        </select>

        <label>Amount</label>
        <input type="number" name="amount" id="amount" placeholder="Enter amount" required>

        <!-- FEE PREVIEW CARD -->
        <div class="card" style="margin-top:10px; background:#f8fafc;">
            💰 Estimated Fee:
            <strong>₱<span id="feePreview">0.00</span></strong>
        </div>

        <div class="card" style="margin-top:10px; background:#f1f5f9;">
            ➕ New Balance After Transaction:
            <strong>₱<span id="newBalance"><?= number_format($available_balance,2) ?></span></strong>
        </div>

        <small id="freeNotice" style="color:green; display:none;">
            Free transaction applied. State reason in notes.
        </small>

        <label class="checkbox">
            <input type="checkbox" id="freeTransaction" name="free_transaction">
            <span>Free Transaction</span>
        </label>

        <label>Customer</label>
        <select name="customer_id" id="customerField">

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
        <select name="status" id="statusField">
            <option value="claimed">Claimed</option>
            <option value="unclaimed">Unclaimed</option>
        </select>

        <label>Notes</label>
        <textarea name="notes" placeholder="Optional notes"></textarea>

        <label>Payment Status</label>
        <select name="payment_status" id="paymentField">
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
const typeField = document.getElementById("type");
const amountField = document.getElementById("amount");
const feePreview = document.getElementById("feePreview");
const freeCheckbox = document.getElementById("freeTransaction");

const customerField = document.getElementById("customerField");
const statusField = document.getElementById("statusField");
const paymentField = document.getElementById("paymentField");

const availableBalance = <?= $available_balance ?>;
const newBalanceEl = document.getElementById("newBalance");

let currentFee = 0;

function updateUI() {

    const isReplenish = typeField.value === "replenish";
    const isFree = freeCheckbox.checked;

    // disable fields
    customerField.disabled = isReplenish;
    statusField.disabled = isReplenish;
    paymentField.disabled = isReplenish;

    if (isReplenish) {

        // 🔥 CLEAR VALUES
        customerField.value = "";
        statusField.value = "";
        paymentField.value = "";

        feePreview.innerText = "0.00";

        return;
    }

    let finalFee = isFree ? 0 : currentFee;
    feePreview.innerText = finalFee.toFixed(2);

    document.getElementById("freeNotice").style.display =
        isFree ? "block" : "none";

    updateBalancePreview();
}

// TYPE CHANGE
typeField.addEventListener("change", function () {

    if (this.value === "replenish") {
        feePreview.innerText = "0.00";
    }

    updateUI();
    updateBalancePreview();
});

// FREE CHECKBOX
freeCheckbox.addEventListener("change", updateUI);

// AMOUNT INPUT
amountField.addEventListener("input", function () {

    if (typeField.value === "replenish") {
        feePreview.innerText = "0.00";
        return;
    }

    fetch("ajax/get-fee.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "amount=" + encodeURIComponent(this.value)
    })
    .then(res => res.json())   // 🔥 THIS WAS MISSING
    .then(data => {
        currentFee = parseFloat(data.fee || 0);
        updateUI();
        updateBalancePreview();
    });

});

function updateBalancePreview() {

    const amount = parseFloat(amountField.value || 0);
    const isReplenish = typeField.value === "replenish";

    let newBalance = availableBalance;

    if (isReplenish) {

        // replenish adds money
        newBalance += amount;

    } else {

        if (typeField.value === "cash_in") {
            newBalance += amount;
        }

        if (typeField.value === "cash_out") {
            newBalance -= amount;
        }

        // ❌ fee ignored completely
    }

    newBalanceEl.innerText = newBalance.toFixed(2);
}

// INIT
updateUI();
</script>
<?php include "../includes/footer.php"; ?>