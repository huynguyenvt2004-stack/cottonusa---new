<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header('Location: login.php');
    exit;
}

$fullname = $_SESSION['admin_fullname'] ?? 'Admin';
$current_page = basename($_SERVER['PHP_SELF']);

// ===== KẾT NỐI DATABASE =====
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy ID đơn hàng
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    header('Location: orders.php');
    exit;
}

// Lấy thông tin đơn hàng
$order = null;
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $order = $result->fetch_assoc();
} else {
    header('Location: orders.php');
    exit;
}
$stmt->close();

// Lấy chi tiết đơn hàng
$items = [];
$stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

// Tính tổng tiền
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping_fee = 30000;
$total = $subtotal + $shipping_fee;

// Cập nhật trạng thái
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $valid_status = ['pending', 'confirmed', 'shipping', 'completed', 'cancelled'];
    if (in_array($new_status, $valid_status)) {
        $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        $stmt->execute();
        $stmt->close();
        header('Location: order-detail.php?id=' . $order_id . '&success=1');
        exit;
    }
}

$conn->close();

// Hàm định dạng ngày
function ngayVietNam($date) {
    return date('d/m/Y H:i', strtotime($date));
}

$status_badge = [
    'pending' => 'Chờ Xử Lý',
    'confirmed' => 'Đã Xác Nhận',
    'shipping' => 'Đang Giao',
    'completed' => 'Hoàn Thành',
    'cancelled' => 'Đã Hủy'
];

