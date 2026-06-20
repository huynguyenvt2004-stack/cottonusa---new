<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($keyword)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập từ khóa']);
    exit;
}

// Kết nối database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Kết nối database thất bại']);
    exit;
}

// Tìm kiếm sản phẩm theo mã hoặc tên
$keyword = $conn->real_escape_string($keyword);
$products = [];

$result = $conn->query("
    SELECT 
        p.id, 
        p.name, 
        p.category, 
        p.price, 
        p.main_image,
        GROUP_CONCAT(DISTINCT ps.size_name ORDER BY ps.size_name SEPARATOR ',') as sizes,
        GROUP_CONCAT(DISTINCT ps.color_name ORDER BY ps.color_name SEPARATOR ',') as colors,
        COALESCE(SUM(ps.stock), 0) as total_stock,
        COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.product_code = p.id), 0) as sold
    FROM products p
    LEFT JOIN product_stock ps ON p.id = ps.product_id
    WHERE p.id LIKE '%$keyword%' OR p.name LIKE '%$keyword%'
    GROUP BY p.id
    ORDER BY p.created_at DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Tính thống kê cho kết quả tìm kiếm
$total_products = count($products);
$total_stock = 0;
$total_sold = 0;
$low_stock = 0;

foreach ($products as $p) {
    $total_stock += (int)$p['total_stock'];
    $total_sold += (int)$p['sold'];
    if ((int)$p['total_stock'] > 0 && (int)$p['total_stock'] <= 5) {
        $low_stock++;
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'products' => $products,
    'stats' => [
        'total_products' => $total_products,
        'total_stock' => $total_stock,
        'total_sold' => $total_sold,
        'low_stock' => $low_stock
    ]
]);
exit;
?>