<?php
require_once "../includes/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];

/* =========================
   FILTER
========================= */
$range = $_GET['range'] ?? 'all';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$dateCondition = "";
$dateParams = [];

/* =========================
   BUILD FILTER
========================= */
if ($range === 'today') {

    $dateCondition = "AND DATE(t.created_at) = CURDATE()";

} elseif ($range === 'week') {

    $dateCondition = "AND YEARWEEK(t.created_at, 1) = YEARWEEK(CURDATE(), 1)";

} elseif ($range === 'month') {

    $dateCondition = "AND MONTH(t.created_at) = MONTH(CURDATE())
                      AND YEAR(t.created_at) = YEAR(CURDATE())";

} elseif ($range === 'year') {

    $dateCondition = "AND YEAR(t.created_at) = YEAR(CURDATE())";

} elseif ($range === 'custom' && !empty($from) && !empty($to)) {

    $from = date('Y-m-d', strtotime($from));
    $to = date('Y-m-d', strtotime($to));

    $dateCondition = "AND DATE(t.created_at) BETWEEN ? AND ?";
    $dateParams = [$from, $to];
}

/* =========================
   PARAMS
========================= */
$params = [$tenant_id];
if (!empty($dateParams)) {
    $params = array_merge($params, $dateParams);
}

/* =========================
   BUSINESS NAME
========================= */
$stmt = $conn->prepare("SELECT business_name FROM tenants WHERE id = ?");
$stmt->execute([$tenant_id]);
$business_name = $stmt->fetchColumn() ?: 'My Business';

