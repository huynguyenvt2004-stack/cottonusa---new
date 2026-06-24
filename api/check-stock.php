<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

error_reporting(0);

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Kết nối database thất bại']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Không nhận được dữ liệu']);
    exit;
}

$product_id = isset($data['product_id']) ? trim($data['product_id']) : '';
$size = isset($data['size']) ? trim($data['size']) : '';
$quantity = isset($data['quantity']) ? (int)$data['quantity'] : 0;

if (empty($product_id) || empty($size) || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin kiểm tra']);
    exit;
}

$sql = "SELECT COALESCE(SUM(stock), 0) as total_stock FROM product_stock WHERE product_id = ? AND size_name = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $product_id, $size);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$available_stock = (int)($row['total_stock'] ?? 0);

$stmt->close();
$conn->close();

$response = array();

if ($available_stock >= $quantity) {
    $response['success'] = true;
    $response['available'] = $available_stock;
    $response['message'] = 'Còn đủ hàng';
} else {
    $response['success'] = false;
    $response['available'] = $available_stock;
    $response['message'] = 'Không đủ hàng. Còn ' . $available_stock . ' sản phẩm';
}

echo json_encode($response);
exit;
?>