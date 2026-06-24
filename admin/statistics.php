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

// ===== XỬ LÝ LỌC THEO THỜI GIAN =====
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, week, month, year

// ===== XỬ LÝ NGÀY THÁNG =====
$from_date_display = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date_display = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// Nếu có filter, ghi đè ngày tháng
if ($filter_type === 'week') {
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $from_date_display = date('d/m/Y', strtotime($week_start));
    $to_date_display = date('d/m/Y', strtotime($week_end));
} elseif ($filter_type === 'month') {
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $from_date_display = date('d/m/Y', strtotime($month_start));
    $to_date_display = date('d/m/Y', strtotime($month_end));
} elseif ($filter_type === 'year') {
    $year_start = date('Y-01-01');
    $year_end = date('Y-12-31');
    $from_date_display = date('d/m/Y', strtotime($year_start));
    $to_date_display = date('d/m/Y', strtotime($year_end));
}

// Chuyển đổi từ dd/mm/yyyy sang yyyy-mm-dd cho SQL
$from_date_sql = '';
$to_date_sql = '';

if (!empty($from_date_display) && !empty($to_date_display)) {
    $from_parts = explode('/', $from_date_display);
    $to_parts = explode('/', $to_date_display);
    
    if (count($from_parts) == 3) {
        $from_date_sql = $from_parts[2] . '-' . $from_parts[1] . '-' . $from_parts[0];
    }
    if (count($to_parts) == 3) {
        $to_date_sql = $to_parts[2] . '-' . $to_parts[1] . '-' . $to_parts[0];
    }
}

// Nếu không có ngày, lấy 30 ngày gần nhất
if (empty($from_date_sql) || empty($to_date_sql)) {
    $from_date_sql = date('Y-m-d', strtotime('-30 days'));
    $to_date_sql = date('Y-m-d');
    $from_date_display = date('d/m/Y', strtotime('-30 days'));
    $to_date_display = date('d/m/Y');
}

// Giá trị cho input date (YYYY-MM-DD)
$from_date_input = date('Y-m-d', strtotime($from_date_sql));
$to_date_input = date('Y-m-d', strtotime($to_date_sql));

// ===== TÍNH DOANH THU (confirmed + completed) =====

// Tổng doanh thu (tất cả)
$result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status IN ('confirmed', 'completed')");
$total_revenue = $result->fetch_assoc()['total'] ?? 0;

