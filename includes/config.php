<?php

$lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;

    [$key, $value] = explode('=', $line, 2);
    $_ENV[$key] = trim($value);
}
session_start();

$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$username = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];
$port = $_ENV['DB_PORT'] ?? 3306;

try {
    $conn = new PDO(
        "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8",
        $username,
        $password
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}


?>