$status_colors = [
    'pending' => '#f59e0b',
    'confirmed' => '#3b82f6',
    'shipping' => '#8b5cf6',
    'completed' => '#22c55e',
    'cancelled' => '#ef4444'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng - CottonUSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }

        /* ===== SIDEBAR MÀU TRẮNG ===== */
        .sidebar {
            width: 250px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 2px 0 12px rgba(0,0,0,0.08);
        }

        .sidebar-brand {
            padding: 24px 0 20px 0;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
        }

        .brand-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
        }

        .brand-logo {
            height: 70px;
            width: auto;
            display: block;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .brand-logo:hover {
            transform: scale(1.05);
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px 0;
        }

        .nav-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #aaa;
            padding: 12px 24px 8px 24px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 11px 24px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .sidebar-nav a:hover {
            background: #f5f5f5;
            color: #1a1a2e;
        }

        .sidebar-nav a.active {
            background: rgba(227,6,19,0.08);
            color: #e30613;
            border-left-color: #e30613;
            font-weight: 600;
        }

        .sidebar-nav a i {
            width: 20px;
            text-align: center;
            font-size: 15px;
        }

        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid #f0f0f0;
            margin-top: auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e30613;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            color: #ffffff;
            flex-shrink: 0;
        }

        .name {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
        }

        .role {
            font-size: 12px;
            color: #888;
        }

        .sidebar-footer a {
            color: #888;
            text-decoration: none;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }

        .sidebar-footer a:hover {
            color: #e30613;
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 24px 32px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header h1 {
            font-size: 24px;
            color: #1a1a2e;
        }
        .page-header h1 span { color: #e30613; }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary { background: #e30613; color: #fff; }
        .btn-primary:hover { background: #c70510; }
        .btn-secondary { background: #e8e8e8; color: #333; }
        .btn-secondary:hover { background: #ddd; }
        .btn-success { background: #22c55e; color: #fff; }
        .btn-success:hover { background: #16a34a; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning { background: #f59e0b; color: #fff; }
        .btn-warning:hover { background: #d97706; }
        .btn-info { background: #3b82f6; color: #fff; }
        .btn-info:hover { background: #2563eb; }
        .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 8px; }

        .order-card {
            background: #fff;
            border-radius: 14px;
            padding: 24px 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }

        .order-card .row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .order-card .col {
            flex: 1;
            min-width: 200px;
        }

        .order-card .col .label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .order-card .col .value {
            font-size: 15px;
            font-weight: 600;
            color: #1a1a2e;
        }

        .order-card .col .value.status {
            color: <?php echo $status_colors[$order['status']] ?? '#666'; ?>;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .detail-table thead th {
            text-align: left;
            padding: 12px 12px;
            border-bottom: 2px solid #f0f0f0;
            color: #888;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }

        .detail-table tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid #f5f5f5;
            vertical-align: middle;
        }

        .detail-table tbody tr:hover { background: #fafafa; }

        .detail-table .product-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .detail-table .product-cell img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
            background: #fafafa;
        }

        .detail-table .product-cell .info .name {
            font-weight: 600;
            color: #1a1a2e;
        }

        .detail-table .product-cell .info .meta {
            font-size: 12px;
            color: #888;
            margin-top: 2px;
        }

        .summary-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 16px 20px;
            margin-top: 16px;
        }

        .summary-box .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
        }

        .summary-box .row.total {
            font-weight: 700;
            font-size: 16px;
            border-top: 2px solid #e0e0e0;
            padding-top: 12px;
            margin-top: 6px;
            color: #e30613;
        }

        .summary-box .row .label { color: #666; }
        .summary-box .row .value { color: #1a1a2e; }
        .summary-box .row.total .value { color: #e30613; }

        .status-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .status-actions select {
            padding: 8px 14px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            background: #fff;
        }

        .status-actions select:focus { border-color: #e30613; }

        .back-link {
            color: #888;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
        }
        .back-link:hover { color: #e30613; }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        /* ===== RESPONSIVE ===== */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .order-card .col { flex: 1 1 100%; }
            .detail-table { font-size: 12px; }
            .menu-toggle { display: block; }
        }
    </style>
</head>
<body>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <a href="home.php" class="brand-link">
                <img src="../images/logo.avif" alt="CottonUSA" class="brand-logo">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="home.php">
                <i class="fas fa-store"></i> Trang chính
            </a>
            <div class="nav-label">Tổng quan</div>
            <a href="dashboard.php">
                <i class="fas fa-chart-pie"></i> Thống kê
            </a>
            <a href="products.php">
                <i class="fas fa-tshirt"></i> Sản phẩm
            </a>
            <a href="orders.php" class="active">
                <i class="fas fa-shopping-cart"></i> Đơn hàng
            </a>
            <div class="nav-label">Nội dung</div>
            <a href="statistics.php">
                <i class="fas fa-chart-line"></i> Thống kê doanh thu
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($fullname, 0, 1)); ?></div>
                <div>
                    <div class="name"><?php echo htmlspecialchars($fullname); ?></div>
                    <div class="role">Administrator</div>
                </div>
            </div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <div class="page-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <h1>📋 Chi tiết <span>Đơn hàng</span></h1>
            </div>
            <div>
                <a href="orders.php" class="back-link"><i class="fas fa-arrow-left"></i> Quay lại danh sách</a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ Cập nhật trạng thái đơn hàng thành công!</div>
        <?php endif; ?>

        <!-- Thông tin đơn hàng -->
        <div class="order-card">
            <div class="row">
                <div class="col">
                    <div class="label">Mã đơn hàng</div>
                    <div class="value"><?php echo htmlspecialchars($order['order_code']); ?></div>
                </div>
                <div class="col">
                    <div class="label">Trạng thái</div>
                    <div class="value status"><?php echo $status_badge[$order['status']] ?? $order['status']; ?></div>
                </div>
                <div class="col">
                    <div class="label">Ngày đặt</div>
                    <div class="value"><?php echo ngayVietNam($order['created_at']); ?></div>
                </div>
                <div class="col">
                    <div class="label">Phương thức thanh toán</div>
                    <div class="value"><?php echo $order['payment_method'] === 'cod' ? 'COD (Thanh toán khi nhận hàng)' : 'Chuyển khoản ngân hàng'; ?></div>
                </div>
            </div>
        </div>

        <!-- Thông tin khách hàng -->
        <div class="order-card">
            <h3 style="margin-bottom:16px;font-size:16px;color:#1a1a2e;">
                <i class="fas fa-user" style="color:#e30613;"></i> Thông tin khách hàng
            </h3>
            <div class="row">
                <div class="col">
                    <div class="label">Họ tên</div>
                    <div class="value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                </div>
                <div class="col">
                    <div class="label">Số điện thoại</div>
                    <div class="value"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                </div>
                <div class="col" style="flex:2;">
                    <div class="label">Địa chỉ</div>
                    <div class="value"><?php echo htmlspecialchars($order['customer_address']); ?></div>
                </div>
            </div>
        </div>

        <!-- Sản phẩm đã đặt -->
        <div class="order-card">
            <h3 style="margin-bottom:16px;font-size:16px;color:#1a1a2e;">
                <i class="fas fa-box" style="color:#e30613;"></i> Sản phẩm đã đặt
            </h3>
            
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Đơn giá</th>
                        <th>Số lượng</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="product-cell">
                                <div class="info">
                                    <div class="name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="meta">
                                        <?php if ($item['size']): ?>Size: <?php echo htmlspecialchars($item['size']); ?> | <?php endif; ?>
                                        <?php if ($item['color']): ?>Màu: <?php echo htmlspecialchars($item['color']); ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo number_format($item['price']); ?>đ</td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo number_format($item['price'] * $item['quantity']); ?>đ</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Tổng cộng -->
            <div class="summary-box">
                <div class="row">
                    <span class="label">Tạm tính</span>
                    <span class="value"><?php echo number_format($subtotal); ?>đ</span>
                </div>
                <div class="row">
                    <span class="label">Phí vận chuyển</span>
                    <span class="value"><?php echo number_format($shipping_fee); ?>đ</span>
                </div>
                <div class="row total">
                    <span class="label">Tổng cộng</span>
                    <span class="value"><?php echo number_format($total); ?>đ</span>
                </div>
            </div>
        </div>

        <!-- Cập nhật trạng thái -->
        <div class="order-card">
            <h3 style="margin-bottom:12px;font-size:16px;color:#1a1a2e;">
                <i class="fas fa-edit" style="color:#e30613;"></i> Cập nhật trạng thái
            </h3>
            <form method="POST" class="status-actions">
                <select name="status">
                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Chờ xử lý</option>
                    <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Đã xác nhận</option>
                    <option value="shipping" <?php echo $order['status'] == 'shipping' ? 'selected' : ''; ?>>Đang giao</option>
                    <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                </select>
                <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                    <i class="fas fa-save"></i> Cập nhật
                </button>
            </form>
        </div>

        <!-- Nút in đơn hàng -->
        <div style="display:flex;gap:12px;margin-top:8px;">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> In đơn hàng
            </button>
            <a href="orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Quay lại
            </a>
        </div>
    </main>

    <script>
        // Toggle sidebar mobile
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
    </script>
</body>
</html>