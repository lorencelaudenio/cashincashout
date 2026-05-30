<?php
session_start();

// If logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: /app/dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CashTrack - Cash In Cash Out Tracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial;
            background: #0f172a;
            color: white;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }

        .box {
            max-width: 500px;
            padding: 30px;
            background: #1e293b;
            border-radius: 12px;
        }

        h1 {
            margin-bottom: 10px;
        }

        p {
            color: #cbd5e1;
        }

        a {
            display: inline-block;
            margin: 10px;
            padding: 10px 20px;
            background: #22c55e;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }

        a.login {
            background: #3b82f6;
        }
    </style>
</head>

<body>

<div class="box">
    <h1>💰 CashTrack</h1>
    <p>Simple Cash In / Cash Out Tracker for Business Owners</p>

    <a class="login" href="/auth/login.php">Login</a>
    <a href="/auth/register.php">Create Account</a>
</div>

</body>
</html>