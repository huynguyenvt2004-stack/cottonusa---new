<?php
// Tắt hiển thị lỗi để tránh ảnh hưởng JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db = 'cottonusa';

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception('Kết nối database thất bại: ' . $conn->connect_error);
    }

    $keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

    if (empty($keyword)) {
        echo json_encode(['success' => true, 'total' => 0, 'products' => []]);
        $conn->close();
        exit;
    }

    // Tìm kiếm sản phẩm
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
    if (!$stmt) {
        throw new Exception('Lỗi prepare SQL: ' . $conn->error);
    }
    
    $searchTerm = '%' . $keyword . '%';
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'price' => (int)$row['price'],
            'main_image' => $row['main_image'] ?? '',
            'sizes' => $row['sizes'] ?? '',
            'colors' => $row['colors'] ?? '',
            'total_stock' => (int)($row['total_stock'] ?? 0),
            'sold' => (int)($row['sold'] ?? 0)
        ];
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'total' => count($products),
        'keyword' => $keyword,
        'products' => $products
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Nếu có lỗi, trả về JSON lỗi
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>