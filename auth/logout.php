<?php
require_once "../includes/config.php";
session_start();

// Clear all session data
$_SESSION = [];

// Destroy session
session_destroy();

// Optional: delete session cookie (more secure)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Redirect to login
header("Location: /auth/login.php");
exit;
?>