<?php
error_reporting(0);
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

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($keyword)) {
    echo json_encode(['success' => true, 'total' => 0, 'products' => []]);
    exit;
}

// Tìm kiếm theo tên hoặc mã sản phẩm - LẤY ĐẦY ĐỦ DỮ LIỆU
$sql = "SELECT 
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
        WHERE p.name LIKE ? OR p.id LIKE ?
        GROUP BY p.id
        ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$searchTerm = '%' . $keyword . '%';
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    // Chuyển đổi dữ liệu sang đúng kiểu
    $row['total_stock'] = (int)$row['total_stock'];
    $row['sold'] = (int)$row['sold'];
    $row['price'] = (int)$row['price'];
    $row['sizes'] = $row['sizes'] ?? '';
    $row['colors'] = $row['colors'] ?? '';
    $products[] = $row;
}

echo json_encode([
    'success' => true,
    'total' => count($products),
    'keyword' => $keyword,
    'products' => $products
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>