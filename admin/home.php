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

// Kết nối database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// ===== THỐNG KÊ TỔNG QUAN =====

// Tổng đơn hàng
$result = $conn->query("SELECT COUNT(*) as total FROM orders");
$total_orders = $result->fetch_assoc()['total'] ?? 0;

// Tổng doanh thu (confirmed + completed)
$result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status IN ('confirmed', 'completed')");
$total_revenue = $result->fetch_assoc()['total'] ?? 0;

// Tổng sản phẩm
$result = $conn->query("SELECT COUNT(*) as total FROM products");
$total_products = $result->fetch_assoc()['total'] ?? 0;

// Đơn hàng chờ xử lý (pending)
$result = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$pending_orders = $result->fetch_assoc()['total'] ?? 0;

// ===== TOÀN BỘ ĐƠN HÀNG =====
$all_orders = [];
$result = $conn->query("
    SELECT order_code, customer_name, total_amount, payment_method, status, created_at 
    FROM orders 
    ORDER BY created_at DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $all_orders[] = $row;
    }
}

$conn->close();

// Hàm định dạng ngày
function ngayVietNam($date) {
    return date('d/m/Y', strtotime($date));
}

// Hàm trạng thái
function statusText($status) {
    $statuses = [
        'pending' => 'Chờ xử lý',
        'confirmed' => 'Đã xác nhận',
        'shipping' => 'Đang giao',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy'
    ];
    return $statuses[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chính - CottonUSA</title>
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
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header h1 {
            font-size: 24px;
            color: #1a1a2e;
        }
        .page-header h1 span { color: #e30613; }
        .page-header .date { font-size: 13px; color: #888; }

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
        .btn-sm { padding: 8px 16px; font-size: 13px; border-radius: 8px; }

        /* ===== STATS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #e30613;
        }

        .stat-card:nth-child(2) { border-left-color: #3b82f6; }
        .stat-card:nth-child(3) { border-left-color: #22c55e; }
        .stat-card:nth-child(4) { border-left-color: #f59e0b; }

        .stat-card .stat-label {
            font-size: 13px;
            color: #888;
            margin-bottom: 4px;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .stat-card .stat-value .currency {
            font-size: 16px;
            color: #888;
            margin-left: 2px;
        }

        /* ===== TABLE ===== */
        .table-wrapper {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .table-wrapper .table-title {
            font-size: 15px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 12px;
        }

        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .table-wrapper thead th {
            text-align: left;
            padding: 12px 12px;
            border-bottom: 2px solid #f0f0f0;
            color: #888;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }

        .table-wrapper tbody td {
            padding: 12px 12px;
            border-bottom: 1px solid #f5f5f5;
            color: #333;
            vertical-align: middle;
        }

        .table-wrapper tbody tr:hover { background: #fafafa; }

        .status-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-pending { background: #fff3e0; color: #e67e22; }
        .status-confirmed { background: #e3f2fd; color: #1976d2; }
        .status-shipping { background: #e8f5e9; color: #388e3c; }
        .status-completed { background: #dcfce7; color: #16a34a; }
        .status-cancelled { background: #fce4ec; color: #c62828; }

        /* ===== SCROLL ===== */
        .table-scroll {
            max-height: 500px;
            overflow-y: auto;
        }
        .table-scroll table thead {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 10;
        }
        .table-scroll table thead th {
            background: #f8f9fa;
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
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .table-wrapper { overflow-x: auto; }
            .menu-toggle { display: block; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
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
            <a href="home.php" class="active">
                <i class="fas fa-store"></i> Trang chính
            </a>
            <div class="nav-label">Tổng quan</div>
            <a href="dashboard.php">
                <i class="fas fa-chart-pie"></i> Thống kê
            </a>
            <a href="products.php">
                <i class="fas fa-tshirt"></i> Sản phẩm
            </a>
            <a href="orders.php">
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
                <h1>📊 Tổng quan</h1>
            </div>
            <div>
                <span class="date"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?></span>
                <a href="orders.php" class="btn btn-primary btn-sm" style="margin-left:12px;">
                    <i class="fas fa-arrow-right"></i> Xem tất cả đơn hàng
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">TỔNG ĐƠN HÀNG</div>
                <div class="stat-value"><?php echo $total_orders; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">DOANH THU</div>
                <div class="stat-value"><?php echo number_format($total_revenue); ?><span class="currency">đ</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">SẢN PHẨM</div>
                <div class="stat-value"><?php echo $total_products; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">CHỜ XỬ LÝ</div>
                <div class="stat-value"><?php echo $pending_orders; ?></div>
            </div>
        </div>

        <!-- All Orders -->
        <div class="table-wrapper">
            <div class="table-title">
                <i class="fas fa-list" style="color:#e30613;"></i> Tất cả đơn hàng (<?php echo count($all_orders); ?> đơn)
            </div>
            
            <?php if (empty($all_orders)): ?>
                <div style="text-align:center;padding:30px;color:#999;">
                    <i class="fas fa-inbox" style="font-size:40px;display:block;margin-bottom:12px;"></i>
                    Chưa có đơn hàng nào
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Tổng tiền</th>
                                <th>Thanh toán</th>
                                <th>Trạng thái</th>
                                <th>Ngày đặt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_orders as $order): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['order_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo number_format($order['total_amount']); ?>đ</td>
                                <td><?php echo $order['payment_method'] === 'cod' ? 'COD' : 'Chuyển khoản'; ?></td>
                                <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo statusText($order['status']); ?></span></td>
                                <td><?php echo ngayVietNam($order['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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