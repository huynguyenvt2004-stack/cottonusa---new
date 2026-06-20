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

// Kết nối database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// ===== PHÂN TRANG =====
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Đếm tổng số sản phẩm
$countResult = $conn->query("SELECT COUNT(*) as total FROM products");
$totalCount = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $limit);

// Lấy danh sách sản phẩm có phân trang - SỬA TỪ product_stock
$products = [];
$result = $conn->query("
    SELECT 
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
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$conn->close();

// ===== TÍNH THỐNG KÊ TỪ product_stock =====
$conn = new mysqli($host, $user, $pass, $db);
$total_products = $totalCount;
$total_stock = 0;
$total_sold = 0;
$low_stock = 0;

// Lấy tổng tồn kho từ product_stock
$stockResult = $conn->query("SELECT COALESCE(SUM(stock), 0) as total_stock FROM product_stock");
if ($stockResult) {
    $total_stock = (int)$stockResult->fetch_assoc()['total_stock'];
}

// Lấy tổng đã bán từ order_items
$soldResult = $conn->query("SELECT COALESCE(SUM(quantity), 0) as total_sold FROM order_items");
if ($soldResult) {
    $total_sold = (int)$soldResult->fetch_assoc()['total_sold'];
}

// Đếm sản phẩm sắp hết (tồn kho <= 5 và > 0)
$lowStockResult = $conn->query("
    SELECT COUNT(DISTINCT product_id) as low_stock 
    FROM product_stock 
    WHERE stock > 0 AND stock <= 5
");
if ($lowStockResult) {
    $low_stock = (int)$lowStockResult->fetch_assoc()['low_stock'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý kho - CottonUSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* ===== GIỮ NGUYÊN CSS CỦA BẠN ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #f0f2f5; display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 250px;
            background: #1a1a2e;
            color: #fff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar-brand {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar-brand a { display: block; text-decoration: none; }
        .sidebar-brand img { height: 50px; width: auto; display: block; margin: 0 auto; }
        .sidebar-nav { flex: 1; padding: 16px 0; }
        .sidebar-nav .nav-label {
            font-size: 11px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.25);
            padding: 8px 24px;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 24px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar-nav a.active {
            background: rgba(227,6,19,0.15);
            color: #fff;
            border-left-color: #e30613;
        }
        .sidebar-nav a i { width: 20px; text-align: center; }
        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        .sidebar-footer .user-info .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e30613;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .sidebar-footer .user-info .name { font-size: 14px; font-weight: 600; }
        .sidebar-footer .user-info .role { font-size: 12px; color: rgba(255,255,255,0.4); }
        .sidebar-footer a {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar-footer a:hover { color: #e30613; }
        
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
        .page-header h1 { font-size: 24px; color: #1a1a2e; }
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
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
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
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #fff;
            flex-shrink: 0;
        }
        .stat-info h3 { font-size: 24px; color: #1a1a2e; }
        .stat-info p { font-size: 13px; color: #999; }
        
        .toolbar {
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
        .toolbar .left { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .toolbar .right { display: flex; gap: 10px; align-items: center; }
        .search-box {
            display: flex;
            align-items: center;
            background: #f5f6fa;
            border-radius: 10px;
            padding: 0 14px;
            border: 2px solid transparent;
        }
        .search-box:focus-within { border-color: #e30613; background: #fff; }
        .search-box input {
            border: none;
            background: transparent;
            padding: 10px 12px;
            font-size: 14px;
            outline: none;
            width: 240px;
        }
        .filter-select {
            padding: 10px 14px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 13px;
            background: #fff;
            outline: none;
            cursor: pointer;
        }
        .filter-select:focus { border-color: #e30613; }
        
        .table-wrapper {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .table-wrapper table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .table-wrapper thead { background: #f8f9fa; }
        .table-wrapper thead th {
            padding: 14px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #888;
            border-bottom: 2px solid #e8e8e8;
        }
        .table-wrapper tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
            vertical-align: middle;
        }
        .table-wrapper tbody tr:hover { background: #fafafa; }
        
        .tag-group { display: flex; flex-wrap: wrap; gap: 4px; }
        .tag {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            background: #f0f0f0;
            color: #555;
            border: 1px solid #e0e0e0;
        }
        .tag-size { background: #e8f0fe; color: #1a73e8; border-color: #d2e3fc; }
        .tag-color { background: #fce8e8; color: #d93025; border-color: #f5c6c6; }
        .tag-color .color-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 4px;
            border: 1px solid rgba(0,0,0,0.1);
            vertical-align: middle;
        }
        .tag-more { background: transparent; color: #888; border: 1px dashed #ccc; }
        
        .status-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-in-stock { background: #dcfce7; color: #16a34a; }
        .status-low-stock { background: #fef3c7; color: #d97706; }
        .status-out-of-stock { background: #fee2e2; color: #dc2626; }
        
        .loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,0.7);
            z-index: 999;
            justify-content: center;
            align-items: center;
        }
        .loading-overlay.active { display: flex; }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e0e0e0;
            border-top-color: #e30613;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .toast-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1a1a2e;
            color: #fff;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            z-index: 9999;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            animation: slideUp 0.4s ease;
            max-width: 400px;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .search-box input { width: 150px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
        }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
        }
    </style>
</head>
<body>

    <!-- Loading -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <a href="home.php" style="display:block; text-decoration:none;">
                <img src="../images/logo.avif" alt="CottonUSA">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="home.php" style="display:flex; align-items:center; gap:14px; padding:12px 24px; color:rgba(255,255,255,0.7); text-decoration:none; font-size:14px; transition:all 0.2s; border-left:3px solid transparent; background:rgba(255,255,255,0.05); margin-bottom:4px;">
                <i class="fas fa-store"></i> Trang chính
            </a>
            <div class="nav-label">Tổng quan</div>
            <a href="dashboard.php" class="active">
                <i class="fas fa-chart-pie"></i> Thống kê
            </a>
            <a href="products.php">
                <i class="fas fa-tshirt"></i> Sản phẩm
            </a>
            <a href="orders.php">
                <i class="fas fa-shopping-cart"></i> Đơn hàng
            </a>
            <a href="#">
                <i class="fas fa-warehouse"></i> Kho hàng
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

    <!-- Main -->
    <main class="main-content">
        <div class="page-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <h1>📊 Thống kê</h1>
            </div>
            <div>
                <span class="date"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?></span>
                <a href="add-product.php" class="btn btn-primary" style="margin-left:12px;"><i class="fas fa-plus"></i> Thêm sản phẩm</a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e30613;"><i class="fas fa-tshirt"></i></div>
                <div class="stat-info">
                    <h3 id="statTotalProducts"><?php echo $total_products; ?></h3>
                    <p>Tổng sản phẩm</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#3b82f6;"><i class="fas fa-boxes"></i></div>
                <div class="stat-info">
                    <h3 id="statTotalStock"><?php echo number_format($total_stock); ?></h3>
                    <p>Tổng tồn kho</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#22c55e;"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3 id="statTotalSold"><?php echo number_format($total_sold); ?></h3>
                    <p>Đã bán</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f59e0b;"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-info">
                    <h3 id="statLowStock"><?php echo $low_stock; ?></h3>
                    <p>Sắp hết hàng</p>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="left">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Tìm mã hoặc tên sản phẩm...">
                </div>
                <select class="filter-select" id="statusFilter">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="in-stock">Còn hàng</option>
                    <option value="low-stock">Sắp hết</option>
                    <option value="out-of-stock">Hết hàng</option>
                </select>
            </div>
            <div class="right">
                <button class="btn btn-secondary btn-sm" id="btnRefresh">
                    <i class="fas fa-sync-alt"></i> Làm mới
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Mã SP</th>
                        <th>Tên sản phẩm</th>
                        <th>Size</th>
                        <th>Màu</th>
                        <th>Giá</th>
                        <th>Đã bán</th>
                        <th>Tồn kho</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;padding:40px;color:#999;">
                                <i class="fas fa-box" style="font-size:40px;display:block;margin-bottom:12px;"></i>
                                Chưa có sản phẩm nào. <a href="add-product.php" style="color:#e30613;">Thêm sản phẩm</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): 
                            $sizes = !empty($product['sizes']) ? explode(',', $product['sizes']) : [];
                            $colors = !empty($product['colors']) ? explode(',', $product['colors']) : [];
                            $stock = (int)$product['total_stock'];
                            $sold = (int)$product['sold'];
                            $price = (int)$product['price'];
                            
                            if ($stock <= 0) {
                                $status = 'Hết hàng';
                                $statusClass = 'status-out-of-stock';
                            } elseif ($stock <= 5) {
                                $status = 'Sắp hết';
                                $statusClass = 'status-low-stock';
                            } else {
                                $status = 'Còn hàng';
                                $statusClass = 'status-in-stock';
                            }
                            
                            $colorHex = ['Đen'=>'#1a1a1a','Trắng'=>'#ffffff','Kem'=>'#f5f5dc','Hồng'=>'#f8bbd0','Navy'=>'#1a237e','Xám'=>'#9e9e9e','Xanh'=>'#2196f3'];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($product['id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td>
                                <div class="tag-group">
                                    <?php 
                                    $displaySizes = array_slice($sizes, 0, 4);
                                    foreach ($displaySizes as $size): 
                                    ?>
                                        <span class="tag tag-size"><?php echo htmlspecialchars($size); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($sizes) > 4): ?>
                                        <span class="tag tag-more">+<?php echo count($sizes) - 4; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="tag-group">
                                    <?php 
                                    $displayColors = array_slice($colors, 0, 3);
                                    foreach ($displayColors as $color): 
                                        $hex = $colorHex[$color] ?? '#cccccc';
                                    ?>
                                        <span class="tag tag-color">
                                            <span class="color-dot" style="background:<?php echo $hex; ?>;<?php echo $color === 'Trắng' ? 'border-color:#ddd;' : ''; ?>"></span>
                                            <?php echo htmlspecialchars($color); ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($colors) > 3): ?>
                                        <span class="tag tag-more">+<?php echo count($colors) - 3; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo number_format($price); ?>đ</td>
                            <td><?php echo $sold; ?></td>
                            <td><?php echo $stock; ?></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                            <td>
                                <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-xs"><i class="fas fa-edit"></i></a>
                                <button class="btn btn-danger btn-xs btn-delete" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;flex-wrap:wrap;gap:10px;">
            <span style="font-size:13px;color:#888;" id="productCount">Hiển thị <?php echo count($products); ?>/<?php echo $totalCount; ?> sản phẩm</span>
            <div style="display:flex;gap:6px;" id="paginationControls">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" style="<?php echo $i == $page ? 'background:#e30613;' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Toggle sidebar mobile
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });

        // ===== BIẾN PHÂN TRANG =====
        let currentPage = <?php echo $page; ?>;
        let totalPages = <?php echo $totalPages; ?>;

        // ===== NÚT LÀM MỚI =====
        document.getElementById('btnRefresh')?.addEventListener('click', function() {
            const btn = this;
            const icon = btn.querySelector('i');
            
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'all';
            
            icon.classList.add('fa-spin');
            btn.disabled = true;
            document.getElementById('loadingOverlay').classList.add('active');
            
            fetch('api/get-products.php')
                .then(response => {
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            throw new Error('Server trả về HTML thay vì JSON: ' + text.substring(0, 100));
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        updateTable(data.products);
                        updateStats(data.stats);
                        filterTable();
                        showToast('✅ Đã làm mới danh sách sản phẩm!');
                    } else {
                        showToast('❌ Lỗi: ' + data.message);
                    }
                })
                .catch(error => {
                    showToast('❌ Lỗi: ' + error.message);
                    console.error('Error:', error);
                })
                .finally(() => {
                    document.getElementById('loadingOverlay').classList.remove('active');
                    icon.classList.remove('fa-spin');
                    btn.disabled = false;
                });
        });

        // ===== CẬP NHẬT BẢNG =====
        function updateTable(products) {
            const tbody = document.getElementById('productTableBody');
            
            if (!products || products.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" style="text-align:center;padding:40px;color:#999;">
                            <i class="fas fa-box" style="font-size:40px;display:block;margin-bottom:12px;"></i>
                            Chưa có sản phẩm nào.
                        </td>
                    </tr>
                `;
                document.getElementById('productCount').textContent = 'Hiển thị 0 sản phẩm';
                return;
            }
            
            const colorHex = {
                'Đen': '#1a1a1a', 'Trắng': '#ffffff', 'Kem': '#f5f5dc',
                'Hồng': '#f8bbd0', 'Navy': '#1a237e', 'Xám': '#9e9e9e',
                'Xanh': '#2196f3', 'Đỏ': '#dc2626', 'Vàng': '#f59e0b'
            };
            
            let html = '';
            products.forEach(p => {
                const sizes = p.sizes ? p.sizes.split(',') : [];
                const colors = p.colors ? p.colors.split(',') : [];
                const stock = parseInt(p.total_stock) || 0;
                const sold = parseInt(p.sold) || 0;
                const price = parseInt(p.price) || 0;
                
                let status, statusClass;
                if (stock <= 0) { status = 'Hết hàng'; statusClass = 'status-out-of-stock'; }
                else if (stock <= 5) { status = 'Sắp hết'; statusClass = 'status-low-stock'; }
                else { status = 'Còn hàng'; statusClass = 'status-in-stock'; }
                
                let sizeHtml = sizes.slice(0, 4).map(s => `<span class="tag tag-size">${s}</span>`).join('');
                if (sizes.length > 4) sizeHtml += `<span class="tag tag-more">+${sizes.length - 4}</span>`;
                
                let colorHtml = colors.slice(0, 3).map(c => {
                    const hex = colorHex[c] || '#cccccc';
                    const border = c === 'Trắng' ? 'border-color:#ddd;' : '';
                    return `<span class="tag tag-color"><span class="color-dot" style="background:${hex};${border}"></span>${c}</span>`;
                }).join('');
                if (colors.length > 3) colorHtml += `<span class="tag tag-more">+${colors.length - 3}</span>`;
                
                html += `
                    <tr>
                        <td><strong>${p.id}</strong></td>
                        <td>${p.name}</td>
                        <td><div class="tag-group">${sizeHtml || '--'}</div></td>
                        <td><div class="tag-group">${colorHtml || '--'}</div></td>
                        <td>${price.toLocaleString()}đ</td>
                        <td>${sold}</td>
                        <td>${stock}</td>
                        <td><span class="status-badge ${statusClass}">${status}</span></td>
                        <td>
                            <a href="edit-product.php?id=${p.id}" class="btn btn-primary btn-xs"><i class="fas fa-edit"></i></a>
                            <button class="btn btn-danger btn-xs btn-delete" data-id="${p.id}" data-name="${p.name}"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            document.getElementById('productCount').textContent = `Hiển thị ${products.length} sản phẩm`;
        }

        // ===== CẬP NHẬT THỐNG KÊ =====
        function updateStats(stats) {
            if (!stats) return;
            document.getElementById('statTotalProducts').textContent = stats.total_products || 0;
            document.getElementById('statTotalStock').textContent = (stats.total_stock || 0).toLocaleString();
            document.getElementById('statTotalSold').textContent = (stats.total_sold || 0).toLocaleString();
            document.getElementById('statLowStock').textContent = stats.low_stock || 0;
        }

        // ===== CẬP NHẬT PHÂN TRANG =====
        function updatePagination(totalPages, currentPage) {
            const container = document.getElementById('paginationControls');
            if (!container) return;
            
            let html = '';
            
            if (currentPage > 1) {
                html += `<a href="?page=${currentPage - 1}" class="btn btn-secondary btn-sm page-link"><i class="fas fa-chevron-left"></i></a>`;
            }
            
            for (let i = 1; i <= totalPages; i++) {
                const active = i === currentPage ? 'btn-primary' : 'btn-secondary';
                const style = i === currentPage ? 'background:#e30613;' : '';
                html += `<a href="?page=${i}" class="btn ${active} btn-sm page-link" style="${style}">${i}</a>`;
            }
            
            if (currentPage < totalPages) {
                html += `<a href="?page=${currentPage + 1}" class="btn btn-secondary btn-sm page-link"><i class="fas fa-chevron-right"></i></a>`;
            }
            
            container.innerHTML = html;
            
            document.querySelectorAll('.page-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = new URL(this.href);
                    const page = url.searchParams.get('page');
                    if (page) {
                        loadPage(parseInt(page));
                    }
                });
            });
        }

        // ===== TẢI TRANG =====
        function loadPage(page) {
            currentPage = page;
            document.getElementById('loadingOverlay').classList.add('active');
            
            fetch('api/get-products.php?page=' + page)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        updateTable(data.products);
                        updatePagination(data.total_pages, data.current_page);
                        showToast('📄 Trang ' + page);
                    } else {
                        showToast('❌ Lỗi: ' + data.message);
                    }
                })
                .catch(error => {
                    showToast('❌ Lỗi kết nối: ' + error.message);
                    console.error('Error:', error);
                })
                .finally(() => {
                    document.getElementById('loadingOverlay').classList.remove('active');
                });
        }

        // ===== FILTER TABLE =====
        function filterTable() {
            const keyword = document.getElementById('searchInput').value.toLowerCase().trim();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#productTableBody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (row.querySelector('td[colspan]')) {
                    row.style.display = '';
                    return;
                }
                
                const cells = row.querySelectorAll('td');
                const maSP = cells[0] ? cells[0].textContent.toLowerCase() : '';
                const tenSP = cells[1] ? cells[1].textContent.toLowerCase() : '';
                
                const badge = row.querySelector('.status-badge');
                const badgeText = badge ? badge.textContent.trim() : '';
                
                let show = true;
                
                if (keyword) {
                    if (!maSP.includes(keyword) && !tenSP.includes(keyword)) {
                        show = false;
                    }
                }
                
                if (show && statusFilter !== 'all') {
                    if (statusFilter === 'in-stock' && badgeText !== 'Còn hàng') show = false;
                    if (statusFilter === 'low-stock' && badgeText !== 'Sắp hết') show = false;
                    if (statusFilter === 'out-of-stock' && badgeText !== 'Hết hàng') show = false;
                }
                
                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });
            
            document.getElementById('productCount').textContent = `Hiển thị ${visibleCount} sản phẩm`;
        }

        // ===== TOAST =====
        function showToast(message) {
            const existing = document.querySelector('.toast-notification');
            if (existing) existing.remove();
            
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(20px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ===== SỰ KIỆN =====
        document.getElementById('searchInput')?.addEventListener('input', filterTable);
        document.getElementById('statusFilter')?.addEventListener('change', filterTable);

        // ===== XÓA SẢN PHẨM =====
        document.addEventListener('click', function(e) {
            const deleteBtn = e.target.closest('.btn-delete');
            if (deleteBtn) {
                const id = deleteBtn.dataset.id;
                const name = deleteBtn.dataset.name;
                
                if (confirm(`Xác nhận xóa sản phẩm "${name}"?`)) {
                    fetch('api/delete-product.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=' + encodeURIComponent(id)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('✅ Đã xóa sản phẩm "' + name + '"');
                            loadPage(currentPage);
                        } else {
                            showToast('❌ ' + data.message);
                        }
                    })
                    .catch(err => {
                        showToast('❌ Lỗi kết nối!');
                    });
                }
            }
        });

        console.log('✅ Dashboard đã sẵn sàng!');
    </script>
</body>
</html>