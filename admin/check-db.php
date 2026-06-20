<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

echo "<h2>Kiểm tra database</h2>";

// Kiểm tra bảng products
$result = $conn->query("SELECT COUNT(*) as total FROM products");
$row = $result->fetch_assoc();
echo "<p>📦 Tổng sản phẩm: <strong>" . $row['total'] . "</strong></p>";

// Lấy danh sách sản phẩm
$result = $conn->query("SELECT id, name, price FROM products LIMIT 5");
echo "<h3>Danh sách sản phẩm:</h3>";
echo "<ul>";
while ($row = $result->fetch_assoc()) {
    echo "<li>" . $row['id'] . " - " . $row['name'] . " - " . number_format($row['price']) . "đ</li>";
}
echo "</ul>";

$conn->close();
?>