<?php
$host = 'localhost';
$db   = 'uit_exam_system';
$user = 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$charset = 'utf8mb4';

// --- ADD THIS LINE TO FIX THE "LOCKED" BUTTON ISSUE ---
date_default_timezone_set('Asia/Yangon');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // This ensures MySQL also uses the correct timezone for queries like NOW()
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+06:30'"
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
