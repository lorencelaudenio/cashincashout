<?php
require_once "../includes/config.php";

// If already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: /app/dashboard.php");
    exit;
}



$error = "";

if ($_POST) {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Get user
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        if (password_verify($password, $user['password'])) {

            // Set SaaS session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['tenant_id'] = $user['tenant_id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];

            header("Location: /app/dashboard.php");
            exit;

        } else {
            $error = "Incorrect password.";
        }

    } else {
        $error = "User not found.";
    }
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - CashTrack</title>
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
            width: 300px;
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
            cursor: pointer;
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

    <h2>Login</h2>

    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>

        <button type="submit">Login</button>
    </form>

    <p><a href="/auth/register.php">Create account</a></p>

</div>

</body>
</html>