<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Kết nối database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Kết nối database thất bại: ' . $conn->connect_error
    ]);
    exit;
}

// Lấy dữ liệu từ request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Không nhận được dữ liệu'
    ]);
    exit;
}

$customer_name = isset($data['name']) ? trim($data['name']) : '';
$customer_phone = isset($data['phone']) ? trim($data['phone']) : '';
$customer_address = isset($data['address']) ? trim($data['address']) : '';
$payment_method = isset($data['payment_method']) ? $data['payment_method'] : 'cod';
$total_amount = isset($data['total_amount']) ? (int)$data['total_amount'] : 0;
$items = isset($data['items']) ? $data['items'] : [];

if (empty($customer_name) || empty($customer_phone) || empty($customer_address)) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin khách hàng'
    ]);
    exit;
}

if (empty($items)) {
    echo json_encode([
        'success' => false,
        'message' => 'Giỏ hàng trống'
    ]);
    exit;
}

// Tạo mã đơn hàng
$order_code = 'CUS' . time() . rand(100, 999);

$conn->begin_transaction();

try {
    // Tạo bảng orders nếu chưa có
    $conn->query("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_code VARCHAR(50) UNIQUE NOT NULL,
            customer_name VARCHAR(100) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            customer_address VARCHAR(255) NOT NULL,
            total_amount DECIMAL(12,0) NOT NULL,
            payment_method ENUM('cod', 'bank') DEFAULT 'cod',
            status ENUM('pending', 'confirmed', 'shipping', 'completed', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Tạo bảng order_items nếu chưa có
    $conn->query("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_code VARCHAR(50) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(12,0) NOT NULL,
            size VARCHAR(10),
            color VARCHAR(50),
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )
    ");

    // Thêm đơn hàng
    $stmt = $conn->prepare("INSERT INTO orders (order_code, customer_name, customer_phone, customer_address, total_amount, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->bind_param("ssssds", $order_code, $customer_name, $customer_phone, $customer_address, $total_amount, $payment_method);
    
    if (!$stmt->execute()) {
        throw new Exception('Lỗi khi thêm đơn hàng: ' . $stmt->error);
    }
    
    $order_id = $stmt->insert_id;
    $stmt->close();

    // Thêm chi tiết đơn hàng
    foreach ($items as $item) {
        $product_code = isset($item['code']) ? $item['code'] : '';
        $product_name = isset($item['name']) ? $item['name'] : '';
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $price = isset($item['price']) ? (int)$item['price'] : 0;
        $size = isset($item['size']) ? $item['size'] : '';
        $color = isset($item['color']) ? $item['color'] : '';

        $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_code, product_name, quantity, price, size, color) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param("issidss", $order_id, $product_code, $product_name, $quantity, $price, $size, $color);
        
        if (!$stmt2->execute()) {
            throw new Exception('Lỗi khi thêm chi tiết đơn hàng: ' . $stmt2->error);
        }
        $stmt2->close();
    }

    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Đặt hàng thành công!',
        'order_code' => $order_code,
        'order_id' => $order_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}

$conn->close();
?>