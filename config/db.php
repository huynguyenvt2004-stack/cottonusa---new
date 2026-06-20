<?php
// config/db.php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Kết nối database thất bại: " . $e->getMessage());
}

// Tạo biến $conn cho tương thích với code cũ
$conn = $pdo;
?>