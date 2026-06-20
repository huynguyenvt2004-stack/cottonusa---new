<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "✅ PHP đang chạy!";
echo "<br>Kết nối database...";

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa_db';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo "<br>❌ Lỗi kết nối: " . $conn->connect_error;
} else {
    echo "<br>✅ Kết nối database thành công!";
    
    // Kiểm tra bảng products
    $result = $conn->query("SHOW TABLES LIKE 'products'");
    if ($result->num_rows > 0) {
        echo "<br>✅ Bảng products tồn tại!";
    } else {
        echo "<br>❌ Bảng products KHÔNG tồn tại!";
    }
    
    $conn->close();
}
?>