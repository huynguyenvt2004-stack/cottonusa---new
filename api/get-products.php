<?php
require_once '../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Lấy danh sách sản phẩm từ bảng product_stock
    $sql = "
        SELECT 
            p.id, 
            p.name, 
            p.category, 
            p.price, 
            p.main_image,
            GROUP_CONCAT(DISTINCT ps.size_name ORDER BY ps.size_name SEPARATOR ',') as sizes,
            GROUP_CONCAT(DISTINCT ps.color_name ORDER BY ps.color_name SEPARATOR ',') as colors,
            COALESCE(SUM(ps.stock), 0) as total_stock
        FROM products p
        LEFT JOIN product_stock ps ON p.id = ps.product_id
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}
exit;
?>