// Doanh thu tuần này
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$result = $conn->query("
    SELECT SUM(total_amount) as total 
    FROM orders 
    WHERE status IN ('confirmed', 'completed') 
    AND DATE(created_at) BETWEEN '$week_start' AND '$week_end'
");
$week_revenue = $result->fetch_assoc()['total'] ?? 0;

// Doanh thu tháng này
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$result = $conn->query("
    SELECT SUM(total_amount) as total 
    FROM orders 
    WHERE status IN ('confirmed', 'completed') 
    AND DATE(created_at) BETWEEN '$month_start' AND '$month_end'
");
$month_revenue = $result->fetch_assoc()['total'] ?? 0;

// Doanh thu năm nay
$year_start = date('Y-01-01');
$year_end = date('Y-12-31');
$result = $conn->query("
    SELECT SUM(total_amount) as total 
    FROM orders 
    WHERE status IN ('confirmed', 'completed') 
    AND DATE(created_at) BETWEEN '$year_start' AND '$year_end'
");
$year_revenue = $result->fetch_assoc()['total'] ?? 0;

// ===== DANH SÁCH ĐƠN HÀNG =====
$orders = [];
if (!empty($from_date_sql) && !empty($to_date_sql)) {
    $sql = "
        SELECT id, order_code, customer_name, total_amount, status, created_at 
        FROM orders 
        WHERE status IN ('confirmed', 'completed') 
        AND DATE(created_at) BETWEEN '$from_date_sql' AND '$to_date_sql'
        ORDER BY created_at DESC
    ";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}

$conn->close();

// Hàm đổi tên tháng sang tiếng Việt
function thangVietNam($month) {
    $months = [
        1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3', 4 => 'Tháng 4',
        5 => 'Tháng 5', 6 => 'Tháng 6', 7 => 'Tháng 7', 8 => 'Tháng 8',
        9 => 'Tháng 9', 10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12'
    ];
    return $months[(int)$month] ?? $month;
}

// Hàm định dạng ngày tiếng Việt
function ngayVietNam($date) {
    $timestamp = strtotime($date);
    $ngay = date('d', $timestamp);
    $thang = date('m', $timestamp);
    $nam = date('Y', $timestamp);
    return $ngay . '/' . $thang . '/' . $nam;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê doanh thu - CottonUSA</title>
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
        .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 8px; }
        .btn-xs { padding: 4px 10px; font-size: 11px; border-radius: 6px; }

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
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stat-card .stat-label {
            font-size: 13px;
            color: #888;
            margin-bottom: 6px;
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

        .stat-card:nth-child(2) { border-left-color: #3b82f6; }
        .stat-card:nth-child(3) { border-left-color: #22c55e; }
        .stat-card:nth-child(4) { border-left-color: #f59e0b; }

        .stat-card.active-filter {
            border-left-color: #e30613;
            border-left-width: 6px;
            background: #fef0f0;
        }

        .stat-card .filter-hint {
            font-size: 11px;
            color: #aaa;
            margin-top: 4px;
        }
        .stat-card .filter-hint i {
            font-size: 10px;
        }

        .filter-bar {
            background: #fff;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .filter-bar .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-bar .filter-group label {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .filter-bar .filter-group .date-wrapper {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #fafafa;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            padding: 0 4px;
            transition: border-color 0.3s;
        }

        .filter-bar .filter-group .date-wrapper:focus-within {
            border-color: #e30613;
            background: #fff;
        }

        .filter-bar .filter-group .date-wrapper input[type="date"] {
            padding: 8px 6px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            background: transparent;
            min-width: 130px;
            font-family: inherit;
            color: #333;
        }

        .filter-bar .filter-group .date-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.5;
            padding: 2px;
        }

        .filter-bar .filter-group .date-wrapper input[type="date"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }

        .filter-bar .filter-group .btn-filter {
            padding: 8px 20px;
            background: #e30613;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .filter-bar .filter-group .btn-filter:hover {
            background: #c70510;
        }

        .filter-bar .filter-group .btn-reset {
            padding: 8px 16px;
            background: #e8e8e8;
            color: #333;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .filter-bar .filter-group .btn-reset:hover {
            background: #ddd;
        }

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
        .table-wrapper tbody tr:last-child td { border-bottom: none; }

        .status-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-completed { background: #dcfce7; color: #16a34a; }
        .status-confirmed { background: #e3f2fd; color: #1976d2; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 40px;
            display: block;
            margin-bottom: 12px;
            color: #ddd;
        }

        .revenue-note {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
            font-weight: 400;
        }
        .revenue-note i {
            color: #22c55e;
            margin-right: 4px;
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

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar .filter-group { flex-wrap: wrap; }
            .filter-bar .filter-group .date-wrapper input[type="date"] { min-width: 100px; }
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
            <a href="orders.php">
                <i class="fas fa-shopping-cart"></i> Đơn hàng
            </a>
            <div class="nav-label">Nội dung</div>
            <a href="statistics.php" class="active">
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
                <h1>📊 Thống kê <span>Doanh thu</span></h1>
            </div>
            <div>
                <span class="date"><i class="far fa-calendar-alt"></i> <?php echo ngayVietNam(date('Y-m-d')); ?></span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card <?php echo $filter_type === 'all' ? 'active-filter' : ''; ?>" onclick="applyFilter('all')">
                <div class="stat-label">TỔNG DOANH THU</div>
                <div class="stat-value"><?php echo number_format($total_revenue); ?><span class="currency">đ</span></div>
            </div>
            <div class="stat-card <?php echo $filter_type === 'week' ? 'active-filter' : ''; ?>" onclick="applyFilter('week')">
                <div class="stat-label">DOANH THU TUẦN</div>
                <div class="stat-value"><?php echo number_format($week_revenue); ?><span class="currency">đ</span></div>
                <div class="revenue-note"><i class="fas fa-calendar-week"></i> <?php echo ngayVietNam($week_start); ?> - <?php echo ngayVietNam($week_end); ?></div>
            </div>
            <div class="stat-card <?php echo $filter_type === 'month' ? 'active-filter' : ''; ?>" onclick="applyFilter('month')">
                <div class="stat-label">DOANH THU THÁNG</div>
                <div class="stat-value"><?php echo number_format($month_revenue); ?><span class="currency">đ</span></div>
                <div class="revenue-note"><i class="fas fa-calendar-alt"></i> <?php echo thangVietNam(date('m')); ?> - <?php echo date('Y'); ?></div>
            </div>
            <div class="stat-card <?php echo $filter_type === 'year' ? 'active-filter' : ''; ?>" onclick="applyFilter('year')">
                <div class="stat-label">DOANH THU NĂM</div>
                <div class="stat-value"><?php echo number_format($year_revenue); ?><span class="currency">đ</span></div>
                <div class="revenue-note"><i class="fas fa-calendar"></i> Năm <?php echo date('Y'); ?></div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <div class="filter-group">
                <label>Từ ngày:</label>
                <div class="date-wrapper">
                    <input type="date" id="from_date" value="<?php echo $from_date_input; ?>">
                </div>
                <label>Đến ngày:</label>
                <div class="date-wrapper">
                    <input type="date" id="to_date" value="<?php echo $to_date_input; ?>">
                </div>
                <button class="btn-filter" onclick="applyFilterCustom()"><i class="fas fa-filter"></i> Tìm</button>
                <button class="btn-reset" onclick="applyFilter('all')"><i class="fas fa-undo"></i> Tất cả</button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-wrapper">
            <div class="table-title">
                <i class="fas fa-list" style="color:#e30613;"></i> Danh sách đơn hàng
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Không có đơn hàng nào trong khoảng thời gian này</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Ngày / Giờ</th>
                            <th>Số tiền</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#<?php echo htmlspecialchars($order['order_code']); ?></strong></td>
                            <td><?php echo ngayVietNam($order['created_at']) . ' ' . date('H:i', strtotime($order['created_at'])); ?></td>
                            <td><?php echo number_format($order['total_amount']); ?>đ</td>
                            <td>
                                <?php if ($order['status'] == 'completed'): ?>
                                    <span class="status-badge status-completed"><i class="fas fa-check-circle"></i> Hoàn thành</span>
                                <?php else: ?>
                                    <span class="status-badge status-confirmed"><i class="fas fa-check"></i> Đã xác nhận</span>
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

        // Apply filter by type (all, week, month, year)
        function applyFilter(type) {
            if (type === 'all') {
                window.location.href = 'statistics.php';
            } else {
                window.location.href = 'statistics.php?filter=' + type;
            }
        }

        // Apply custom filter from date inputs
        function applyFilterCustom() {
            const from_date = document.getElementById('from_date').value;
            const to_date = document.getElementById('to_date').value;
            
            if (from_date && to_date) {
                var fromParts = from_date.split('-');
                var toParts = to_date.split('-');
                var fromDisplay = fromParts[2] + '/' + fromParts[1] + '/' + fromParts[0];
                var toDisplay = toParts[2] + '/' + toParts[1] + '/' + toParts[0];
                
                window.location.href = 'statistics.php?from_date=' + encodeURIComponent(fromDisplay) + '&to_date=' + encodeURIComponent(toDisplay);
            }
        }

        // Enter key support
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const from_date = document.getElementById('from_date');
                const to_date = document.getElementById('to_date');
                if (document.activeElement === from_date || document.activeElement === to_date) {
                    applyFilterCustom();
                }
            }
        });
    </script>
</body>
</html>