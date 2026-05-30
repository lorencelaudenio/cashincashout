<?php
require_once "../includes/config.php";


if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];

//
// 🏢 GET BUSINESS NAME
//
$stmt = $conn->prepare("
    SELECT business_name 
    FROM tenants 
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

$business_name = $tenant['business_name'] ?? '';

//
// 💾 UPDATE BUSINESS NAME
//
if (isset($_POST['update_business'])) {

    $name = trim($_POST['business_name']);

    $stmt = $conn->prepare("
        UPDATE tenants 
        SET business_name = ?
        WHERE id = ?
    ");

    $stmt->execute([$name, $tenant_id]);

    header("Location: settings.php");
    exit;
}

//
// ➕ ADD FEE RULE
//
if (isset($_POST['add_rule'])) {

    $min = floatval($_POST['min_amount']);
    $max = floatval($_POST['max_amount']);
    $fee = floatval($_POST['fee']);

    if ($min >= 0 && $max > $min) {

        $stmt = $conn->prepare("
            INSERT INTO fee_rules (tenant_id, min_amount, max_amount, fee)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([$tenant_id, $min, $max, $fee]);
    }

    header("Location: settings.php");
    exit;
}

//
// 🗑 DELETE RULE
//
if (isset($_GET['delete'])) {

    $id = $_GET['delete'];

    $stmt = $conn->prepare("
        DELETE FROM fee_rules 
        WHERE id = ? AND tenant_id = ?
    ");

    $stmt->execute([$id, $tenant_id]);

    header("Location: settings.php");
    exit;
}

//
// 📋 GET RULES
//
$stmt = $conn->prepare("
    SELECT * FROM fee_rules 
    WHERE tenant_id = ?
    ORDER BY min_amount ASC
");
$stmt->execute([$tenant_id]);
$rules = $stmt->fetchAll();

$editRule = null;

if (!empty($_GET['edit'])) {

    $stmt = $conn->prepare("
        SELECT * FROM fee_rules 
        WHERE id = ? AND tenant_id = ?
    ");

    $stmt->execute([$_GET['edit'], $tenant_id]);
    $editRule = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (isset($_POST['update_rule'])) {

    $id = $_POST['id'];
    $min = floatval($_POST['min_amount']);
    $max = floatval($_POST['max_amount']);
    $fee = floatval($_POST['fee']);

    $stmt = $conn->prepare("
        UPDATE fee_rules 
        SET min_amount = ?, max_amount = ?, fee = ?
        WHERE id = ? AND tenant_id = ?
    ");

    $stmt->execute([$min, $max, $fee, $id, $tenant_id]);

    header("Location: settings.php");
    exit;
}

include "../includes/header.php";
?>

<h2>Settings</h2>

<!-- 🏢 BUSINESS INFO -->
<div class="card" style="margin-bottom:15px;">

    <h3>Business Information</h3>

    <form method="POST">

        <label>Store Name</label>
        <input type="text"
               name="business_name"
               value="<?= htmlspecialchars($business_name) ?>"
               placeholder="Enter business name"
               required>

        <button type="submit" name="update_business">
            💾 Update Store Name
        </button>

    </form>

</div>

<!-- 💰 FEE RULES -->
<div class="card" style="margin-bottom:15px;">

    <h3>Fee Rules</h3>

    <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">

        <input type="hidden" name="id" value="<?= $editRule['id'] ?? '' ?>">

        <div>
            <label>Min</label><br>
            <input type="number" step="0.01" name="min_amount"
                value="<?= $editRule['min_amount'] ?? '' ?>" required>
        </div>

        <div>
            <label>Max</label><br>
            <input type="number" step="0.01" name="max_amount"
                value="<?= $editRule['max_amount'] ?? '' ?>" required>
        </div>

        <div>
            <label>Fee</label><br>
            <input type="number" step="0.01" name="fee"
                value="<?= $editRule['fee'] ?? '' ?>" required>
        </div>

        <div>
            <?php if ($editRule): ?>
                <button type="submit" name="update_rule">💾 Update Rule</button>
                <a href="settings.php">Cancel</a>
            <?php else: ?>
                <button type="submit" name="add_rule">➕ Add Rule</button>
            <?php endif; ?>
        </div>

    </form>

</div>

<!-- 📊 RULE TABLE -->
<div class="card">

    <h3>Existing Rules</h3>

    <table>

        <tr>
            <th>Min</th>
            <th>Max</th>
            <th>Fee</th>
            <th>Action</th>
        </tr>

        <?php if (count($rules) == 0): ?>
        <tr>
            <td colspan="4" style="text-align:center; color:#6b7280; padding:15px;">
                No fee rules yet.
            </td>
        </tr>
        <?php endif; ?>

        <?php foreach ($rules as $r): ?>
        <tr>
            <td>₱<?= number_format($r['min_amount'],2) ?></td>
            <td>₱<?= number_format($r['max_amount'],2) ?></td>
            <td>₱<?= number_format($r['fee'],2) ?></td>
            <td>
                <a href="settings.php?edit=<?= $r['id'] ?>">✏️ Edit</a> |
                <a href="?delete=<?= $r['id'] ?>" onclick="return confirm('Delete this rule?')">🗑 Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>

    </table>

</div>
<?php include "../includes/footer.php"; ?>