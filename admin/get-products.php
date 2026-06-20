<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

// Kết nối database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Kết nối database thất bại']);
    exit;
}

// Lấy danh sách sản phẩm
$products = [];
$result = $conn->query("
    SELECT 
        p.id, 
        p.name, 
        p.category, 
        p.price, 
        p.main_image,
        GROUP_CONCAT(DISTINCT ps.size_name ORDER BY ps.size_name SEPARATOR ',') as sizes,
        GROUP_CONCAT(DISTINCT pc.color_name ORDER BY pc.color_name SEPARATOR ',') as colors,
        SUM(ps.stock) as total_stock,
        COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.product_code = p.id), 0) as sold
    FROM products p
    LEFT JOIN product_sizes ps ON p.id = ps.product_id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Tính thống kê
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

header('Content-Type: application/json');
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
?>