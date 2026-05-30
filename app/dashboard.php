<?php
require_once "../includes/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

include "../includes/header.php";

$range = $_GET['range'] ?? 'all';

$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;

$dateCondition = "";

if ($range === 'today') {

    $dateCondition = "AND DATE(created_at) = CURDATE()";

} elseif ($range === 'week') {

    $dateCondition = "AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";

} elseif ($range === 'month') {

    $dateCondition = "AND MONTH(created_at) = MONTH(CURDATE()) 
                      AND YEAR(created_at) = YEAR(CURDATE())";

} elseif ($range === 'year') {

    $dateCondition = "AND YEAR(created_at) = YEAR(CURDATE())";

} elseif ($range === 'custom' && !empty($from) && !empty($to)) {

    $from = date('Y-m-d', strtotime($from));
    $to = date('Y-m-d', strtotime($to));

    $dateCondition = "AND DATE(created_at) BETWEEN '$from' AND '$to'";

} else {

    $dateCondition = "";
}

// Get tenant ID FIRST
$tenant_id = $_SESSION['tenant_id'];

$stmt = $conn->prepare("
    SELECT 
        SUM(
            CASE 
                WHEN type = 'cash_in' THEN amount
                WHEN type = 'cash_out' THEN -amount
            END
        ) as running_balance
    FROM transactions
    WHERE tenant_id = ?
");

$stmt->execute([$tenant_id]);
$running_balance = $stmt->fetch()['running_balance'] ?? 0;

//
// 🏢 BUSINESS NAME
//
$stmt = $conn->prepare("
    SELECT business_name 
    FROM tenants 
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$tenant_id]);

$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

$business_name = $tenant['business_name'] ?? 'My Business';

//
// 💰 TOTAL CASH IN
//
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) as total_in 
    FROM transactions 
    WHERE tenant_id = ? 
    AND type = 'cash_in'
    $dateCondition
");

$stmt->execute([$tenant_id]);
$total_in = $stmt->fetch()['total_in'];

//
// 💸 TOTAL CASH OUT
//
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) as total_out 
    FROM transactions 
    WHERE tenant_id = ? 
    AND type = 'cash_out'
    $dateCondition
");

$stmt->execute([$tenant_id]);
$total_out = $stmt->fetch()['total_out'];

//
// 💵 TOTAL FEES
//
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(fee),0) as total_fee 
    FROM transactions 
    WHERE tenant_id = ?
    $dateCondition
");

$stmt->execute([$tenant_id]);
$total_fee = $stmt->fetch()['total_fee'];

//
// 📊 BALANCE
//
$balance = $total_in - $total_out;

