<?php
require_once "../includes/config.php";
include "../includes/header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];

$where = "WHERE t.tenant_id = ?";
$params = [$tenant_id];

// 🔎 SEARCH
if (!empty($_GET['search'])) {
    $where .= " AND (
        c.name LIKE ? 
        OR t.notes LIKE ? 
        OR t.type LIKE ?
        OR t.amount LIKE ?
    )";

    $search = "%{$_GET['search']}%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// 🎯 FILTERS
if (!empty($_GET['type'])) {
    $where .= " AND t.type = ?";
    $params[] = $_GET['type'];
}

if (!empty($_GET['payment_status'])) {
    $where .= " AND t.payment_status = ?";
    $params[] = $_GET['payment_status'];
}

if (!empty($_GET['status'])) {
    $where .= " AND t.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $where .= " AND DATE(t.created_at) BETWEEN ? AND ?";
    $params[] = $_GET['from'];
    $params[] = $_GET['to'];
}

$stmt = $conn->prepare("
    SELECT 
        t.*,
        c.name AS customer_name,
        @balance := @balance +
            CASE 
                WHEN t.type = 'cash_in' THEN t.amount
                WHEN t.type = 'replenish' THEN t.amount
                WHEN t.type = 'cash_out' THEN -t.amount
            END AS running_balance
    FROM transactions t
    LEFT JOIN customers c ON c.id = t.customer_id
    CROSS JOIN (SELECT @balance := 0) init
    $where
    ORDER BY t.created_at ASC
");

$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>

<h2>Transactions</h2>

<div class="card" style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">

    <div>
        <strong>All Transactions</strong><br>
        <small style="color:#6b7280;">Manage your cash in & cash out records</small>
    </div>

    <a href="add-transaction.php">
        <button>+ Add Transaction</button>
    </a>

</div>

<div class="card">

<form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:nowrap;overflow-x:auto;margin-bottom:15px;">

    <input type="text" name="search" placeholder="Search name, notes, type..."
        value="<?= $_GET['search'] ?? '' ?>">

    <select name="type">
        <option value="">All Types</option>

        <option value="cash_in" <?= ($_GET['type'] ?? '') == 'cash_in' ? 'selected' : '' ?>>
            Cash In
        </option>

        <option value="cash_out" <?= ($_GET['type'] ?? '') == 'cash_out' ? 'selected' : '' ?>>
            Cash Out
        </option>

        <option value="replenish" <?= ($_GET['type'] ?? '') == 'replenish' ? 'selected' : '' ?>>
            Replenish
        </option>
    </select>

    <select name="payment_status">
        <option value="">Payment</option>

        <option value="paid" <?= ($_GET['payment_status'] ?? '') == 'paid' ? 'selected' : '' ?>>
            Paid
        </option>

        <option value="unpaid" <?= ($_GET['payment_status'] ?? '') == 'unpaid' ? 'selected' : '' ?>>
            Unpaid
        </option>
    </select>

    <select name="status">
        <option value="">Status</option>

        <option value="claimed" <?= ($_GET['status'] ?? '') == 'claimed' ? 'selected' : '' ?>>
            Claimed
        </option>

        <option value="unclaimed" <?= ($_GET['status'] ?? '') == 'unclaimed' ? 'selected' : '' ?>>
            Unclaimed
        </option>
    </select>

    <button type="submit">Filter</button>
    <a href="transactions.php" style="text-decoration:none;padding:6px 10px;background:#f3f4f6;border-radius:6px;">Reset</a>

</form>

<table>
    <tr>
        <th>Type</th>
        <th>Amount</th>
        <th>Fee</th>
        <th>Customer</th>
        <th>Reference / Notes</th>
        <th>Status</th>
        <th>Date</th>
        <th>Payment</th>
        <th>Action</th>
        <th>Running Balance</th>
    </tr>

    <?php if (count($transactions) == 0): ?>
        <tr>
            <td colspan="7" style="text-align:center; padding:20px; color:#6b7280;">
                No transactions found.
            </td>
        </tr>
    <?php endif; ?>

    <?php foreach ($transactions as $t): ?>
    <tr>
        <td><?= htmlspecialchars($t['type']) ?></td>
        <td>₱<?= number_format($t['amount'], 2) ?></td>
        <td>₱<?= number_format($t['fee'], 2) ?></td>
        <td><?= htmlspecialchars($t['customer_name'] ?? 'No Customer') ?></td>
        <td><?= htmlspecialchars($t['notes']) ?></td>
        <td><?= htmlspecialchars($t['notes'] ?? '') ?></td>
        <td><?= $t['created_at'] ?></td>
        <td>
            <?php if ($t['payment_status'] == 'paid'): ?>
                <span style="color:green;">Paid</span>
            <?php else: ?>
                <span style="color:red;">Unpaid</span>
            <?php endif; ?>
        </td>
        <td>

            <a href="edit-transaction.php?id=<?= $t['id'] ?>">
                ✏️ Edit
            </a>

            <form method="POST" action="delete-transaction.php" style="display:inline;">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">

                <button type="submit"
                        class="btn-danger"
                        onclick="return confirm('Delete this transaction?')">
                    🗑 Delete
                </button>

            </form>

        </td>
        <td>₱<?= number_format($t['running_balance'], 2) ?></td>
    </tr>
    <?php endforeach; ?>

</table>

</div>

<script>
let timeout;

document.querySelector('input[name="search"]').addEventListener('input', function () {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        this.form.submit();
    }, 400);
});
</script>

<?php include "../includes/footer.php"; ?>