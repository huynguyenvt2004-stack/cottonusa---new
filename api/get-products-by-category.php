<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Kết nối thất bại']);
    exit;
}

// Nhận danh mục từ URL
$category = isset($_GET['category']) ? $_GET['category'] : '';

if (empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu danh mục']);
    exit;
}

// Truy vấn sản phẩm theo danh mục
$sql = "SELECT * FROM products WHERE category = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $category);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode([
    'success' => true,
    'category' => $category,
    'total' => count($products),
    'products' => $products
]);

$stmt->close();
$conn->close();
?>