//
// 🧾 RECENT TRANSACTIONS
//
$stmt = $conn->prepare("
    SELECT * FROM transactions 
    WHERE tenant_id = ?
    $dateCondition
    ORDER BY created_at DESC 
    LIMIT 5
");

$stmt->execute([$tenant_id]);
$recent = $stmt->fetchAll();

//
// 📈 CHART DATA (Cash In / Cash Out per day)
//
$stmt = $conn->prepare("
    SELECT 
        DATE(created_at) as day,
        SUM(CASE WHEN type='cash_in' THEN amount ELSE 0 END) as cash_in,
        SUM(CASE WHEN type='cash_out' THEN amount ELSE 0 END) as cash_out
    FROM transactions
    WHERE tenant_id = ?
    $dateCondition
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
");

$stmt->execute([$tenant_id]);
$chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Dashboard</h2>

<!-- BUSINESS NAME -->
<div class="card" style="margin-bottom:15px;">
    <h3 style="margin:0;">
        <?= htmlspecialchars($business_name) ?>
    </h3>
    <small style="color:#6b7280;">Business Overview</small>
</div>

<form method="GET" style="margin-bottom:15px; display:flex; gap:10px; align-items:end; flex-wrap:wrap;">

    <div>
        <label>Quick Filter</label><br>
        <select name="range" onchange="this.form.submit()">
            <option value="today" <?= ($_GET['range'] ?? '') == 'today' ? 'selected' : '' ?>>Today</option>
            <option value="week" <?= ($_GET['range'] ?? '') == 'week' ? 'selected' : '' ?>>This Week</option>
            <option value="month" <?= ($_GET['range'] ?? '') == 'month' ? 'selected' : '' ?>>This Month</option>
            <option value="year" <?= ($_GET['range'] ?? '') == 'year' ? 'selected' : '' ?>>This Year</option>
            <option value="all" <?= ($_GET['range'] ?? 'all') == 'all' ? 'selected' : '' ?>>All Time</option>
            <option value="custom" <?= ($_GET['range'] ?? '') == 'custom' ? 'selected' : '' ?>>
                Custom
            </option>
        </select>
    </div>

    <div>
        <label>From</label><br>
        <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>">
    </div>

    <div>
        <label>To</label><br>
        <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>">
    </div>

    <div>
        <button type="submit">Apply</button>
    </div>

</form>

<!-- STATS -->
<div class="flex" style="margin-bottom:20px;">

    <div class="card">
        <h3 style="color:#111827;">Available Balance</h3>
        <p>
            ₱<?= number_format($running_balance, 2) ?>
        </p>
    </div>

    <div class="card">
        <h3 style="color:#16a34a;">Cash In</h3>
        <p>₱<?= number_format($total_in,2) ?></p>
    </div>

    <div class="card">
        <h3 style="color:#dc2626;">Cash Out</h3>
        <p>₱<?= number_format($total_out,2) ?></p>
    </div>

    <div class="card">
        <h3 style="color:#2563eb;">Balance</h3>
        <p>₱<?= number_format($balance,2) ?></p>
    </div>

    <div class="card">
        <h3 style="color:#f59e0b;">Fees (Profit)</h3>
        <p>₱<?= number_format($total_fee,2) ?></p>
    </div>

</div>

<div class="card" style="margin-top:20px; height:300px;">
    <h3>Cash Flow Overview</h3>
    <canvas id="cashFlowChart"></canvas>
</div>

<!-- RECENT TRANSACTIONS -->
<div class="card">

    <h3>Recent Transactions</h3>

    <table>

        <tr>
            <th>Type</th>
            <th>Amount</th>
            <th>Fee</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Date</th>
        </tr>

        <?php if (count($recent) == 0): ?>
        <tr>
            <td colspan="6" style="text-align:center; color:#6b7280; padding:15px;">
                No transactions found.
            </td>
        </tr>
        <?php endif; ?>

        <?php foreach ($recent as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['type']) ?></td>
            <td>₱<?= number_format($t['amount'],2) ?></td>
            <td>₱<?= number_format($t['fee'],2) ?></td>
            <td><?= htmlspecialchars($t['customer_name']) ?></td>
            <td><?= htmlspecialchars($t['status']) ?></td>
            <td><?= $t['created_at'] ?></td>
        </tr>
        <?php endforeach; ?>

    </table>

</div>

<?php
$labels = [];
$cashInData = [];
$cashOutData = [];

foreach ($chartData as $row) {
    $labels[] = $row['day'];
    $cashInData[] = $row['cash_in'];
    $cashOutData[] = $row['cash_out'];
}

if (empty($labels)) {
    $labels = ['No Data'];
    $cashInData = [0];
    $cashOutData = [0];
}
?>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const ctx = document.getElementById('cashFlowChart');

    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'Cash In',
                    data: <?= json_encode($cashInData) ?>,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22,163,74,0.1)',
                    tension: 0.3
                },
                {
                    label: 'Cash Out',
                    data: <?= json_encode($cashOutData) ?>,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220,38,38,0.1)',
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

});
</script>

<?php include "../includes/footer.php"; ?>