/* =========================
   WALLET BALANCE
========================= */
$stmt = $conn->prepare("
SELECT COALESCE(SUM(
    CASE 
        WHEN type = 'cash_out' THEN amount   -- money coming in
        WHEN type = 'replenish' THEN amount  -- capital injection
        WHEN type = 'cash_in' THEN -amount   -- money going out
        ELSE 0
    END
),0) as wallet_balance
    FROM transactions t
    WHERE t.tenant_id = ?
    $dateCondition
");

$stmt->execute($params);
$running_balance = $stmt->fetchColumn();

/* =========================
   CASH IN
========================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM transactions t
    WHERE t.tenant_id = ?
    AND type = 'cash_in'
    $dateCondition
");
$stmt->execute($params);
$total_in = $stmt->fetchColumn();

/* =========================
   REPLENISH
========================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM transactions t
    WHERE t.tenant_id = ?
    AND type = 'replenish'
    $dateCondition
");
$stmt->execute($params);
$total_replenish = $stmt->fetchColumn();

/* =========================
   CASH OUT
========================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM transactions t
    WHERE t.tenant_id = ?
    AND type = 'cash_out'
    $dateCondition
");
$stmt->execute($params);
$total_out = $stmt->fetchColumn();

/* =========================
   FEES
========================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(fee),0)
    FROM transactions t
    WHERE t.tenant_id = ?
    $dateCondition
");
$stmt->execute($params);
$total_fee = $stmt->fetchColumn();

/* =========================
   RECENT TRANSACTIONS
========================= */
$stmt = $conn->prepare("
    SELECT t.*, c.name AS customer_name
    FROM transactions t
    LEFT JOIN customers c ON c.id = t.customer_id
    WHERE t.tenant_id = ?
    $dateCondition
    ORDER BY t.created_at DESC
    LIMIT 5
");
$stmt->execute($params);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   CHART DATA (FILTERED)
========================= */
$stmt = $conn->prepare("
    SELECT 
        DATE(t.created_at) as day,
        SUM(CASE WHEN t.type='cash_in' THEN t.amount ELSE 0 END) as cash_in,
        SUM(CASE WHEN t.type='cash_out' THEN t.amount ELSE 0 END) as cash_out,
        SUM(CASE WHEN t.type='replenish' THEN t.amount ELSE 0 END) as replenish
    FROM transactions t
    WHERE t.tenant_id = ?
    $dateCondition
    GROUP BY DATE(t.created_at)
    ORDER BY DATE(t.created_at) ASC
");
$stmt->execute($params);
$chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   PIE CHART (NOW FILTERED)
========================= */
$stmt = $conn->prepare("
    SELECT payment_status, COUNT(*) as total
    FROM transactions t
    WHERE t.tenant_id = ?
    $dateCondition
    GROUP BY payment_status
");
$stmt->execute($params);
$pieData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paid = 0;
$unpaid = 0;

foreach ($pieData as $row) {
    if ($row['payment_status'] === 'paid') $paid = (int)$row['total'];
    if ($row['payment_status'] === 'unpaid') $unpaid = (int)$row['total'];
}

/* =========================
   CHART PREP
========================= */
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

include "../includes/header.php";
?>

<h2>Dashboard</h2>

<!-- FILTER -->
<form method="GET" style="
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:nowrap;
    overflow-x:auto;
    padding:10px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:10px;
">

    <select name="range" onchange="this.form.submit()" style="padding:6px;">
        <option value="today" <?= $range=='today'?'selected':'' ?>>Today</option>
        <option value="week" <?= $range=='week'?'selected':'' ?>>Week</option>
        <option value="month" <?= $range=='month'?'selected':'' ?>>Month</option>
        <option value="year" <?= $range=='year'?'selected':'' ?>>Year</option>
        <option value="all" <?= $range=='all'?'selected':'' ?>>All</option>
        <option value="custom" <?= $range=='custom'?'selected':'' ?>>Custom</option>
    </select>

    <input type="date" name="from"
        value="<?= htmlspecialchars($from) ?>"
        style="padding:6px;">

    <span style="color:#6b7280;">to</span>

    <input type="date" name="to"
        value="<?= htmlspecialchars($to) ?>"
        style="padding:6px;">

    <button type="submit" style="
        padding:6px 12px;
        background:#2563eb;
        color:#fff;
        border:none;
        border-radius:6px;
        cursor:pointer;
        white-space:nowrap;
    ">
        Apply
    </button>

</form>

<!-- STATS -->
<div style="display:flex; gap:15px; flex-wrap:wrap;">

    <div class="card">Wallet<br>₱<?= number_format($running_balance,2) ?></div>
    <div class="card">Cash In<br>₱<?= number_format($total_in,2) ?></div>
    <div class="card">Cash Out<br>₱<?= number_format($total_out,2) ?></div>
    <div class="card">Fees<br>₱<?= number_format($total_fee,2) ?></div>
    <div class="card">Replenish<br>₱<?= number_format($total_replenish,2) ?></div>

</div>

<!-- CHARTS -->
<div style="
    display:flex;
    gap:15px;
    flex-wrap:wrap;
    margin-top:20px;
">

    <!-- CHART CARD -->
    <div style="
        flex:1 1 500px;
        min-width:300px;
        background:#fff;
        border:1px solid #e5e7eb;
        border-radius:12px;
        padding:15px;
        height:350px;
        box-sizing:border-box;
    ">
        <h3 style="margin-bottom:10px;">Cash Flow Overview</h3>
        <div style="position:relative; height:280px;">
            <canvas id="cashFlowChart"></canvas>
        </div>
    </div>

    <!-- PIE CARD -->
    <div style="
        flex:1 1 300px;
        min-width:280px;
        background:#fff;
        border:1px solid #e5e7eb;
        border-radius:12px;
        padding:15px;
        height:350px;
        box-sizing:border-box;
    ">
        <h3 style="margin-bottom:10px;">Payment Status</h3>
        <div style="position:relative; height:280px;">
            <canvas id="paymentPieChart"></canvas>
        </div>
    </div>

</div>

<!-- RECENT -->
<h3>Recent Transactions</h3>
<table border="1" width="100%">
<tr>
    <th>Type</th>
    <th>Amount</th>
    <th>Fee</th>
    <th>Customer</th>
    <th>Status</th>
    <th>Date</th>
</tr>

<?php foreach ($recent as $t): ?>
<tr>
    <td><?= $t['type'] ?></td>
    <td><?= $t['amount'] ?></td>
    <td><?= $t['fee'] ?></td>
    <td><?= $t['customer_name'] ?></td>
    <td><?= $t['status'] ?></td>
    <td><?= $t['created_at'] ?></td>
</tr>
<?php endforeach; ?>

</table>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
new Chart(document.getElementById('cashFlowChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
            { label:'Cash In', data: <?= json_encode($cashInData) ?>, borderColor:'green' },
            { label:'Cash Out', data: <?= json_encode($cashOutData) ?>, borderColor:'red' }
        ]
    }
});

new Chart(document.getElementById('paymentPieChart'), {
    type: 'pie',
    data: {
        labels: ['Paid','Unpaid'],
        datasets: [{
            data: [<?= $paid ?>, <?= $unpaid ?>],
            backgroundColor: ['green','red']
        }]
    }
});
</script>

<?php include "../includes/footer.php"; ?>