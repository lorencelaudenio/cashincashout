<?php
require_once "../includes/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

include "../includes/header.php";

$tenant_id = $_SESSION['tenant_id'];

//
// ➕ ADD CUSTOMER
//
if ($_POST && isset($_POST['add'])) {

    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    $stmt = $conn->prepare("
        INSERT INTO customers (tenant_id, name, phone, email)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([$tenant_id, $name, $phone, $email]);

    header("Location: customers.php");
    exit;
}

//
// ✏️ UPDATE CUSTOMER
//
if ($_POST && isset($_POST['update'])) {

    $id = $_POST['id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    $stmt = $conn->prepare("
        UPDATE customers
        SET name = ?, phone = ?, email = ?
        WHERE id = ? AND tenant_id = ?
    ");

    $stmt->execute([$name, $phone, $email, $id, $tenant_id]);

    header("Location: customers.php");
    exit;
}

//
// 🗑 DELETE CUSTOMER
//
if (isset($_GET['delete'])) {

    $id = $_GET['delete'];

    $stmt = $conn->prepare("
        DELETE FROM customers
        WHERE id = ? AND tenant_id = ?
    ");

    $stmt->execute([$id, $tenant_id]);

    header("Location: customers.php");
    exit;
}

//
// 📋 GET CUSTOMERS
//
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.name,
        c.phone,
        c.email,

        COALESCE(SUM(CASE WHEN t.type = 'cash_in' THEN t.amount ELSE 0 END), 0) AS total_cash_in,

        COALESCE(SUM(CASE WHEN t.type = 'cash_out' THEN t.amount ELSE 0 END), 0) AS total_cash_out

    FROM customers c

    LEFT JOIN transactions t 
        ON t.customer_id = c.id

    WHERE c.tenant_id = ?

    GROUP BY c.id, c.name, c.phone, c.email

    ORDER BY c.id DESC
");

$stmt->execute([$tenant_id]);
$customers = $stmt->fetchAll();

//
// ✏️ EDIT MODE
//
$edit = null;

if (isset($_GET['edit'])) {

    $stmt = $conn->prepare("
        SELECT * FROM customers
        WHERE id = ? AND tenant_id = ?
    ");

    $stmt->execute([$_GET['edit'], $tenant_id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<h2>Customers</h2>

<div class="card">

    <!-- ➕ ADD / ✏️ EDIT FORM -->
    <form method="POST">

        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">

        <label>Name</label>
        <input type="text" name="name"
               value="<?= $edit['name'] ?? '' ?>"
               required>

        <label>Phone</label>
        <input type="text" name="phone"
               value="<?= $edit['phone'] ?? '' ?>">

        <label>Email</label>
        <input type="email" name="email"
               value="<?= $edit['email'] ?? '' ?>">

        <button type="submit" name="<?= $edit ? 'update' : 'add' ?>">
            <?= $edit ? 'Update Customer' : 'Add Customer' ?>
        </button>

    </form>

</div>

<br>

<div class="card">

    <h3>Customer List</h3>

    <table>

        <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Cash In</th>
            <th>Cash Out</th>
            <th>Action</th>
        </tr>

        <?php if (count($customers) == 0): ?>
        <tr>
            <td colspan="4" style="text-align:center; color:#6b7280;">
                No customers yet.
            </td>
        </tr>
        <?php endif; ?>

        <?php foreach ($customers as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['name']) ?></td>
            <td><?= htmlspecialchars($c['phone']) ?></td>
            <td><?= htmlspecialchars($c['email']) ?></td>

            <td style="color:green;">
                ₱<?= number_format($c['total_cash_in'], 2) ?>
            </td>

            <td style="color:red;">
                ₱<?= number_format($c['total_cash_out'], 2) ?>
            </td>

            <td>
                <a href="?edit=<?= $c['id'] ?>">✏️ Edit</a>
                <a href="?delete=<?= $c['id'] ?>"
                onclick="return confirm('Delete customer?')"
                style="color:red; margin-left:10px;">
                🗑 Delete
                </a>
            </td>
        </tr>
        <?php endforeach; ?>

    </table>

</div>

<?php include "../includes/footer.php"; ?>