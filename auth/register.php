<?php
require_once "../includes/config.php";

if (isset($_SESSION['user_id'])) {
    header("Location: /app/dashboard.php");
    exit;
}

$error = "";

if ($_POST) {

    $business_name = trim($_POST['business_name']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Basic validation
    if (!$business_name || !$fullname || !$email || !$password) {
        $error = "All fields are required.";
    } else {

        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = "Email already exists.";
        } else {

            // 1. Create tenant (business)
            $stmt = $conn->prepare("
                INSERT INTO tenants (business_name, plan)
                VALUES (?, 'free')
            ");
            $stmt->execute([$business_name]);

            $tenant_id = $conn->lastInsertId();

            // 2. Create owner user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users (tenant_id, fullname, email, password, role)
                VALUES (?, ?, ?, ?, 'owner')
            ");

            $stmt->execute([
                $tenant_id,
                $fullname,
                $email,
                $hashedPassword
            ]);

            $user_id = $conn->lastInsertId();

            // 3. Login session (auto-login after register)
            $_SESSION['user_id'] = $user_id;
            $_SESSION['tenant_id'] = $tenant_id;
            $_SESSION['fullname'] = $fullname;
            $_SESSION['role'] = 'owner';

            header("Location: /app/dashboard.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - CashTrack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial;
            background: #0f172a;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .box {
            background: #1e293b;
            padding: 30px;
            border-radius: 12px;
            width: 320px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border-radius: 6px;
            border: none;
        }

        button {
            width: 100%;
            padding: 10px;
            background: #22c55e;
            color: white;
            border: none;
            border-radius: 6px;
        }

        .error {
            color: #ef4444;
            margin-bottom: 10px;
        }

        a {
            color: #60a5fa;
            font-size: 12px;
        }
    </style>
</head>

<body>

<div class="box">

    <h2>Create Account</h2>

    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">

        <input type="text" name="business_name" placeholder="Business Name" required>
        <input type="text" name="fullname" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>

        <button type="submit">Register</button>

    </form>

    <p><a href="/auth/login.php">Already have an account? Login</a></p>

</div>

</body>
</html>