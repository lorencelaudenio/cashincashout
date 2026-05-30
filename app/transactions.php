<?php
require_once "../includes/config.php";
include "../includes/header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];

$stmt = $conn->prepare("
    SELECT 
        t.*,
        c.name AS customer_name,
        @balance := @balance +
            CASE 
                WHEN t.type = 'cash_in' THEN t.amount
                WHEN t.type = 'cash_out' THEN -t.amount
            END AS running_balance
    FROM transactions t
    LEFT JOIN customers c ON c.id = t.customer_id
    CROSS JOIN (SELECT @balance := 0) init
    WHERE t.tenant_id = ?
    ORDER BY t.created_at ASC
");

$stmt->execute([$tenant_id]);
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
        <td><?= htmlspecialchars($t['status']) ?></td>
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
<?php include "../includes/footer.php"; ?>