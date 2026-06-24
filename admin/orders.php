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

// Lấy danh sách đơn hàng
$orders = [];
$result = $conn->query("SELECT * FROM orders ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đơn hàng - CottonUSA</title>
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
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #22c55e; color: #fff; }
        .btn-success:hover { background: #16a34a; }
        .btn-info { background: #3b82f6; color: #fff; }
        .btn-info:hover { background: #2563eb; }
        .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 8px; }
        .btn-xs { padding: 4px 10px; font-size: 11px; border-radius: 6px; }

        .content-card {
            background: #fff;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .table-toolbar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .table-toolbar .filter-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .table-toolbar .filter-group select,
        .table-toolbar .filter-group input {
            padding: 8px 14px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            background: #fff;
        }

        .table-toolbar .filter-group select:focus,
        .table-toolbar .filter-group input:focus {
            border-color: #e30613;
        }

        .table-toolbar .filter-group input {
            min-width: 220px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table thead th {
            text-align: left;
            padding: 12px 12px;
            border-bottom: 2px solid #f0f0f0;
            color: #888;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        table tbody td {
            padding: 12px 12px;
            border-bottom: 1px solid #f5f5f5;
            color: #333;
            vertical-align: middle;
        }

        table tbody tr:hover { background: #fafafa; }

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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            color: #ddd;
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
            .table-toolbar { flex-direction: column; }
            .table-toolbar .filter-group { flex-direction: column; }
            .table-toolbar .filter-group input { min-width: auto; }
            table { font-size: 12px; }
            table thead th, table tbody td { padding: 8px 6px; }
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
                <h1>📦 Quản lý <span>Đơn hàng</span></h1>
            </div>
            <div>
                <span style="font-size:13px;color:#888;"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?></span>
            </div>
        </div>

        <div class="content-card">
            <div class="table-toolbar">
                <div class="filter-group">
                    <select id="statusFilter" onchange="filterOrders()">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="pending">Chờ xử lý</option>
                        <option value="confirmed">Đã xác nhận</option>
                        <option value="shipping">Đang giao</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                    <input type="text" id="searchOrder" placeholder="Tìm kiếm mã đơn, tên khách..." oninput="filterOrders()">
                </div>
                <div>
                    <button class="btn btn-secondary btn-sm" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Làm mới</button>
                </div>
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Chưa có đơn hàng nào</p>
                    <span style="font-size:13px;color:#bbb;">Đơn hàng sẽ hiển thị ở đây khi khách hàng đặt mua</span>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>SĐT</th>
                            <th>Địa chỉ</th>
                            <th>Tổng tiền</th>
                            <th>PT thanh toán</th>
                            <th>Trạng thái</th>
                            <th>Ngày đặt</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="orderTableBody">
                        <?php foreach ($orders as $order): ?>
                        <tr class="order-row" data-status="<?php echo $order['status']; ?>">
                            <td><strong><?php echo htmlspecialchars($order['order_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_address']); ?></td>
                            <td><?php echo number_format($order['total_amount']); ?>đ</td>
                            <td><?php echo $order['payment_method'] === 'cod' ? 'COD' : 'Chuyển khoản'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ['pending'=>'Chờ Xử Lý','confirmed'=>'Đã Xác Nhận','shipping'=>'Đang Giao','completed'=>'Hoàn Thành','cancelled'=>'Đã Hủy'][$order['status']] ?? $order['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                            <td>
                                <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-info btn-xs"><i class="fas fa-eye"></i></a>
                                <?php if ($order['status'] === 'pending'): ?>
                                    <button onclick="updateStatus(<?php echo $order['id']; ?>, 'confirmed')" class="btn btn-success btn-xs"><i class="fas fa-check"></i></button>
                                    <button onclick="updateStatus(<?php echo $order['id']; ?>, 'cancelled')" class="btn btn-danger btn-xs"><i class="fas fa-times"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Toggle sidebar mobile
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });

        // Filter orders
        function filterOrders() {
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchOrder').value.toLowerCase();
            const rows = document.querySelectorAll('.order-row');
            
            rows.forEach(row => {
                const rowStatus = row.dataset.status;
                const text = row.textContent.toLowerCase();
                let show = true;
                
                if (status !== 'all' && rowStatus !== status) show = false;
                if (search && !text.includes(search)) show = false;
                
                row.style.display = show ? '' : 'none';
            });
        }

        // Update order status
        function updateStatus(orderId, status) {
            if (!confirm('Xác nhận cập nhật trạng thái đơn hàng?')) return;
            
            fetch('api/update-order-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'order_id=' + orderId + '&status=' + status
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Cập nhật thành công!');
                    location.reload();
                } else {
                    alert('❌ Lỗi: ' + data.message);
                }
            })
            .catch(err => {
                alert('❌ Lỗi kết nối!');
                console.error(err);
            });
        }

        console.log('✅ Orders page loaded');
    </script>
</body>
</html>