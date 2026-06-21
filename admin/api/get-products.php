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

// Kết nối database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Kết nối database thất bại']);
    exit;
}

// ===== PHÂN TRANG =====
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Đếm tổng sản phẩm
$countResult = $conn->query("SELECT COUNT(*) as total FROM products");
$totalCount = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $limit);

// Lấy danh sách sản phẩm từ product_stock
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
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// ===== TÍNH THỐNG KÊ =====
$total_products = $totalCount;
$total_stock = 0;
$total_sold = 0;
$low_stock = 0;

// Tổng tồn kho
$stockResult = $conn->query("SELECT COALESCE(SUM(stock), 0) as total_stock FROM product_stock");
if ($stockResult) {
    $total_stock = (int)$stockResult->fetch_assoc()['total_stock'];
}

// Tổng đã bán
$soldResult = $conn->query("SELECT COALESCE(SUM(quantity), 0) as total_sold FROM order_items");
if ($soldResult) {
    $total_sold = (int)$soldResult->fetch_assoc()['total_sold'];
}

// Sắp hết hàng
$lowStockResult = $conn->query("
    SELECT COUNT(DISTINCT product_id) as low_stock 
    FROM product_stock 
    WHERE stock > 0 AND stock <= 5
");
if ($lowStockResult) {
    $low_stock = (int)$lowStockResult->fetch_assoc()['low_stock'];
}

$conn->close();

echo json_encode([
    'success' => true,
    'products' => $products,
    'total_pages' => $totalPages,
    'current_page' => $page,
    'total_count' => $totalCount,
    'stats' => [
        'total_products' => $total_products,
        'total_stock' => $total_stock,
        'total_sold' => $total_sold,
        'low_stock' => $low_stock
    ]
]);
exit;
?>