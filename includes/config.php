<?php
session_start();

$host = "sql203.infinityfree.com";
$dbname = "if0_42048378_db_cashincashout";
$username = "if0_42048378";
$password = "bHkVEbBd9Nh";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
?>