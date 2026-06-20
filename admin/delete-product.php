<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$id = isset($_POST['id']) ? trim($_POST['id']) : '';

if (empty($id)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Thiếu mã sản phẩm']);
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

// Xóa sản phẩm (các bảng liên quan sẽ tự động xóa nhờ ON DELETE CASCADE)
$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("s", $id);

if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Xóa thành công']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Xóa thất bại: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>