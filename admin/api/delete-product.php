<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$id = isset($_POST['id']) ? trim($_POST['id']) : '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã sản phẩm']);
    exit;
}

try {
    // Xóa sản phẩm
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Xóa thành công']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Xóa thất bại: ' . $e->getMessage()]);
}
exit;
?>